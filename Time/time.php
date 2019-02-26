<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-2-14
 * Time: 下午1:57
 */

// 使用日期对象

$time = new DateTime();


/*(
    [date] => 2019-02-12 13:56:34.000000
    [timezone_type] => 3
    [timezone] => Asia/Chongqing
)*/

$time->setTimestamp(strtotime('+2 day'));

/*(
    [date] => 2019-02-14 13:56:34.000000
    [timezone_type] => 3
    [timezone] => Asia/Chongqing
)*/