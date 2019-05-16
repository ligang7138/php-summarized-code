<?php


namespace App\Corutine;


use Generator;
use SplQueue;

class Scheduler
{
    /**
     * @var SplQueue
     */
    protected $taskQueue;
    /**
     * @var int
     */
    protected $maxTaskId = 0;
    protected $taskMap = []; // taskId => task

    /**
     * Scheduler constructor.
     */
    public function __construct()
    {
        /* 原理就是维护了一个队列，
         * 前面说过，从编程角度上看，协程的思想本质上就是控制流的主动让出（yield）和恢复（resume）机制
         * */
        $this->taskQueue = new SplQueue();
    }

    /**
     * 增加一个任务
     *
     * @param Generator $coroutine
     * @return int
     */
    public function addTask(Generator $coroutine)
    {
        $tid = ++$this->maxTaskId;
        $task = new Task($tid, $coroutine);
        $this->taskMap[$tid] = $task;
        $this->schedule($task);
        return $tid;
    }

    /**
     * 把任务进入队列
     *
     * @param Task $task
     */
    public function schedule(Task $task)
    {
        $this->taskQueue->enqueue($task);
    }

    /**
     * 运行调度器
     * 多个任务是交替串行执行的
     */
    public function run()
    {
        while (!$this->taskQueue->isEmpty()) {//如果队列任务不为空，则不断出队任务，并执行
            // 任务出队
            $task = $this->taskQueue->dequeue();
            // 运行任务直到 yield
            $task->run();

            if ($task->isFinished()) {//判断当前任务是否执行结束了
                unset($this->taskMap[$task->getTaskId()]);
            } else {//还没执行结束的任务需要重新入队，以便下次调度
                $this->schedule($task);
            }
        }
    }
}