<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-3-6
 * Time: 上午11:00
 */

$server=new \Swoole\Server("0.0.0.0",9801);
$server->on('receive',function (...$arv){
    print_r($arv);

    fwrite($arv[1],'receive'.posix_getpid());
});

$server->start();
print_r($server);
die;
set_time_limit(0);
class SelectSocketServer
{
    private static $socket;
    private static $timeout = 60;
    private static $maxconns = 1024;
    private static $connections = array();
    function __construct($port)
    {
        global $errno, $errstr;
        if ($port < 1024) {
            die("Port must be a number which bigger than 1024\n");
        }

        $socket = socket_create_listen($port);
        if (!$socket) die("Listen $port failed");

        socket_set_nonblock($socket); // 非阻塞
        while (true)
        {
            $readfds = array_merge(self::$connections, array($socket));
            $writefds = array();
            // 选择一个连接，获取读、写连接通道
            $e = NULL;

            /*
             * socket_select是阻塞，有数据请求才处理，否则一直阻塞
             * 此处$readfds会读取到当前活动的连接
             * 比如执行socket_select前的数据如下(描述socket的资源ID)：
             * $socket = Resource id #4
             * $readfds = Array
             *       (
             *           [0] => Resource id #5 //客户端1
             *           [1] => Resource id #4 //server绑定的端口的socket资源
             *       )
             * 调用socket_select之后，此时有两种情况：
             * 情况一：如果是新客户端2连接，那么 $readfds = array([1] => Resource id #4),此时用于接收新客户端2连接
             * 情况二：如果是客户端1(Resource id #5)发送消息，那么$readfds = array([1] => Resource id #5)，用户接收客户端1的数据
             *
             * 通过以上的描述可以看出，socket_select有两个作用，这也是实现了IO复用
             * 1、新客户端来了，通过 Resource id #4 介绍新连接，如情况一
             * 2、已有连接发送数据，那么实时切换到当前连接，接收数据，如情况二
            */
            print_r($readfds);
            if (socket_select($readfds, $writefds, $e, self::$timeout))
            {
                // 如果是当前服务端的监听连接
                if (in_array($socket, $readfds)) {
                    echo "socket_accept\n";
                    // 接受客户端连接
                    $newconn = socket_accept($socket);
                    $i = (int) $newconn;
                    $reject = '';
                    if (count(self::$connections) >= self::$maxconns) {
                        $reject = "Server full, Try again later.\n";
                    }
                    // 将当前客户端连接放入 socket_select 选择
                    self::$connections[$i] = $newconn;
                    // 输入的连接资源缓存容器
                    $writefds[$i] = $newconn;
                    // 连接不正常
                    if ($reject) {
                        socket_write($writefds[$i], $reject);
                        unset($writefds[$i]);
                        self::close($i);
                    } else {
                        echo "Client $i come.\n";
                    }
                    // remove the listening socket from the clients-with-data array
                    $key = array_search($socket, $readfds);
                    unset($readfds[$key]);
                }
                // 轮循读通道
                foreach ($readfds as $rfd) {
                    // 客户端连接
                    $i = (int) $rfd;
                    // 从通道读取
                    $line = @socket_read($rfd, 2048, PHP_NORMAL_READ);
                    if ($line === false) {
                        // 读取不到内容，结束连接
                        echo "Connection closed on socket $i.\n";
                        self::close($i);
                        continue;
                    }
                    $tmp = substr($line, -1);
                    if ($tmp != "\r" && $tmp != "\n") {
                        // 等待更多数据
                        continue;
                    }
                    // 处理逻辑
                    $line = trim($line);
                    if ($line == "quit") {
                        echo "Client $i quit.\n";
                        self::close($i);
                        break;
                    }
                    if ($line) {
                        echo "Client $i >>" . $line . "\n";
                        //发送客户端
                        socket_write($rfd,  "$i=>$line\n");
                    }
                }

                // 轮循写通道
                foreach ($writefds as $wfd) {
                    $i = (int) $wfd;
                    socket_write($wfd, "Welcome Client $i!\n");
                }
            }
        }
    }

    function close ($i)
    {
        socket_shutdown(self::$connections[$i]);
        socket_close(self::$connections[$i]);
        unset(self::$connections[$i]);
    }
}
new SelectSocketServer(3000);