<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-3-4
 * Time: 上午9:31
 */

/*class SocketUdpClient
{

}*/

$fp = stream_socket_client("udp://127.0.0.1:8888", $errno, $errstr, 30);

if (!$fp) {
    echo "ERROR: $errno - $errstr<br />\n";
} else {
    fwrite($fp, "\n");
    echo fread($fp, 26);
    fclose($fp);
}