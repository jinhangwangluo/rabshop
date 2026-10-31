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

namespace app\common\service\plugin\contract;

/**
 * 生命周期事件发布端口。
 *
 * Service 只依赖此端口，事件 Outbox 的具体实现由插件事件内核提供。
 */
interface PluginLifecycleEventPublisherInterface
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function publish(
        string $eventName,
        array $payload,
        string $idempotencyKey,
        array $options = []
    ): array;
}
