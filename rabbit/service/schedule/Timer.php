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

declare(strict_types=1);

namespace Rabbit\service\schedule;

use Closure;

/**
 * 定时器服务。
 */
final class Timer
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $tasks = [];

    /**
     * 兼容旧调用，当前 Timer 只负责收集任务。
     *
     * @param bool $autoStart 是否自动启动。
     * @return self
     */
    public function setAutoStart(bool $autoStart): self
    {
        return $this;
    }

    /**
     * 清空已注册任务。
     *
     * @return self
     */
    public function clear(): self
    {
        $this->tasks = [];

        return $this;
    }

    /**
     * 获取全部任务定义。
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->tasks;
    }

    /**
     * 获取指定任务定义。
     *
     * @param string $code 任务编码。
     * @return array<string, mixed>|null
     */
    public function get(string $code): ?array
    {
        return $this->tasks[$code] ?? null;
    }

    /**
     * 注册循环定时器。
     *
     * @param int $milliseconds 执行间隔毫秒。
     * @param Closure $callback 业务回调。
     * @param string|null $code 任务编码。
     * @param string|null $name 任务名称。
     * @param int $lockTtl 执行锁过期秒数。
     * @return void
     */
    public function tick(int $milliseconds, Closure $callback, ?string $code = null, ?string $name = null, int $lockTtl = 600): void
    {
        $code = $this->code($code, $callback);
        if (isset($this->tasks[$code])) {
            return;
        }

        $this->tasks[$code] = [
            'code' => $code,
            'name' => $name ?: $code,
            'type' => ScheduleItem::TYPE_INTERVAL,
            'milliseconds' => max(1, $milliseconds),
            'callback' => $callback,
            'lock_ttl' => $lockTtl,
        ];
    }

    /**
     * 注册延迟定时器。
     *
     * @param int $milliseconds 延迟毫秒。
     * @param Closure $callback 业务回调。
     * @param string|null $code 任务编码。
     * @param string|null $name 任务名称。
     * @param int $lockTtl 执行锁过期秒数。
     * @return void
     */
    public function after(int $milliseconds, Closure $callback, ?string $code = null, ?string $name = null, int $lockTtl = 600): void
    {
        $code = $this->code($code, $callback);
        if (isset($this->tasks[$code])) {
            return;
        }

        $this->tasks[$code] = [
            'code' => $code,
            'name' => $name ?: $code,
            'type' => ScheduleItem::TYPE_DELAY,
            'milliseconds' => max(1, $milliseconds),
            'callback' => $callback,
            'lock_ttl' => $lockTtl,
        ];
    }

    /**
     * 注册每日定时器。
     *
     * @param string $time 每日执行时间，格式 HH:MM 或 HH:MM:SS。
     * @param Closure $callback 业务回调。
     * @param string|null $code 任务编码。
     * @param string|null $name 任务名称。
     * @param int $lockTtl 执行锁过期秒数。
     * @return void
     */
    public function daily(string $time, Closure $callback, ?string $code = null, ?string $name = null, int $lockTtl = 600): void
    {
        $code = $this->code($code, $callback);
        if (isset($this->tasks[$code])) {
            return;
        }

        $this->tasks[$code] = [
            'code' => $code,
            'name' => $name ?: $code,
            'type' => ScheduleItem::TYPE_DAILY,
            'time' => $time,
            'callback' => $callback,
            'lock_ttl' => $lockTtl,
        ];
    }

    /**
     * 生成任务编码。
     *
     * @param string|null $code 指定编码。
     * @param Closure $callback 业务回调。
     * @return string
     */
    private function code(?string $code, Closure $callback): string
    {
        return $code ?: 'timer_' . spl_object_id($callback);
    }

}
