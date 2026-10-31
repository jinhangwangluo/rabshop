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
// | CORS 跨域配置
// +----------------------------------------------------------------------
return [
    // 允许的来源。支持精确域名和通配符写法，例如：
    // - ['https://admin.example.com']
    // - ['https://*.example.com']
    // - ['*']
    'allow_origins' => ['*'],

    // 允许的请求方法
    'allow_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // 允许的请求头
    'allow_headers' => [
        'Client-Userid',
        'x-token',
        'Authorization',
        'Authori-zation',
        'Content-Type',
        'If-Match',
        'If-Modified-Since',
        'If-None-Match',
        'If-Unmodified-Since',
        'X-Requested-With',
        'Form-type',
        'X-Terminal-Type',
        'Origin',
        'Accept',
        // 插件安全网关的 JWE 请求元数据必须通过浏览器预检。
        'X-Plugin-Protocol-Version',
        'X-Plugin-Request-Id',
        // 智能体访客会话标识，H5/iframe 嵌入页跨域调用时需通过预检。
        'X-Ai-Agent-Guest'
    ],

    // 允许前端读取的响应头
    'expose_headers' => ['Server', 'X-New-Token'],

    // 是否允许携带凭证
    'allow_credentials' => true,

    // 预检请求缓存时间（秒）
    'max_age' => 1728000,
];
