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
 * 定时任务定义。
 */
final class ScheduleItem
{
    public const TYPE_DELAY = 'delay';
    public const TYPE_INTERVAL = 'interval';
    public const TYPE_DAILY = 'daily';

    /**
     * @param string $code 任务编码。
     * @param string $name 任务名称。
     * @param string $type 任务类型。
     * @param int|null $seconds 延迟或间隔秒数。
     * @param string|null $time 每日执行时间，格式 HH:MM 或 HH:MM:SS。
     * @param bool $withoutOverlapping 是否防止重叠执行。
     * @param int $lockTtl 执行锁过期秒数。
     * @param Closure|null $callback 业务回调。
     * @param string|null $event 事件名称。
     * @param class-string|null $listener 监听器类名。
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $type,
        public ?int $seconds = null,
        public ?string $time = null,
        public bool $withoutOverlapping = true,
        public int $lockTtl = 600,
        public ?Closure $callback = null,
        public ?string $event = null,
        public ?string $listener = null
    ) {
    }

    /**
     * 创建延迟任务。
     *
     * @param string $code 任务编码。
     * @param string $name 任务名称。
     * @param int $seconds 延迟秒数。
     * @param int $lockTtl 执行锁过期秒数。
     * @return self
     */
    public static function delay(string $code, string $name, int $seconds, int $lockTtl = 600): self
    {
        return new self($code, $name, self::TYPE_DELAY, max(1, $seconds), null, true, $lockTtl);
    }

    /**
     * 创建间隔任务。
     *
     * @param string $code 任务编码。
     * @param string $name 任务名称。
     * @param int $seconds 间隔秒数。
     * @param int $lockTtl 执行锁过期秒数。
     * @return self
     */
    public static function interval(string $code, string $name, int $seconds, int $lockTtl = 600): self
    {
        return new self($code, $name, self::TYPE_INTERVAL, max(1, $seconds), null, true, $lockTtl);
    }

    /**
     * 创建每日任务。
     *
     * @param string $code 任务编码。
     * @param string $name 任务名称。
     * @param string $time 每日执行时间，格式 HH:MM 或 HH:MM:SS。
     * @param int $lockTtl 执行锁过期秒数。
     * @return self
     */
    public static function daily(string $code, string $name, string $time, int $lockTtl = 600): self
    {
        return new self($code, $name, self::TYPE_DAILY, null, $time, true, $lockTtl);
    }

    /**
     * 绑定事件和监听器。
     *
     * @param string $event 事件名称。
     * @param class-string $listener 监听器类名。
     * @return self
     */
    public function withEvent(string $event, string $listener): self
    {
        $this->event = $event;
        $this->listener = $listener;

        return $this;
    }

    /**
     * 从 Timer 注册数据创建任务定义。
     *
     * @param array<string, mixed> $task Timer 任务数据。
     * @return self
     */
    public static function fromTimerTask(array $task): self
    {
        $milliseconds = isset($task['milliseconds']) ? (int) $task['milliseconds'] : null;

        return new self(
            (string) $task['code'],
            (string) $task['name'],
            (string) $task['type'],
            $milliseconds === null ? null : max(1, (int) ceil($milliseconds / 1000)),
            isset($task['time']) ? (string) $task['time'] : null,
            true,
            (int) ($task['lock_ttl'] ?? 600),
            $task['callback'] instanceof Closure ? $task['callback'] : null
        );
    }
}
