<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 19-3-22
 * Time: 下午1:33
 */
//使用生成器读取文件，第一次读取了第一行，第二次读取了第二行，以此类推，每次被加载到内存中的文字只有一行，大大的减小了内存的使用。这样，即使读取上G的文本也不用担心，完全可以像读取很小文件一样编写代码
header("content-type:text/html;charset=utf-8");
function readTxt()
{
    # code...
    $handle = fopen("./test.txt", 'rb');

    while (feof($handle)===false) {
        # code...
        yield fgets($handle);
    }

    fclose($handle);
}

foreach (readTxt() as $key => $value) {
    # code...
    echo $value.PHP_EOL;
}

$numbers = array('nike' => 200, 'jordan' => 500, 'adiads' => 800);

//通常方法，如果是百万级别的访问量，这种方法会占用极大内存
function rand_weight($numbers)
{
    $total = 0;
    foreach ($numbers as $number => $weight) {
        $total += $weight;
        $distribution[$number] = $total;
    }
    $rand = mt_rand(0, $total-1);

    foreach ($distribution as $num => $weight) {
        if ($rand < $weight) return $num;
    }
}

//改用yield生成器
function mt_rand_weight($numbers) {
    $total = 0;
    foreach ($numbers as $number => $weight) {
        $total += $weight;
        yield $number => $total;
    }
}

function mt_rand_generator($numbers)
{
    $total = array_sum($numbers);
    $rand = mt_rand(0, $total -1);
    foreach (mt_rand_weight($numbers) as $num => $weight) {
        if ($rand < $weight) return $num;
    }
}

echo mt_rand_generator($numbers);