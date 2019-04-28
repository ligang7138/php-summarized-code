<?php

namespace App\Controllers;

use App\Corutine\Scheduler;
use App\Corutine\Task;
use App\Services\Dao\CityDao;
use App\Services\Dao\ConfigDao;
use App\Services\Dao\CreditDao;
use App\Services\Dao\UsersDao;
use App\Constants\StatusCode;
use App\Utils\Functions;
use App\Constants\MesageType;
use App\Exceptions\NotifyException;
use PhpBoot\Utils\Logger;

/**
 * Class Credit 信用支付相关
 * @package App\Controllers
 */
class Credit extends Common
{



    /**
     * @param $user_info
     * @param bool $type
     * @throws NotifyException
     */
    public function accessThirdOrgs($user_info,$type = false){

        $thirdPartyCredit = [
            'tongdun' => ['enable' => true,'is_grade' => false,'class' => 'App\Controllers\DataProvider\TongDun'],
            'bairong' => ['enable' => true,'is_grade' => true ,'class' => 'App\Controllers\DataProvider\BaiRong','sourceMaxScore' => 1000],
        ];
        $scheduler = new Scheduler; // 实例化一个调度器

        foreach ($thirdPartyCredit as $k => $org){

            // 闭包函数里用yield -> 生成器    测试结果没有并行，没有节省时间
            $scheduler->addTask((function () use ($org, $user_info) {
                yield from $org['class']::access($user_info);
            })());


            /*go(function () use($org, $user_info){

                co::sleep(2);
                echo $org['class'].PHP_EOL;
//                        $org['class']::access($user_info);
            });*/


        }
        $scheduler->run();
    }


}