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

use Psr\Log\LoggerInterface;
use Rabbit\util\Lock;
use think\facade\Cache;
use Throwable;

/**
 * 定时任务执行器。
 */
final class ScheduleRunner
{
    /**
     * @param ScheduleManager $manager 任务注册中心。
     * @param LoggerInterface $logger 日志服务。
     */
    public function __construct(
        private readonly ScheduleManager $manager,
        private readonly LoggerInterface $logger,
        private readonly Lock $lock
    ) {
    }

    /**
     * 执行到期任务。
     *
     * @param string|null $code 指定任务编码，空则检查全部任务。
     * @param bool $force 是否强制执行，忽略到期判断。
     * @return array<int, array<string, mixed>>
     */
    public function runDue(?string $code = null, bool $force = false): array
    {
        if ($code !== null) {
            return [$this->runOne($code, $force)];
        }

        $items = $this->manager->all();
        if ($items === []) {
            return [[
                'code' => '',
                'status' => 'not_found',
                'message' => '任务不存在',
            ]];
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = $this->runTask($item, $force);
        }

        return $result;
    }

    /**
     * 按编码执行单个任务。
     *
     * @param string $code 任务编码。
     * @param bool $force 是否强制执行，忽略到期判断。
     * @return array<string, mixed>
     */
    public function runOne(string $code, bool $force = false): array
    {
        $item = $this->manager->get($code);
        if (!$item) {
            return [
                'code' => $code,
                'status' => 'not_found',
                'message' => '任务不存在',
            ];
        }

        return $this->runTask($item, $force);
    }

    /**
     * 执行单个任务。
     *
     * @param ScheduleItem $item 任务定义。
     * @param bool $force 是否强制执行，忽略到期判断。
     * @return array<string, mixed>
     */
    public function runTask(ScheduleItem $item, bool $force = false): array
    {
        $startedAt = microtime(true);
        if (!$force && !$this->isDue($item)) {
            return $this->buildResult($item, 'skipped', '任务未到执行时间', $startedAt);
        }

        $lockValue = null;
        if ($item->withoutOverlapping) {
            $lockValue = $this->acquireLock($item);
        }

        if ($item->withoutOverlapping && $lockValue === null) {
            return $this->buildResult($item, 'locked', '任务正在执行中', $startedAt);
        }

        $this->logger->info('schedule task started', ['code' => $item->code]);

        try {
            if (!$item->callback) {
                throw new \RuntimeException('schedule callback is empty: ' . $item->code);
            }

            ($item->callback)();
            $this->markRan($item);
            $result = $this->buildResult($item, 'success', '执行成功', $startedAt);
            $this->logger->info('schedule task success', ['code' => $item->code, 'duration_ms' => $result['duration_ms']]);

            return $result;
        } catch (Throwable $e) {
            $result = $this->buildResult($item, 'failed', $e->getMessage(), $startedAt);
            $this->logger->error('schedule task failed', [
                'code' => $item->code,
                'message' => $e->getMessage(),
                'duration_ms' => $result['duration_ms'],
            ]);

            return $result;
        } finally {
            if ($item->withoutOverlapping && $lockValue !== null) {
                try {
                    $this->lock->unlock($this->lockKey($item), $lockValue);
                } catch (Throwable $e) {
                    $this->logger->warning('schedule task lock release failed', [
                        'code' => $item->code,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * 判断任务是否到期。
     *
     * @param ScheduleItem $item 任务定义。
     * @return bool
     */
    private function isDue(ScheduleItem $item): bool
    {
        $lastRanAt = (int) Cache::get($this->lastRanKey($item), 0);
        $now = time();

        return match ($item->type) {
            ScheduleItem::TYPE_DELAY => $lastRanAt === 0 && $now >= $this->firstSeenAt($item) + (int) $item->seconds,
            ScheduleItem::TYPE_INTERVAL => $lastRanAt === 0 || $now >= $lastRanAt + (int) $item->seconds,
            ScheduleItem::TYPE_DAILY => $this->isDailyDue($item, $lastRanAt, $now),
            default => false,
        };
    }

    /**
     * 判断每日任务是否到期。
     *
     * @param ScheduleItem $item 任务定义。
     * @param int $lastRanAt 上次执行时间戳。
     * @param int $now 当前时间戳。
     * @return bool
     */
    private function isDailyDue(ScheduleItem $item, int $lastRanAt, int $now): bool
    {
        $dueAt = strtotime(date('Y-m-d ', $now) . (string) $item->time);
        if ($dueAt === false || $now < $dueAt) {
            return false;
        }

        return date('Y-m-d', $lastRanAt) !== date('Y-m-d', $now);
    }

    /**
     * 获取任务首次被调度器发现时间。
     *
     * @param ScheduleItem $item 任务定义。
     * @return int
     */
    private function firstSeenAt(ScheduleItem $item): int
    {
        $key = 'schedule:first_seen_at:' . $item->code;
        $firstSeenAt = (int) Cache::get($key, 0);
        if ($firstSeenAt <= 0) {
            $firstSeenAt = time();
            Cache::set($key, $firstSeenAt, 86400 * 30);
        }

        return $firstSeenAt;
    }

    /**
     * 获取任务锁。
     *
     * @param ScheduleItem $item 任务定义。
     * @return string|null
     */
    private function acquireLock(ScheduleItem $item): ?string
    {
        $value = bin2hex(random_bytes(16));

        return $this->lock->tryLock($this->lockKey($item), $value, $item->lockTtl) ? $value : null;
    }

    /**
     * 标记任务已执行。
     *
     * @param ScheduleItem $item 任务定义。
     * @return void
     */
    private function markRan(ScheduleItem $item): void
    {
        Cache::set($this->lastRanKey($item), time(), 86400 * 30);
    }

    /**
     * 构造执行结果。
     *
     * @param ScheduleItem $item 任务定义。
     * @param string $status 执行状态。
     * @param string $message 状态说明。
     * @param float $startedAt 开始时间。
     * @return array<string, mixed>
     */
    private function buildResult(ScheduleItem $item, string $status, string $message, float $startedAt): array
    {
        return [
            'code' => $item->code,
            'name' => $item->name,
            'type' => $item->type,
            'status' => $status,
            'message' => $message,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * 获取执行锁缓存键。
     *
     * @param ScheduleItem $item 任务定义。
     * @return string
     */
    private function lockKey(ScheduleItem $item): string
    {
        return 'schedule:lock:' . $item->code;
    }

    /**
     * 获取上次执行时间缓存键。
     *
     * @param ScheduleItem $item 任务定义。
     * @return string
     */
    private function lastRanKey(ScheduleItem $item): string
    {
        return 'schedule:last_ran_at:' . $item->code;
    }
}
