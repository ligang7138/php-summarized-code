<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-3-4
 * Time: 上午9:05
 */

class SocketUdpServer
{
    private $server = null;
    private $config = [
        'uri' => 'udp://127.0.0.1:8888',// udp协议
//        'uri' => 'tcp://127.0.0.1:8888',// tcp协议
//        'port' => 8888,
    ];
    public function __construct($config = [])
    {
        if(is_null($this->server)){
            if(!empty($confit)) $this->config = array_merge($this->config,$config);

//            注: 当指定数字的 IPv6 地址（例如 fe80::1）时必须将 IP 地址放在方括号内。例如 tcp://[fe80::1]:80
//            $this->server = stream_socket_server("udp://127.0.0.1:1113", $errno, $errstr, STREAM_SERVER_BIND);
            $this->server = stream_socket_server($this->config['uri'], $errno, $errstr, STREAM_SERVER_BIND);

            // udp协议的示例
            do {
                $pkt = stream_socket_recvfrom($this->server, 1, 0, $peer);
                echo "$peer\n";
                stream_socket_sendto($this->server, date("D M j H:i:s Y\r\n"), 0, $peer);
            } while ($pkt !== false);
        }

    }
}

new SocketUdpServer();