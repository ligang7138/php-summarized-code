<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-2-11
 * Time: 上午11:47
 */

/*
 * int count ( mixed $var [, int $mode ] )  --  计算数组中的单元数目或对象中的属性个数,如果可选的 mode 参数设为 COUNT_RECURSIVE（或 1），count() 将递归地对数组计数。对计算多维数组的所有单元尤其有用。mode 的默认值是 0。count() 识别不了无限递归。
 * 如何判断数组是一维还是多维

*/

if(count($arr) == count($arr,COUNT_RECURSIVE)){
    echo '一维数组';
}else{
    echo '多维数组';
}