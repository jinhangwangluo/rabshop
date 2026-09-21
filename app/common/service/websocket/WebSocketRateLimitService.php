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

namespace app\common\service\websocket;

use think\facade\Cache;

/**
 * WebSocket限流保护服务
 * 
 * 防止短时间大量推送导致系统拥塞
 * 支持按事件类型、目标房间、用户维度进行限流
 */
class WebSocketRateLimitService
{
    /**
     * Redis Key 前缀
     */
    const CACHE_PREFIX = 'ws_rate_limit:';
    
    /**
     * 默认配置
     */
    protected array $defaultConfig = [
        // 是否启用限流
        'enabled' => true,
        
        // 全局限流：每秒最大推送次数
        'global_limit_per_second' => 1000,
        
        // 按事件类型限流（次/秒）
        'event_limits' => [
            'level_upgrade'    => 10,   // 升级广播：10 次/秒
            'room_rank_update' => 5,    // 榜单更新：5 次/秒
            'chat_message'     => 100,  // 聊天消息：100 次/秒
            'gift_message'     => 50,   // 礼物消息：50 次/秒
        ],
        
        // 按房间限流（次/秒）
        'room_limit_per_second' => 20,
        
        // 按用户限流（次/秒）
        'user_limit_per_second' => 10,
        
        // 限流时间窗口（秒）
        'window_size' => 1,
        
        // 超过限流后的处理：block=拒绝，queue=排队
        'overflow_strategy' => 'block',
    ];
    
    /**
     * 当前配置
     */
    protected array $config = [];
    
    public function __construct()
    {
        // 从配置文件加载配置
        $this->config = config('websocket.rate_limit', $this->defaultConfig);
    }
    
    /**
     * 检查是否可以推送
     * @param string $eventType
     * @param int|null $roomId
     * @param int|null $userId
     * @return array [allowed: bool, reason: string, retry_after: int]
     */
    public function check(string $eventType, ?int $roomId = null, ?int $userId = null): array
    {
        // 如果未启用限流，直接允许
        if (!$this->config['enabled']) {
            return ['allowed' => true, 'reason' => '', 'retry_after' => 0];
        }
        
        // 1. 检查全局限流
        $globalResult = $this->checkGlobalLimit();
        if (!$globalResult['allowed']) {
            return $globalResult;
        }
        
        // 2. 检查事件类型限流
        $eventResult = $this->checkEventLimit($eventType);
        if (!$eventResult['allowed']) {
            return $eventResult;
        }
        
        // 3. 检查房间限流
        if ($roomId) {
            $roomResult = $this->checkRoomLimit($roomId);
            if (!$roomResult['allowed']) {
                return $roomResult;
            }
        }
        
        // 4. 检查用户限流
        if ($userId) {
            $userResult = $this->checkUserLimit($userId);
            if (!$userResult['allowed']) {
                return $userResult;
            }
        }
        
        // 全部通过
        return ['allowed' => true, 'reason' => '', 'retry_after' => 0];
    }
    
    /**
     * 检查全局限流
     * @return array
     */
    protected function checkGlobalLimit(): array
    {
        $limit = $this->config['global_limit_per_second'];
        $key = self::CACHE_PREFIX . 'global';
        
        return $this->checkRateLimit($key, $limit);
    }
    
    /**
     * 检查事件类型限流
     * @param string $eventType
     * @return array
     */
    protected function checkEventLimit(string $eventType): array
    {
        $limits = $this->config['event_limits'] ?? [];
        $limit = $limits[$eventType] ?? 100; // 默认 100 次/秒
        
        $key = self::CACHE_PREFIX . 'event:' . $eventType;
        
        return $this->checkRateLimit($key, $limit);
    }
    
    /**
     * 检查房间限流
     * @param int $roomId
     * @return array
     */
    protected function checkRoomLimit(int $roomId): array
    {
        $limit = $this->config['room_limit_per_second'] ?? 20;
        $key = self::CACHE_PREFIX . 'room:' . $roomId;
        
        return $this->checkRateLimit($key, $limit);
    }
    
    /**
     * 检查用户限流
     * @param int $userId
     * @return array
     */
    protected function checkUserLimit(int $userId): array
    {
        $limit = $this->config['user_limit_per_second'] ?? 10;
        $key = self::CACHE_PREFIX . 'user:' . $userId;
        
        return $this->checkRateLimit($key, $limit);
    }
    
    /**
     * 通用限流检查（使用 Redis INCR 实现滑动窗口）
     * @param string $key
     * @param int $limit
     * @return array
     */
    protected function checkRateLimit(string $key, int $limit): array
    {
        $redis = Cache::store('redis')->handler();
        $windowSize = $this->config['window_size'] ?? 1;
        
        // 生成带时间窗口的 key
        $timeWindowKey = $key . ':' . time();
        
        // 原子递增计数
        $count = $redis->incr($timeWindowKey);
        
        if ($count === 1) {
            // 第一次访问，设置过期时间
            $redis->expire($timeWindowKey, $windowSize + 1);
        }
        
        if ($count > $limit) {
            // 超过限流
            return [
                'allowed'     => false,
                'reason'      => 'rate_limit_exceeded',
                'retry_after' => $windowSize, // 建议等待时间
                'current_count' => $count,
                'limit'       => $limit
            ];
        }
        
        return [
            'allowed'       => true,
            'reason'        => '',
            'retry_after'   => 0,
            'current_count' => $count,
            'limit'         => $limit
        ];
    }
    
    /**
     * 记录推送结果（用于统计）
     * @param string $eventType
     * @param bool $success
     * @param int $latencyMs
     */
    public function recordPushResult(string $eventType, bool $success, int $latencyMs): void
    {
        $redis = Cache::store('redis')->handler();
        
        // 记录成功/失败数
        $statsKey = self::CACHE_PREFIX . 'stats:' . date('YmdH');
        if ($success) {
            $redis->hIncrBy($statsKey, 'success', 1);
        } else {
            $redis->hIncrBy($statsKey, 'failed', 1);
        }
        
        // 记录延迟统计
        $latencyKey = self::CACHE_PREFIX . 'latency:' . $eventType;
        $redis->lPush($latencyKey, $latencyMs);
        $redis->lTrim($latencyKey, 0, 999); // 保留最近 1000 次
        
        // 设置过期时间（1 小时）
        $redis->expire($statsKey, 3600);
        $redis->expire($latencyKey, 3600);
    }
    
    /**
     * 获取当前限流状态
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'enabled'           => $this->config['enabled'],
            'global_limit'      => $this->config['global_limit_per_second'],
            'event_limits'      => $this->config['event_limits'] ?? [],
            'room_limit'        => $this->config['room_limit_per_second'],
            'user_limit'        => $this->config['user_limit_per_second'],
            'window_size'       => $this->config['window_size'],
            'overflow_strategy' => $this->config['overflow_strategy'],
        ];
    }
}
