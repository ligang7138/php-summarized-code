<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-2-22
 * Time: 上午10:51
 */

namespace HomeWork;
use HomeWork\Utils\Client;
require_once '../../vendor/autoload.php';
$config = [
    'host' => '127.0.0.1',
    'port' => 9800,
    'open_length_check' => true,
    'package_length_type' => 'N',
    'package_length_offset' => 0,
    'package_body_offset' => 4,
    'package_max_length' => 1024 * 1024 * 1
];


// tcp粘包
$info = str_repeat('a',1603);
//echo pack('N',strlen($info).$info);die;
(new Client($config))->send(pack("Na*",strlen($info),$info));

// 客户端发送swoft rpc请求示例
(new Client($config))->send(json_encode([
        "interface" => "App\Lib\DemoInterface",
        "version" => "1.0.0",
        "method" => "getUser",
        "params" => [45],
    ])."\r\n");