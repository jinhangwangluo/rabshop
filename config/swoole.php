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
    'http'       => [
        'enable'     => true,
        'host'       => '0.0.0.0',
        'port'       => 25780,
        'worker_num' => swoole_cpu_num(),
        'options'    => [
            'package_max_length' => 50 * 1024 * 1024,
        ],
    ],
    'websocket'  => [
        'enable'        => true,
        'handler'       => \think\swoole\websocket\Handler::class,
        'ping_interval' => 25000,
        'ping_timeout'  => 60000,
        'room'          => [
            'type'  => 'table',
            'table' => [
                'room_rows'   => 8192,
                'room_size'   => 2048,
                'client_rows' => 4096,
                'client_size' => 2048,
            ],
            'redis' => [
                'host'          => '127.0.0.1',
                'port'          => 6379,
                'max_active'    => 3,
                'max_wait_time' => 5,
            ],
        ],
        'listen'        => [],
        'subscribe'     => [],
    ],
    'rpc'        => [
        'server' => [
            'enable'     => false,
            'host'       => '0.0.0.0',
            'port'       => 9000,
            'worker_num' => swoole_cpu_num(),
            'services'   => [],
        ],
        'client' => [],
    ],
    //队列
    'queue'      => [
        'enable'      => true,
        'console_log' => [
            'enable' => env('QUEUE_CONSOLE_LOG', false),
        ],
        'workers'     => [
            env('QUEUE_NAME', 'manage') => [

            ],
            // 商品批量采集/上架专用队列（ProductBatchCollectJob::QUEUE_NAME），
            // 单条任务含外部采集调用，消费超时放宽到 300 秒，避免被默认 60 秒截断后重复消费
            \app\common\job\store\ProductBatchCollectJob::QUEUE_NAME => [
                'worker_num' => 1,
                'timeout'    => 300,
            ],
        ],
    ],
    'hot_update' => [
        'enable'  => env('APP_DEBUG', false),
        'name'    => ['*.php'],
        // plugins 为插件开发目录，改动需触发热重载，否则常驻进程持续执行旧代码。
        'include' => [app_path(), root_path() . 'rabbit', root_path() . 'plugins'],
        'exclude' => [],
    ],
    //连接池
    'pool'       => [
        'db'    => [
            'enable'        => true,
            'max_active'    => 10,
            'max_wait_time' => 5,
        ],
        'cache' => [
            'enable'        => true,
            'max_active'    => 10,
            'max_wait_time' => 5,
        ],
        //自定义连接池
    ],
    'ipc'        => [
        'type'  => 'unix_socket',
        'redis' => [
            'host'          => '127.0.0.1',
            'port'          => 6379,
            'max_active'    => 3,
            'max_wait_time' => 5,
        ],
    ],
    'tables'     => [],
    //每个worker里需要预加载以共用的实例
    'concretes'  => [],
    //重置器
    'resetters'  => [
        \app\common\resetter\ResetWebSocket::class,
    ],
    //每次请求前需要清空的实例
    'instances'  => [],
    //每次请求前需要重新执行的服务
    'services'   => [],
];
