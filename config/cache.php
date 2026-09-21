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
// | 缓存设置
// +----------------------------------------------------------------------

use think\facade\Env;

return [
    // 默认缓存驱动
    'default' => env('cache.driver', 'redis'),

    // 缓存连接方式配置
    'stores' => [
        'file' => [
            // 驱动方式
            'type'          => 'File',
            // 缓存保存目录
            'path'          => '',
            // 缓存前缀
            'prefix'        => '',
            // 缓存有效期 0表示永久缓存
            'expire'        => 0,
            // 缓存标签前缀
            'tag_prefix'    => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'     => [],
        ],
        // 更多的缓存连接
        // redis缓存
        'redis' => [
            // 驱动方式
            'type'          => 'redis',
            // 服务器地址
            'host'          => env('REDIS_HOSTNAME', '127.0.0.1'),
            // 端口
            'port'          => env('REDIS_PORT', '6379'),
            // 密码
            'password'      => env('REDIS_PASSWORD', ''),
            // 缓存有效期 0表示永久缓存
            'expire'        => 3600,
            // 缓存前缀
            'prefix'        => env('REDIS_PREFIX', 'manage'),
            // 缓存标签前缀
            'tag_prefix'    => env('REDIS_TAG_PREFIX', 'manage_tag_'),
            // 数据库 0号数据库
            'select'        => intval(env('REDIS_SELECT', 0)),
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'     => [],
            // 服务端主动关闭
            'timeout'       => 0
        ],
    ],
];
