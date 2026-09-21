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
    // 默认磁盘
    'default' => env('filesystem.driver', 'public'),
    // 磁盘列表
    'disks' => [
        'local' => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type' => 'local',
            // 磁盘路径
            'root' => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url' => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        // 更多的磁盘配置信息
    ],
    'storage' => [
        'file_size' => 2097152,
        'file_ext' => ['jpg', 'jpeg', 'png', 'gif', 'pem', 'mp3', 'wma', 'zip', '7z', 'rar', 'gz', 'tar', 'wav', 'amr', 'mp4', 'key', 'xlsx', 'xls', 'video/mp4', 'webp'],
        'file_mime' => [
            'image/jpeg',
            'image/gif',
            'image/png',
            'image/webp',
            'text/plain',
            'audio/mpeg',
            'application/octet-stream',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-works',
            'application/vnd.ms-excel',
            'application/zip',
            'application/vnd.ms-excel',
            'application/vnd.ms-excel',
            'text/xml',
        ],
    ]
];
