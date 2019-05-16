<?php


namespace App\Corutine;


use Generator;

/**
 * Class Task
 * @package App\Corutine
 * 举个例子说明下协程的用处，例如现在我们的业务代码里需要调用（通过tcp、udp、http……）其他业务系统的服务，该调用会有一定的耗时。 如果使用PHP语言常规的LNMP模式，当客户端访问我们的业务代码时，能同时处理的请求数 = php-fpm开启的进程数。 如果使用PHP的协程（当然前提是要有一个功能完备且强大的调度器），那么同时处理的请求数 = php进程数 * 每个进程能开启的协程数。
 */
class Task
{
    protected $taskId;
    protected $coroutine;
    protected $beforeFirstYield = true;
    protected $sendValue;

    /**
     * Task constructor.
     * @param $taskId
     * @param Generator $coroutine
     */
    public function __construct($taskId, Generator $coroutine)
    {
        $this->taskId = $taskId;
        $this->coroutine = $coroutine;
    }

    /**
     * 获取当前的Task的ID
     *
     * @return mixed
     */
    public function getTaskId()
    {
        return $this->taskId;
    }

    /**
     * 判断Task执行完毕了没有
     *
     * @return bool
     */
    public function isFinished()
    {
        return !$this->coroutine->valid();
    }

    /**
     * 设置下次要传给协程的值，比如 $id = (yield $xxxx)，这个值就给了$id了
     *
     * @param $value
     */
    public function setSendValue($value)
    {
        $this->sendValue = $value;
    }

    /**
     * 运行任务
     *
     * @return mixed
     */
    public function run()
    {
        // 这里要注意，生成器的开始会reset，所以第一个值要用current获取
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;
            return $this->coroutine->current();
        } else {
            // 我们说过了，用send去调用一个生成器
            $retval = $this->coroutine->send($this->sendValue);
            $this->sendValue = null;
            return $retval;
        }
    }
}