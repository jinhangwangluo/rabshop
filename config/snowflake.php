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

return [
    // 雪花算法配置
    'snowflake' => [
        // 数据中心ID (1-31)
        'datacenter_id' => env('SNOWFLAKE_DATACENTER_ID', 1),
        
        // 工作节点ID (1-31)
        // 可以设置为主机名的hash值，或手动指定
        'worker_id' => env('SNOWFLAKE_WORKER_ID', null),
        
        // 是否启用雪花算法
        'enabled' => env('SNOWFLAKE_ENABLED', true),
        
        // 分布式锁配置
        'lock' => [
            // 锁超时时间（毫秒）
            'timeout' => 100,
            // 重试间隔（微秒）
            'retry_interval' => 1000,
            // 最大重试次数
            'max_retries' => 3,
        ],
    ],
    
    // 订单号配置
    'order' => [
        // 订单号前缀映射
        'prefix' => [
            'PD' => '普通订单',
            'VIP' => 'VIP订单', 
            'REFUND' => '退款订单',
            'PAY' => '支付订单',
            'WITHDRAW' => '提现订单',
        ],
        
        // 订单号生成策略
        'strategy' => [
            // 是否记录生成日志
            'log_enabled' => true,
            // 日志级别
            'log_level' => 'info',
            // 超时时间（秒）
            'timeout' => 3,
        ],
    ],
];