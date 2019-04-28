<?php


namespace App\Controllers\DataProvider;


use App\Constants\StatusCode;
use App\Controllers\Common;
use App\Exceptions\NotifyException;
use App\Services\Dao\CreditDao;
use App\Services\Dao\UsersDao;
use App\Utils\Functions;
use PhpBoot\DB\DB;

class TongDun extends Common implements DataProviderInterface
{
    use DataProviderTrait;
    public static function access($user_info)
    {
//        yield $tongdunData = self::tongdunData($user_info);
        yield $gen = self::tongdunData($user_info);
        $tongdunData = $gen->current();
        $preLoanData['tongdun_result'] = json_encode($tongdunData,JSON_UNESCAPED_UNICODE);
        $creditService = self::getService(CreditDao::class);
        $thirdPartyCredit = self::$app->get('third_party_credit');
        if(is_array($tongdunData) && $tongdunData['status'] == CreditDao::CREDIT_APPLY_STATUS_SUCCESS){
            $preLoanData['tongdun_decision'] = $tongdunData['result']['final_decision'];
            $decision = strtolower($preLoanData['tongdun_decision']);
//            $tongdunScore = intval($tongdunData['result']['Score']['scoreconson']);
            $tongdunScore = 200;
            if($thirdPartyCredit['tongdun']['is_grade']){
                // 将第三方的分值转化成平台的分值
                $creditScore = Functions::calCreditScore($tongdunScore,$thirdPartyCredit['tongdun']['sourceMaxScore'],self::calOrgMaxCreditScore());

                self::$organizationScore = bcadd(self::$organizationScore,$creditScore,2);

                $creditRuleLog = [
                    'name' => '同盾科技：信用评分',
                    'score' => $tongdunScore
                ];

                $insertRuleLogSql = "insert into qy_credit_rule_log (`u_code`,`cr_id`,`cr_rule_name`,`cr_value`,`cr_type`,`add_time`) values ";
                $value = '';
                $current_time = date('Y-m-d H:i:s');

                $value .= "('{$user_info['ucode']}',0,'{$creditRuleLog['name']}',{$creditRuleLog['score']},2,'{$current_time}')";

                self::$app->get(\PhpBoot\DB\DB::class)->execute($insertRuleLogSql.$value, []);

                // 修改用户机构评分
                self::getService(UsersDao::class)->updateUserCreditScore($user_info['ucode'],self::$organizationScore);
            }

            if(in_array($decision,['pass','review'])){
                if($decision == 'pass'){
                    $preLoanData['tongdun_decision'] = '通过';
                }elseif($decision == 'review'){
                    $preLoanData['tongdun_decision'] = '复议';
                }else{
                    $preLoanData['tongdun_decision'] = '通过';
                }

                $creditService->insert($preLoanData,$user_info);
            }else{
                $msg = '同盾拒绝';
                $user_info['remark'] = $msg;
                self::$app->get(DB::class)->transaction(function() use ($user_info,$preLoanData,$creditService) {
                    $creditService->insertBlack($user_info);
                    $creditApplyData['ucvl_true_name'] = $user_info['ui_true_name'];
                    $creditApplyData['ucvl_remark'] = $user_info['remark'];
                    $creditApplyData['u_code'] = $user_info['ucode'];
                    $creditApplyData['ucvl_status'] = CreditDao::CREDIT_ORG_CHECK_REJECT; // 机构审核拒绝、
                    $preLoanData['tongdun_decision'] = '拒绝';
                    $creditService->insertCreditValueApply($creditApplyData);
                    $creditService->insert($preLoanData,$user_info);
                });

                throw new NotifyException('系统拒绝！',5000);
            }
        }else{
            if(isset($tongdunData['code'])){
                $code = $tongdunData['code'] ;
            }elseif($tongdunData['status']){
                $code = $tongdunData['status'];
            }else{
                $code = StatusCode::NORMAL_ERROR;
            }
            throw new NotifyException('系统调用失败！',$code);
        }
    }


    private function tongdunData($data){

        if(empty($data['ui_ident_no'])){
            throw new NotifyException('用户身份证号必传！',5000);
        }else{
            $tongDunParams['id_number'] = $data['ui_ident_no'];
        }

        if(empty($data['ui_true_name'])){
            throw new NotifyException('用户姓名必传！',5000);
        }else{
            $tongDunParams['account_name'] = $data['ui_true_name'];
        }

        if(empty($data['u_name'])){
            throw new NotifyException('用户手机号必传！',5000);
        }else{
            $tongDunParams['account_mobile'] = $data['u_name'];
        }

        $tongResult =  self::getWS('channel','tongdun_apply',$tongDunParams);
//        yield;
        yield $tongResult;

    }
}