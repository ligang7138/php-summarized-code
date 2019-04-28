<?php


namespace App\Controllers\DataProvider;


use App\Constants\StatusCode;
use App\Controllers\Common;
use App\Exceptions\NotifyException;
use App\Services\Dao\CreditDao;
use App\Services\Dao\UsersDao;
use App\Utils\Functions;
use PhpBoot\DB\DB;

class BaiRong extends Common implements DataProviderInterface
{

    use DataProviderTrait;
    public static function access($user_info)
    {
//        yield $bairongData = self::bairongData($user_info);
        yield $gen = self::bairongData($user_info);
        $bairongData = $gen->current();
        $thirdPartyCredit = self::$app->get('third_party_credit');
        if(is_array($bairongData) && in_array($bairongData['status'],[CreditDao::CREDIT_APPLY_STATUS_SUCCESS,2001])){

            $preLoanData['bairong_result'] = json_encode($bairongData,JSON_UNESCAPED_UNICODE);
            $preLoanData['bairong_decision'] = $bairongData['result']['Rule']['result']['final_decision'];
            $decision = strtolower($preLoanData['bairong_decision']);
            $bairongScore = intval($bairongData['result']['Score']['scoreconson']);
            // 参与评分

            if($thirdPartyCredit['bairong']['is_grade']){
                // 将第三方的分值转化成平台的分值
                $creditScore = Functions::calCreditScore($bairongScore,$thirdPartyCredit['bairong']['sourceMaxScore'],self::calOrgMaxCreditScore());

                self::$organizationScore = bcadd(self::$organizationScore,$creditScore,2);

                $creditRuleLog = [
                    'name' => '百融金服：信用评分',
                    'score' => $bairongScore
                ];

                $insertRuleLogSql = "insert into qy_credit_rule_log (`u_code`,`cr_id`,`cr_rule_name`,`cr_value`,`cr_type`,`add_time`) values ";
                $value = '';
                $current_time = date('Y-m-d H:i:s');

                $value .= "('{$user_info['ucode']}',0,'{$creditRuleLog['name']}',{$creditRuleLog['score']},2,'{$current_time}')";

                self::$app->get(\PhpBoot\DB\DB::class)->execute($insertRuleLogSql.$value, []);

                // 修改用户机构评分
                self::getService(UsersDao::class)->updateUserCreditScore($user_info['ucode'],self::$organizationScore);
            }
            if($bairongData['status'] == 2001){
                $preLoanData['bairong_decision'] = '通过';
                self::getCreditService()->insert($preLoanData,$user_info);
            }elseif(in_array($decision ,['accept','review'])){

                if($decision == 'accept'){
                    $preLoanData['bairong_decision'] = '通过';
                }elseif($decision == 'review'){
                    $preLoanData['bairong_decision'] = '复议';
                }else{
                    $preLoanData['bairong_decision'] = '通过';
                }

                self::getCreditService()->insert($preLoanData,$user_info);
                // 返回无建议的情况，根据信用分
            }elseif(empty($decision) && $bairongScore >= 475){
                if($bairongScore >= 475 && $bairongScore < 720){
                    $preLoanData['bairong_decision'] = '复议';
                }elseif ($bairongScore >= 720){
                    $preLoanData['bairong_decision'] = '通过';
                }
                self::getCreditService()->insert($preLoanData,$user_info);
            }else{
                $msg = '百融拒绝';
                $user_info['remark'] = $msg;
                self::$app->get(DB::class)->transaction(function() use ($user_info,$preLoanData) {
                    self::getCreditService()->insertBlack($user_info);
                    $creditApplyData['u_code'] = $user_info['ucode'];
                    $creditApplyData['ucvl_remark'] = $user_info['remark'];
                    $creditApplyData['ucvl_true_name'] = $user_info['ui_true_name'];
                    $creditApplyData['ucvl_status'] = CreditDao::CREDIT_ORG_CHECK_REJECT; // 机构审核拒绝、
                    $preLoanData['bairong_decision'] = '拒绝';
                    self::getCreditService()->insertCreditValueApply($creditApplyData);
                    self::getCreditService()->insert($preLoanData,$user_info);
                });

//                throw new NotifyException('系统拒绝！',5000);
            }
        }else{
            if(isset($bairongData['code'])){
                $code = $bairongData['code'] ;
            }elseif($bairongData['status']){
                $code = $bairongData['status'];
            }else{
                $code = StatusCode::NORMAL_ERROR;
            }
//            throw new NotifyException('系统调用失败！',$code);
        }
    }


    private function bairongData($data){

        if(empty($data['ui_ident_no'])){
            throw new NotifyException('用户身份证号必传！',5000);
        }else{
            $bairongParams['id'] = $data['ui_ident_no'];
        }

        if(empty($data['ui_true_name'])){
            throw new NotifyException('用户姓名必传！',5000);
        }else{
            $bairongParams['name'] = $data['ui_true_name'];
        }

        if(empty($data['u_name'])){
            throw new NotifyException('用户手机号必传！',5000);
        }else{
            $bairongParams['cell'] = $data['u_name'];
        }

        $bairongParams['strategy'] = 'risk_cash';

        $bairongResult =  self::getWS('channel','bairong',$bairongParams);
        yield $bairongResult;

    }
}