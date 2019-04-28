<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-2-11
 * Time: 下午5:16
 */

// 唯一标识符 $length 大于1，小于32，eg:7
echo substr(hash('md5',uniqid('',true)),0 ,$length);
//echo substr(hash('md5', uniqid('', true)), 0, $length);

/**
 * aes对称加/解密
 * @param string $data 待加密串或待解密串
 * @param boolean $is_crypt 是否加密 默认为加密,否则为解密
 * @param string $key 加密Key值
 * @return string
 */
public function aesCrypt($data = '', $is_crypt = true, $key = '')
{
    $key = empty($key) ? substr(md5(123456), 2, 16) : $key;
    $privateKey = $iv = $key;
    $iv = '';
    if ($is_crypt) {
        //加密
        $encrypted = openssl_encrypt($data, 'AES-128-ECB', $privateKey, OPENSSL_RAW_DATA, $iv);
        return str_replace('+', '-', str_replace('/', '_', str_replace('=', '', base64_encode($encrypted))));
    } else {
        //解密
        $data = str_replace('-', '+', str_replace('_', '/', $data));
        $decrypted = openssl_decrypt(base64_decode($data), 'AES-128-ECB', $privateKey, OPENSSL_RAW_DATA, $iv);
        return trim($decrypted);
    }
}