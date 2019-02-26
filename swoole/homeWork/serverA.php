<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-2-22
 * Time: 上午10:51
 */
namespace HomeWork;

use Swoole\Server;
class serverA
{
    static private $server = null;
    static private $config = [];
    const HOST = '0.0.0.0';
    const PORT = 9800;

    /**
     * serverA constructor.
     * @param $config
     */
    public function __construct($config){
        if(is_null(self::$server)){
            self::$config = $config;
            $host = $config['host'] ?? self::HOST;
            $port = $config['port'] ?? self::PORT;
//            self::$server = new Server($host,$port,SWOOLE_PROCESS,SWOOLE_TCP);
            self::$server = new Server($host,$port);
            unset($config['host'],$config['port']);
            self::$server->set($config);
        }

        self::$server->on('connect',[$this,'onConnection']);
//        self::$server->on('re',[$this,'onConnection']);
//        self::$server->on('connection',[$this,'onConnection']);
        self::$server->on('receive', [$this,'onReceive']);
        self::$server->on('finish', [$this,'onMessage']);
        self::$server->on('close', [$this,'onClose']);
        self::$server->start();
//        return self::$server;
    }

    public function onConnection(\Swoole\Server $server,int $fd){
//        var_dump($server,$fd);
//        echo $fd.'-'.'2343'.PHP_EOL;
    }

    public function onReceive($server,int $fd, $reactor_id, $data){
//        var_dump($server,$fd, $reactor_id, $data);
//        $server->send($fd,'收到了');

        print_r(unpack('Nlen/a*string',$data));
//        $server->send($fd,'服务端接受的数据：'.substr($data,0,unpack('N',$data)[1]).PHP_EOL);
        $server->send($fd,'服务端接受的数据：'.$fd.'--'.unpack('N',substr($data,0,4))[1].PHP_EOL);
//        echo $fd.'-'.$reactor_id.$data.PHP_EOL;
    }

    public function onMessage(\Swoole\Server $server,int $fd, $reactor_id, $data){
//        var_dump($server,$fd, $reactor_id, $data);
        echo '我发你的消息'.PHP_EOL;
    }

    public function onClose(\Swoole\Server $server, $fd){
//        var_dump($server,$fd);
        echo "Client: Close.\n";
    }
}

$config = array(
    'host' => '127.0.0.1',
    'port' => 9800,
    'worker_num' => 2,    //worker process num
//    'backlog' => 128,   //listen backlog
//    'max_request' => 5000,
//    'dispatch_mode'=>1,
    'open_length_check' => true,
    'package_length_type' => 'N',
    'package_length_offset' => 0,
    'package_body_offset' => 4,
    'package_max_length' => 100
);

$serverA = new serverA($config);
//$serverA->start();
