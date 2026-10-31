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

namespace Rabbit\service;

use think\facade\Cache;

/**
 * 雪花算法订单号生成服务（Redis 原子序列版）
 *
 * 使用 Redis HINCRBY 原子操作管理毫秒级序列，保证多进程/Swoole 协程
 * 并发场景下订单号全局唯一，不依赖进程内存状态。
 *
 * Class SnowflakeOrderService
 * @package Rabbit\service
 */
class SnowflakeOrderService
{
    /**
     * 起始时间戳（毫秒）：2020-01-01 00:00:00 UTC
     */
    private const TWEPOCH = 1577836800000;

    /**
     * 机器ID位数
     */
    private const WORKER_ID_BITS = 5;

    /**
     * 数据中心ID位数
     */
    private const DATACENTER_ID_BITS = 5;

    /**
     * 最大机器ID（31）
     */
    private const MAX_WORKER_ID = (-1 ^ (-1 << self::WORKER_ID_BITS));

    /**
     * 最大数据中心ID（31）
     */
    private const MAX_DATACENTER_ID = (-1 ^ (-1 << self::DATACENTER_ID_BITS));

    /**
     * 序列号位数
     */
    private const SEQUENCE_BITS = 12;

    /**
     * 序列号掩码（4095）
     */
    private const SEQUENCE_MASK = (-1 ^ (-1 << self::SEQUENCE_BITS));

    /**
     * 机器ID左移位数（12）
     */
    private const WORKER_ID_SHIFT = self::SEQUENCE_BITS;

    /**
     * 数据中心ID左移位数（17）
     */
    private const DATACENTER_ID_SHIFT = self::SEQUENCE_BITS + self::WORKER_ID_BITS;

    /**
     * 时间戳左移位数（22）
     */
    private const TIMESTAMP_LEFT_SHIFT = self::SEQUENCE_BITS + self::WORKER_ID_BITS + self::DATACENTER_ID_BITS;

    /**
     * Redis 中 Hash 键的前缀
     */
    private const REDIS_KEY_PREFIX = 'snowflake:seq:';

    /**
     * 工作机器ID (0~31)
     */
    private int $workerId;

    /**
     * 数据中心ID (0~31)
     */
    private int $datacenterId;

    /**
     * Redis 连接句柄
     * @var mixed|\Redis
     */
    private mixed $redis;

    /**
     * SnowflakeOrderService constructor.
     *
     * @param int $datacenterId 数据中心ID (0~31)
     * @param int $workerId     工作机器ID (0~31)
     */
    public function __construct(int $datacenterId = 1, int $workerId = 1)
    {
        if ($workerId > self::MAX_WORKER_ID || $workerId < 0) {
            $workerId = abs($workerId) % (self::MAX_WORKER_ID + 1);
        }

        if ($datacenterId > self::MAX_DATACENTER_ID || $datacenterId < 0) {
            $datacenterId = abs($datacenterId) % (self::MAX_DATACENTER_ID + 1);
        }

        $this->workerId = $workerId;
        $this->datacenterId = $datacenterId;
        $this->redis = Cache::store('redis')->handler();
    }

    /**
     * 生成带前缀的订单号
     *
     * @param string $prefix 订单前缀
     * @return string
     */
    public function generateOrderId(string $prefix = 'PD'): string
    {
        return $prefix . $this->nextId();
    }

    /**
     * 获取下一个唯一ID（雪花算法核心，序列号由 Redis 原子自增保证）
     *
     * @return string
     * @throws \RuntimeException
     */
    public function nextId(): string
    {
        $timestamp = $this->timeGen();
        $sequence = $this->nextSequence($timestamp);

        // 序列溢出：等到下一毫秒，并用新时间戳重置序列
        if ($sequence > self::SEQUENCE_MASK) {
            $timestamp = $this->tilNextMillis($timestamp);
            $sequence = $this->resetSequence($timestamp);
        }

        // 移位拼装 64 位 ID
        $id = (($timestamp - self::TWEPOCH) << self::TIMESTAMP_LEFT_SHIFT)
            | ($this->datacenterId << self::DATACENTER_ID_SHIFT)
            | ($this->workerId << self::WORKER_ID_SHIFT)
            | ($sequence & self::SEQUENCE_MASK);

        return (string)$id;
    }

    /**
     * 通过 Redis HINCRBY 原子自增获取当前毫秒内的序列号（从 0 开始）
     *
     * Hash 结构：key = snowflake:seq:{timestamp}
     *            field = {datacenterId}:{workerId}
     * 每个毫秒 key 在 2 秒后自动过期，避免 key 堆积。
     *
     * @param int $timestamp 当前毫秒时间戳
     * @return int 原子自增后的序列值（第一次调用返回 1）
     */
    private function nextSequence(int $timestamp): int
    {
        $key = self::REDIS_KEY_PREFIX . $timestamp;
        $field = $this->datacenterId . ':' . $this->workerId;

        // HINCRBY 是原子操作，多进程/协程安全
        $seq = (int)$this->redis->hIncrBy($key, $field, 1);

        // 仅在首次创建 key 时设置过期（避免重复调用 EXPIRE 增加开销）
        if ($seq === 1) {
            $this->redis->expire($key, 2);
        }

        // 返回从 0 开始的序列（自增从 1 起，减 1 对齐雪花标准）
        return $seq - 1;
    }

    /**
     * 进入新毫秒后重置序列（从 0 开始重新计数）
     *
     * @param int $timestamp 新的毫秒时间戳
     * @return int 始终返回 0
     */
    private function resetSequence(int $timestamp): int
    {
        $key = self::REDIS_KEY_PREFIX . $timestamp;
        $field = $this->datacenterId . ':' . $this->workerId;

        $this->redis->hSet($key, $field, 1);
        $this->redis->expire($key, 2);

        return 0;
    }

    /**
     * 阻塞等待直到获得比 $lastTimestamp 更大的时间戳
     *
     * @param int $lastTimestamp 上一次使用的毫秒时间戳
     * @return int 新的毫秒时间戳
     */
    private function tilNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->timeGen();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->timeGen();
        }
        return $timestamp;
    }

    /**
     * 返回当前毫秒时间戳
     *
     * @return int
     */
    private function timeGen(): int
    {
        return (int)(microtime(true) * 1000);
    }
}
