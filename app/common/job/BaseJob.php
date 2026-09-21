<?php
// +----------------------------------------------------------------------
// | Rabbit多商户商城系统
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2026 https://www.rabshop.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Rabbit多商户商城系统不是自由软件，未经许可不能去掉Rabbit相关版权
// +----------------------------------------------------------------------
// | Author: Rabbit Team <admin@rabshop.cn>
// +----------------------------------------------------------------------

namespace app\common\job;

use think\facade\Log;
use think\queue\Job;

/**
 * 队列基类
 */
abstract class BaseJob
{
    /**
     * 错误次数
     * @var int
     */
    protected int $errorNum = 3;

    /**
     * 延迟时间
     * @var int
     */
    protected int $delay = 3;

    /**
     * @param string $name
     * @param array $arguments
     * @return void
     */
    public function __call(string $name, array $arguments)
    {
        try {
            $this->fire(...$arguments);
        } catch (\Throwable $e) {
            Log::error('队列投放错误：' . $e->getMessage() . '|' . json_encode(['line' => $e->getLine(), 'code' => $e->getCode()]));
        }
    }

    /**
     * @param Job $job
     * @param $arguments
     * @return void
     */
    public function fire(Job $job, $arguments): void
    {
        $jobStr = (string) $job->payload('job', static::class);
        $segments = explode('@', $jobStr);

        [, $action] = count($segments) > 1 ? $segments : [$segments[0], 'handle'];

        //从头部切割字符串
        $action = strpos($action, 'job') ? substr($action, strpos($action, 'job') + 1) : $action;
        $startedAt = microtime(true);

        $this->writeQueueConsoleLog('start', $job, $jobStr, $action);

        try {
            $this->runJob($action, $job, $arguments);

            $job->delete();

            $this->writeQueueConsoleLog('success', $job, $jobStr, $action, [
                'status' => 'deleted',
                'duration_ms' => $this->formatQueueDuration($startedAt),
            ]);
        } catch (\Throwable $e) {
            $status = 'deleted';
            $reason = 'no_retry';

            if (!$this->errorNum) {
                $job->delete();
            } else {
                if ($job->attempts() >= $this->errorNum) {
                    //删除任务
                    $job->delete();
                    $reason = 'max_attempts';
                } else {
                    //从新放入队列
                    $job->release();
                    $status = 'released';
                    $reason = 'retry';
                }
            }

            $this->writeQueueConsoleLog('failed', $job, $jobStr, $action, [
                'status' => $status,
                'reason' => $reason,
                'duration_ms' => $this->formatQueueDuration($startedAt),
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            $this->writeQueueConsoleLog($status === 'released' ? 'retry' : 'delete', $job, $jobStr, $action, [
                'reason' => $reason,
                'max_attempts' => $this->errorNum,
            ]);

            Log::error('队列错误：' . $e->getMessage() . '|line=' . $e->getLine() . '|file=' . $e->getFile() . '|tree=' . json_encode($e->getTrace()));
        }
    }

    /**
     * 执行队列
     * @param string $action
     * @param Job $job
     * @param array $arguments
     * @return mixed
     */
    protected function runJob(string $action, Job $job, array $arguments)
    {
        $action = method_exists($this, $action) ? $action : 'handle';
        if (!method_exists($this, $action)) {
            $job->delete();
        }

        return $this->{$action}(...$arguments);
    }

    /**
     * 输出队列消费控制台日志。
     *
     * @param string $event 事件名称
     * @param Job $job 队列任务
     * @param string $jobName 任务名称
     * @param string $action 执行动作
     * @param array $context 附加上下文
     * @return void
     */
    private function writeQueueConsoleLog(
        string $event,
        Job $job,
        string $jobName,
        string $action,
        array $context = []
    ): void {
        try {
            if (!$this->queueConsoleLogEnabled()) {
                return;
            }

            $context = array_merge([
                'job' => $jobName,
                'handler' => static::class,
                'action' => $action,
                'id' => $this->getQueueJobId($job),
                'attempts' => $this->getQueueAttempts($job),
                'pid' => getmypid() ?: 0,
            ], $context);

            $message = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '][queue][' . $event . '] ' . $message . PHP_EOL);
        } catch (\Throwable) {
        }
    }

    /**
     * 判断是否启用队列控制台日志。
     *
     * @return bool
     */
    private function queueConsoleLogEnabled(): bool
    {
        return PHP_SAPI === 'cli'
            && defined('STDOUT')
            && (bool) app()->config->get('swoole.queue.console_log.enable', false);
    }

    /**
     * 获取队列任务 ID。
     *
     * @param Job $job 队列任务
     * @return string
     */
    private function getQueueJobId(Job $job): string
    {
        try {
            return (string) $job->getJobId();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * 获取队列任务尝试次数。
     *
     * @param Job $job 队列任务
     * @return int
     */
    private function getQueueAttempts(Job $job): int
    {
        try {
            return (int) $job->attempts();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 格式化队列任务耗时。
     *
     * @param float $startedAt 开始时间
     * @return float
     */
    private function formatQueueDuration(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
