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

// +----------------------------------------------------------------------
// | WebSocket 配置
// +----------------------------------------------------------------------

return [
    // +----------------------------------------------------------------------
    // | WebSocket 基本配置
    // +----------------------------------------------------------------------
    
    // 是否启用 WebSocket
    'enabled' => env('WEBSOCKET_ENABLED', true),
    
    // WebSocket 服务器地址（前端使用）
    'server_url' => env('WEBSOCKET_SERVER_URL', ''),
    
    // Token 过期时间（秒）
    'token_expire' => 86400, // 24 小时
    
    // +----------------------------------------------------------------------
    // | 限流保护配置
    // +----------------------------------------------------------------------
    
    'rate_limit' => [
        // 是否启用限流
        'enabled' => env('WEBSOCKET_RATE_LIMIT', true),
        
        // 全局限流：每秒最大推送次数
        'global_limit_per_second' => env('WEBSOCKET_GLOBAL_LIMIT', 1000),
        
        // 按事件类型限流（次/秒）
        'event_limits' => [
            'level_upgrade'    => env('WEBSOCKET_LEVEL_UPGRADE_LIMIT', 10),   // 升级广播
            'room_rank_update' => env('WEBSOCKET_RANK_UPDATE_LIMIT', 5),      // 榜单更新
            'chat_message'     => env('WEBSOCKET_CHAT_LIMIT', 100),           // 聊天消息
            'gift_message'     => env('WEBSOCKET_GIFT_LIMIT', 50),            // 礼物消息
            'system_broadcast' => env('WEBSOCKET_BROADCAST_LIMIT', 20),       // 系统广播
            'live_user_enter'  => env('WEBSOCKET_LIVE_USER_ENTER_LIMIT', 30), // 直播间进场提示
        ],
        
        // 按房间限流（次/秒）
        'room_limit_per_second' => env('WEBSOCKET_ROOM_LIMIT', 20),
        
        // 按用户限流（次/秒）
        'user_limit_per_second' => env('WEBSOCKET_USER_LIMIT', 10),
        
        // 限流时间窗口（秒）
        'window_size' => 1,
        
        // 超过限流后的处理：block=拒绝，queue=排队
        'overflow_strategy' => 'block',
    ],
    
    // +----------------------------------------------------------------------
    // | 消息持久化配置
    // +----------------------------------------------------------------------
    
    'persistence' => [
        // 是否启用消息持久化
        'enabled' => env('WEBSOCKET_PERSISTENCE', true),
        
        // 需要持久化的消息类型
        'important_events' => [
            'level_upgrade',     // 等级升级
            'system_broadcast',  // 系统广播
            'order_notification',// 订单通知
            'finance_alert',     // 财务提醒
        ],
        
        // 消息默认保留时间（天）
        'retention_days' => env('WEBSOCKET_MESSAGE_RETENTION', 30),
        
        // 自动清理过期消息（每日执行一次）
        'auto_cleanup' => true,
    ],
    
    // +----------------------------------------------------------------------
    // | 性能监控配置
    // +----------------------------------------------------------------------
    
    'monitor' => [
        // 是否启用性能监控
        'enabled' => env('WEBSOCKET_MONITOR', true),
        
        // 统计指标保留时间（天）
        'stats_retention_days' => 7,
        
        // 慢推送阈值（毫秒）
        'slow_threshold_ms' => 100,
        
        // 告警阈值
        'alert' => [
            // 成功率低于此值触发告警
            'success_rate_threshold' => 95.00,
            
            // 平均延迟高于此值触发告警
            'avg_latency_threshold_ms' => 200,
            
            // 失败数高于此值触发告警
            'failed_count_threshold' => 100,
        ],
    ],
    
    // +----------------------------------------------------------------------
    // | 连接管理配置
    // +----------------------------------------------------------------------
    
    'connection' => [
        // 连接超时时间（秒）
        'timeout' => 300, // 5 分钟
        
        // 心跳间隔（秒）
        'heartbeat_interval' => 30,
        
        // 最大重试次数
        'max_reconnect_attempts' => 3,
        
        // 重连间隔（秒）
        'reconnect_interval' => 5,
    ],
    
    // +----------------------------------------------------------------------
    // | 日志配置
    // +----------------------------------------------------------------------
    
    'log' => [
        // 是否记录推送日志
        'push_log' => env('WEBSOCKET_PUSH_LOG', false),
        
        // 是否记录错误日志
        'error_log' => env('WEBSOCKET_ERROR_LOG', true),
        
        // 是否记录性能日志
        'performance_log' => env('WEBSOCKET_PERFORMANCE_LOG', false),
    ],
];
