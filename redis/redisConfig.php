<?php
/**
 * 项目 redis 统一配置文件;不能使用框架封装的redis
 * 一级节点下为每个 redis 集群配置,集群中必须包含一个名为 default 的节点
 */
return [
    'data_cache' => [
        'default' => [
            'host' => '192.168.141.150', //任选一个master节点
            'port' => '6379',
            'database' => 0,
            'password' => 'admin',
            'persistent' => 1, //持久化连接,此配置可提高redis 连接性能
            'timeout' => 5,
            'slot' => [0, 512], //自定义参数,非框架,用于自己分片配置占用槽点数 标识占用0-512的槽点(不包含512)
        ],
        'redis1' => [
            'host' => '192.168.141.150',
            'password' => 'admin',
            'port' => 6379,
            'persistent' => 1, //持久化连接,此配置可提高redis 连接性能
            'database' => 0,
            'timeout' => 10,
            'slot' => [512, 1024], //自定义参数,非框架,用于自己分片配置占用槽点数
        ],
    ]
];