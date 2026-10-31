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

use Swoole\Coroutine;
use Swoole\Timer as SwooleTimer;
use think\swoole\Manager;
use Throwable;

/**
 * Swoole 定时任务独立进程。
 */
final class SwooleScheduleProcess
{
    private bool $started = false;

    /**
     * @param Manager $manager Swoole 管理器。
     */
    public function __construct(private readonly Manager $manager)
    {
    }

    /**
     * 启动定时任务循环。
     *
     * @param int $tickMs 默认检查间隔毫秒。
     * @return void
     */
    public function start(int $tickMs = 1000): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        /** @var ScheduleManager $manager */
        $manager = app()->make(ScheduleManager::class);
        $items = $manager->all();
        trace('swoole schedule process started, task count: ' . count($items), 'info');

        foreach ($items as $item) {
            $this->registerTimer($item, max(1000, $tickMs));
        }
    }

    /**
     * 为单个任务注册独立 Timer。
     *
     * @param ScheduleItem $item 任务定义。
     * @param int $tickMs 默认检查间隔毫秒。
     * @return void
     */
    private function registerTimer(ScheduleItem $item, int $tickMs): void
    {
        if ($item->type === ScheduleItem::TYPE_DELAY) {
            SwooleTimer::after(max(1, (int) $item->seconds) * 1000, fn () => $this->run($item));
            return;
        }

        $intervalMs = $item->type === ScheduleItem::TYPE_INTERVAL
            ? max(1, (int) $item->seconds) * 1000
            : $tickMs;

        SwooleTimer::tick($intervalMs, fn () => $this->run($item));
    }

    /**
     * 执行指定任务。
     *
     * @param ScheduleItem $item 任务定义。
     * @return void
     */
    private function run(ScheduleItem $item): void
    {
        $cid = Coroutine::create(function () use ($item): void {
            $this->manager->runWithBarrier([$this->manager, 'runInSandbox'], function () use ($item): void {
                app()->make(ScheduleRunner::class)->runTask($item);
            });
        });

        if ($cid === false) {
            trace('schedule timer failed: ' . $item->code . ' coroutine create failed', 'error');
        }
    }
}
