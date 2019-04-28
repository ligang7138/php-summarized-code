<?php
/**
 * swoole进程池 多进程消费任务 常驻内存
 * 方法1： while（true）
 * 方法2： go 协程 （问题：协程执行完后进程销毁重启）
 */

$workerNum = 4;
$process = new Swoole\Process\Pool($workerNum);

$process->on('workerStart',function($pool,$work_id){
    echo "Worker#{$work_id} is started\n";
    $key = "key1";

//    $cli = new Redis();
    // 方法二
        /*go(function() use($key){
//        echo $key.PHP_EOL;
            while(true) {
                $cli = new \Swoole\Coroutine\Redis();
                $cli->connect('172.19.0.3', 6379);
                sleep(1);
                $cli->setDefer();
                $cli->brpop($key, 2);
                $msgs = $cli->recv();
//            sleep($msgs[1]);
                $msgs['cli'] = $cli;
                var_dump($msgs);
            }
        });*/



    // 方法一
    while(true){
        $cli = new Redis();
        $cli->pconnect('172.19.0.3', 6379);
        sleep(1);
        $msgs = $cli->brpop($key, 2);
        if ( $msgs == null) continue;
//        echo $work_id.PHP_EOL;
        $msgs['w_id'] = $work_id;
        print_r($msgs);
    }
});

$process->on('workerStop',function($pool,$work_id){
    echo $work_id.'结束了';
});

$process->start();