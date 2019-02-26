<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-2-11
 * Time: 下午5:16
 */

// 唯一标识符 $length 大于1，小于32，eg:7
echo substr(hash('md5', uniqid('', true)), 0, $length);