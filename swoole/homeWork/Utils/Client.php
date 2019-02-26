<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-2-22
 * Time: 上午10:51
 */

namespace HomeWork\utils;

use Swoole\Client as SwooleClient;

class Client{

    static private $client = null;
    static private $config = [];
    const HOST = '127.0.0.1';
    const PORT = 9800;

    /**
     * client constructor.
     * @param $config
     */
    public function __construct($config){
        if(is_null(self::$client)){
            self::$client = new SwooleClient(SWOOLE_TCP);
        }

        self::$config = $config;
        $host = $config['host'] ?? self::HOST;
        $port = $config['port'] ?? self::PORT;

        if (!self::$client->connect($host, $port, 5)) {
            exit("connect failed. Error: {".self::$client->errCode."}\n");
        }

    }

    public function send($data){
        self::$client->send($data."\n");
        $recv = self::$client->recv();
        if(is_null($recv)){
            exit('服务端返回数据为空');
        }
        echo ('服务端返回数据为:'.$recv.PHP_EOL);
        self::$client->close();
    }
}
