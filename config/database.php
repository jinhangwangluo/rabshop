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
    // 默认使用的数据库连接配置
    'default'         => env('database.driver', 'mysql'),

    // 自定义时间查询规则
    'time_query_rule' => [],

    // 自动写入时间戳字段
    // true为自动识别类型 false关闭
    // 字符串则明确指定时间字段类型 支持 int timestamp datetime date
    'auto_timestamp'  => true,

    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',

    // 时间字段配置 配置格式：create_time,update_time
    'datetime_field'  => '',

    // 数据库连接配置信息
    'connections'     => [
        'mysql' => [
            // 数据库类型
            'type'            => env('DATABASE_TYPE', 'mysql'),
            // 服务器地址
            'hostname'        => env('DATABASE_HOSTNAME', '127.0.0.1'),
            // 数据库名
            'database'        => env('DATABASE_DATABASE', ''),
            // 用户名
            'username'        => env('DATABASE_USERNAME', 'root'),
            // 密码
            'password'        => env('DATABASE_PASSWORD', ''),
            // 端口
            'hostport'        => env('DATABASE_HOSTPORT', '3306'),
            // 数据库连接参数
            'params'          => [],
            // 数据库编码默认采用utf8
            'charset'         => env('DATABASE_CHARSET', 'utf8mb4'),
            // 数据库表前缀
            'prefix'          => env('DATABASE_PREFIX', 'lf_'),

            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'          => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate'     => false,
            // 读写分离后 主服务器数量
            'master_num'      => 1,
            // 指定从服务器序号
            'slave_no'        => '',
            // 是否严格检查字段是否存在
            'fields_strict'   => true,
            // 远端 MySQL 可能主动断开空闲或网络抖动连接，启用断线重连
            'break_reconnect' => true,
            // 监听SQL
            'trigger_sql'     => env('app_debug', true),
            // 开启字段缓存
            'fields_cache'    => false,
        ],

        // 更多的数据库配置信息
    ],

    //数据分页配置
    'pages' => [
        //页码key
        'page_key'      => 'page',
        //每页截取key
        'limit_key'     => 'limit',
        //每页截取最大值
        'limit_max'     => 100,
        //默认条数
        'default_limit' => 10,
    ]
];
