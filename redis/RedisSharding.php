<?php


/**
 * 实现分片对PHPredis的重新封装类
 * 如果使用分片方式,获取实例传入的配置去获取 节点的配置信息生成 Sharding 信息, 节点中必须包含一个名为 default 的节点配置
 * 总节点数最好是1024 整除的数
 * @author yvan.xian <yvan.xian@idreamsky.com>
 * @date 2017-10-26
 */
class RedisSharding extends BaseRepository{

    /**
     * 当前对象实例
     * @var type
     */
    private static $instance = null;

    /**
     * 设置当前实例的redis操作是否使用长连接 1 是 0 否
     * @var type
     */
    private $isPersistent = 1;

    /**
     * 实例分片哈希槽
     * @var type
     */
    private $shardingHashSlot = [];

    /**
     * 分片 哈希槽数
     * @var type
     */
    private $slotCount = 1024;

    /**
     *底层 redis 对象
     * @var type
     */
    private $redis = null;

    /**
     * 当前操作使用的redis配置名称
     * @var type
     */
    private $thisNodeName = '';

    /**
     * 分片使用的实例数组
     * @var type
     */
    private $redisConfigs = [];

    /**
     * 使用的 redis 配置项目(集群的根配置路径)
     * @var type
     */
    private $useConfig = '';

    private function __construct() {
        //加载 redis 的配置文件 2018-01-22 by yvan
        App()->configure('redis');
        //默认使用 redis.data_cache
        $this->useConfig = 'redis.data_cache';
        $this->redisConfigs = config($this->useConfig, []);
    }

    /**
     * 获取当前实例,调用方法和 \Redis 一致
     * @param int $isPersistent 是否使用持久化连接,默认使用 (cli 模式下不使用)
     * @param string $useConfig 使用的配置根路径 database.redis 使用 laravel 框架配置获取的格式
     * @return RedisSharding
     */
    public static function getInstance($isPersistent = 1, $useConfig = 'database.redis'){
        if(is_null(self::$instance) || !isset(self::$instance)){
            //使用扩展方式连接redis
            self::$instance = new self();
        }

        //如果配置和当前实例使用的配置不一致,重新计算分片信息
        if($useConfig != self::$instance->useConfig){
            self::$instance->useConfig = $useConfig;
            self::$instance->redisConfigs = config(self::$instance->useConfig, []);
            //预先生成实例主机的哈希槽数据
            self::$instance->generateShardingHashSlot();
        }

        self::$instance->isPersistent = $isPersistent;
        return self::$instance;
    }

    /**
     *
     * @param type $name 方法名称
     * @param type $arguments 参数列表
     */
    public function __call($name, $arguments) {

        //操作完 关闭redis,因为这里肯定是每次操作都连接
        $key = isset($arguments[0]) ? $arguments[0] : '';
        $redis = $this->getConnect($key);
//        $method
        $result = call_user_func_array([$redis, $name], $arguments);

        $redis->close();

        return $result;
    }

    /**
     * 获取当前操作的 key 请求使用的节点配置名称
     * @return string 实例对应的配置的名称
     */
    public function getThisNodeName(){
        return $this->thisNodeName;
    }

    /**
     * 测试算法 当前的key 落入的节点
     * @param type $key 要测试的 key
     */
    public function getKeyFallNodeName($key){
        $this->getConnectConfig($key);

        return $this->thisNodeName;
    }

    /**
     * 根据操作的redis key 计算获取对应的实例
     * @param type $key 要操作的 key
     * @return type \Redis
     */
    private function getConnect($key){
        $redisConfig = $this->getConnectConfig($key);
        $timeout = isset($redisConfig['timeout']) ? $redisConfig['timeout'] : 5;
        $this->redis  = new \Redis();

        //增加支持有密码验证
        if($this->isPersistent){
            $ret = $this->redis->pconnect($redisConfig['host'], $redisConfig['port'], $timeout);
        }else{
            $ret = $this->redis->connect($redisConfig['host'], $redisConfig['port'], $timeout);
        }

        if($ret){
            $this->auth($this->redis, $redisConfig['password']);
        }

        return $this->redis;
    }

    /**
     * 获取需要连接的配置信息
     * @param type $key 操作的 key
     * @return array 当前操作的 key 所落入的实例的连接信息数组
     */
    private function getConnectConfig($key){
        //通过CR32把 key 转换成int
        $crcid = abs(crc32($key));
        $position = $crcid % $this->slotCount;

        //如果没找到对应槽点的主机则使用默认的
        $redisConfigNodeName = isset($this->shardingHashSlot[$position]) ? $this->shardingHashSlot[$position] : 'default';
        $this->thisNodeName = $redisConfigNodeName;
        $redisConfig = isset($this->redisConfigs[$redisConfigNodeName]) ? $this->redisConfigs[$redisConfigNodeName] : [];

        return $redisConfig;
    }

    /**
     * 根据配置的redis实例连接生成主机的分片信息集合
     * 目前不能指定配置,如果使用分片方式,会获取 database 下redis 节点配置的所有节点进行 sharding 运算
     * 运算之后只会保存实例对应的配置名称,不保存具体的实例配置。
     */
    private function generateShardingHashSlot(){
        /**
         * 获取所有的redis实例配置项
         */
        $redisConfigs = $this->redisConfigs;
        //过滤掉 cluster
        unset($redisConfigs['cluster']);

        //判断 default 节点是否存在
        if(!isset($redisConfigs['default'])){
            throw new \Exception(' Not Found Redis default configure');
        }

        $this->shardingHashSlot = [];
        foreach ($redisConfigs as $key => $redisConfig){
            if(!isset($redisConfig['slot'])){
                throw new \Exception(' Please configure ' . $key .' slot ');
            }
            $slot = $redisConfig['slot'];
            $min = isset($slot[0]) ? $slot[0] : 0;
            $max = isset($slot[1]) ? $slot[1] : 0;
            if($min > $max || $max == 0){
                throw new \Exception($key .' slot Must max > min ');
            }

            for($i = $min;$i < $max;$i++){
                $this->shardingHashSlot[$i] = $key;
            }

        }


    }

    /**
     * 设置密码鉴权
     * @param \Redis $redis Redis 实例对象
     * @param string $password auth 的密码
     * @return boolean
     */
    private function auth($redis, $password){
        if($password){
            return $redis->auth($password);
        }

        return true;
    }


}