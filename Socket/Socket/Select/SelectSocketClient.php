<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-3-6
 * Time: 上午11:01
 */

function debug ($msg)
{
    error_log($msg, 3, './socket.log');
}
if (isset($argv[1]) && !empty($argv[1])) {

    $socket_client = stream_socket_client('tcp://127.0.0.1:3000', $errno, $errstr, 30);

//	stream_set_timeout($socket_client, 0, 100000);

    if (!$socket_client) {
        die("$errstr ($errno)");
    } else {
        $msg = trim($argv[1]);
        for ($i = 0; $i < 5; $i++) {
            $res = fwrite($socket_client, "$msg($i)\n");
            usleep(100000);
            debug(fread($socket_client, 1024)); // 将产生死锁，因为 fread 在阻塞模式下未读到数据时将等待
        }
        fwrite($socket_client, "quit\n"); // add end token
        debug(fread($socket_client, 1024));
        fclose($socket_client);
    }
}
else {

    $phArr = array();
    for ($i = 0; $i < 1; $i++) {
        $phArr[$i] = popen("php ".__FILE__." '{$i}:test'", 'r');
    }
    foreach ($phArr as $ph) {
        pclose($ph);
    }
}