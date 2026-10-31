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

/**
 * 插件内核配置。
 *
 * v1.2 固定使用数据库 Outbox。内存适配器只由事件服务的测试辅助方法显式启用，
 * 不作为生产配置项暴露。
 */
return [
    // 是否启用插件系统。关闭后插件注册、插件市场和插件生命周期能力均不可用。
    'enabled' => (bool)env('PLUGIN_SYSTEM_ENABLED', true),
    'event' => [
        // 事件存储固定使用数据库 Outbox，生产环境不通过环境变量切换存储方式。
        'storage' => 'database',
        // 是否严格处理事件投递失败；开启后失败会继续向上抛出。
        'strict' => (bool)env('PLUGIN_EVENT_STRICT', false),
        // 事件投递最大尝试次数，至少为 1 次。
        'max_attempts' => max(1, (int)env('PLUGIN_EVENT_MAX_ATTEMPTS', 3)),
        // 事件重试间隔，单位为秒，至少为 1 秒。
        'retry_delay_seconds' => max(1, (int)env('PLUGIN_EVENT_RETRY_DELAY', 5)),
        // 同步事件处理的最长等待时间，单位为毫秒，至少为 100 毫秒。
        'sync_timeout_ms' => max(100, (int)env('PLUGIN_EVENT_SYNC_TIMEOUT_MS', 2000)),
    ],
    'registry' => [
        // 插件清单目录，插件 manifest.json 默认从这里读取。
        'manifest_path' => root_path('plugins'),
        // 插件注册表缓存文件路径，插件清单变更后按 reload_required 策略刷新。
        'cache_path' => runtime_path('plugin_registry.php'),
        // 是否要求插件清单变更后重新加载注册表。
        'reload_required' => true,
    ],
    'lifecycle' => [
        // 仅信任管理员预先配置的发布者验签公钥。
        // 调用方自行提供的密钥、签名或未签名源码压缩包不能取得执行权限。
        'trusted_signing_keys' => array_filter([
            (string)env('PLUGIN_ARTIFACT_SIGNING_KEY_ID', 'official-artifact-v1') => (string)env('PLUGIN_ARTIFACT_SIGNING_PUBLIC_KEY', ''),
        ]),
    ],
    'security' => [
        // 插件加密请求协议版本，平台端和开放平台必须保持一致。
        'protocol_version' => '1.0',
        // 当前平台实例标识；为空时由插件密钥服务生成或读取。
        'instance_id' => (string)env('PLUGIN_INSTANCE_ID', ''),
        // 请求时间戳允许的最大偏差，单位为秒，用于防止过期请求重放。
        'timestamp_skew_seconds' => max(1, (int)env('PLUGIN_SECURITY_TIMESTAMP_SKEW', 300)),
        // nonce 防重放记录的有效期，单位为秒。
        'nonce_ttl_seconds' => max(1, (int)env('PLUGIN_SECURITY_NONCE_TTL', 300)),
        // 允许的 JWS 签名算法，必须与开放平台密钥配置一致。
        'allowed_jws_algorithm' => 'ES256',
        // 允许的 JWE 密钥加密算法，必须与开放平台密钥配置一致。
        'allowed_jwe_algorithm' => 'RSA-OAEP-256',
        // 允许的 JWE 内容加密算法，必须与开放平台密钥配置一致。
        'allowed_jwe_encryption' => 'A256GCM',
    ],
    'gateway' => [
        // 只允许 HTTPS，不能携带查询参数；只填域名时会自动补上 /openapi/secure-gateway。
        'endpoint' => 'https://www.rabshop.cn',
        // 传输失败时的总尝试次数；每次重试都会重新生成 nonce，写请求复用同一个幂等键。
        'retry_attempts' => max(1, min(3, (int)env('PLUGIN_GATEWAY_RETRY_ATTEMPTS', 2))),
        // 重试前的基础等待时间，单位为毫秒；按尝试次数指数退避，避免瞬时拥塞时连续冲击官网。
        'retry_delay_ms' => max(0, min(1000, (int)env('PLUGIN_GATEWAY_RETRY_DELAY_MS', 120))),
        // 官网公钥目录地址；为空时根据 endpoint 自动推导为 /openapi/security/public-key。
        'key_directory_endpoint' => '',
        // 开放平台密钥目录中的 owner_id，必须与开放平台登记的平台实例归属一致。
        'owner_id' => 'plugin-gateway',
        // 官网用于验签的本地请求签名 kid，必须与官网登记值一致。
        'request_signing_key_id' => 'instance-sign-v1',
        // 官网响应使用的本地实例加密 kid；本地密钥表不存在该稳定别名时回退到当前有效密钥。
        'instance_decryption_key_id' => 'instance-encrypt-v1',
    ],
    'build_service' => [
        // 地址里的路径会作为前缀保留（如 https://www.rabshop.cn/build-api），不能携带查询参数。
        'endpoint' => 'https://www.rabshop.cn',
        // 普通接口（目标探测、创建/查询任务、取消）超时，单位为秒。
        'timeout' => max(3, (int)env('PLUGIN_BUILD_TIMEOUT', 20)),
        // 上传源码包与下载产物的超时，单位为秒；同步等待构建时也要覆盖构建耗时。
        'long_timeout' => max(30, (int)env('PLUGIN_BUILD_LONG_TIMEOUT', 600)),
        // 单个源码包大小上限，单位为 MB，需要与服务端 UPLOAD_MAX_MB 保持一致。
        'upload_max_mb' => max(1, (int)env('PLUGIN_BUILD_UPLOAD_MAX_MB', 200)),
        // 单次产物下载大小上限，单位为 MB；超过上限明确报错，避免把 PHP 内存打满。
        'artifact_max_mb' => max(1, (int)env('PLUGIN_BUILD_ARTIFACT_MAX_MB', 64)),
        // 是否允许上传源码包打包；关闭后上传接口与带 source 的构建请求在本地直接拦截。
        'allow_source_upload' => (bool)env('PLUGIN_BUILD_ALLOW_SOURCE_UPLOAD', true),
    ],
];
