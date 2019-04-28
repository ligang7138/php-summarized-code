<?php


namespace App\Controllers\DataProvider;
use App\Controllers\Common;
use App\Services\Dao\ConfigDao;
use App\Services\Dao\CreditDao;

trait DataProviderTrait
{
    protected static $organizationScore = 0;
    /**
     * 计算参与评分的机构分配到的最大分值
     */
    public static function calOrgMaxCreditScore(){

        $number_of_scoring_agencies = 0;
        $orgs = $GLOBALS['app']->get('third_party_credit');
        foreach ($orgs as $org){
            if($org['is_grade']) $number_of_scoring_agencies++;
        }
        $config = Common::getService(ConfigDao::class)->selectValueByKey('org_max_score');
        return bcdiv(intval($config['c_value']),$number_of_scoring_agencies,2);
    }

    public static function getCreditService(){
        return self::getService(CreditDao::class);
    }
}