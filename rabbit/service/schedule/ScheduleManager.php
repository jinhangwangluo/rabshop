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

use think\facade\Cache;

/**
 * 定时任务注册中心。
 */
final class ScheduleManager
{
    private const EVENT_NAME = 'schedule';

    /**
     * 获取全部任务。
     *
     * @return array<string, ScheduleItem>
     */
    public function all(): array
    {
        $result = [];

        /** @var Timer $timer */
        $timer = app('timer');
        $timer->setAutoStart(false)->clear();
        app()->instance('timer', $timer);
        event(self::EVENT_NAME);

        foreach ($timer->all() as $task) {
            $item = ScheduleItem::fromTimerTask($task);
            $this->markFirstSeen($item);
            $result[$item->code] = $item;
        }

        return $result;
    }

    /**
     * 根据编码获取任务。
     *
     * @param string $code 任务编码。
     * @return ScheduleItem|null
     */
    public function get(string $code): ?ScheduleItem
    {
        return $this->all()[$code] ?? null;
    }

    /**
     * 标记任务首次被调度器发现时间。
     *
     * @param ScheduleItem $item 任务定义。
     * @return void
     */
    private function markFirstSeen(ScheduleItem $item): void
    {
        if ($item->type !== ScheduleItem::TYPE_DELAY) {
            return;
        }

        $key = 'schedule:first_seen_at:' . $item->code;
        if ((int) Cache::get($key, 0) <= 0) {
            Cache::set($key, time(), 86400 * 30);
        }
    }
}
