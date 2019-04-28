<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-3-4
 * Time: 上午9:05
 */

class SocketTcpServer
{
    private $server = null;
    private $config = [
        'uri' => 'tcp://127.0.0.1:8888',// tcp协议
//        'port' => 8888,
    ];
    public function __construct($config = [])
    {
        if(is_null($this->server)){
            if(!empty($confit)) $this->config = array_merge($this->config,$config);
            $this->server = stream_socket_server($this->config['uri'],$errno, $errstr);

            if (!$this->server) {
                echo "$errstr ($errno)";
            } else {
                // while循环持续监听，否则处理一次请求后服务就断开
                while ($conn = stream_socket_accept($this->server)) {
                    fwrite($conn, 'The local time is ' . date('n/j/Y g:i a') . "\n");
                    fclose($conn);
                }
//                socket_select()
               fclose($this->server);
            }

        }

    }
}

new SocketTcpServer();