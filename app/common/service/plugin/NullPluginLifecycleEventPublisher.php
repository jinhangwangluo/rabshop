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

namespace app\common\service\plugin;

use app\common\service\plugin\contract\PluginLifecycleEventPublisherInterface;

/**
 * 仅用于契约测试的显式 Noop 发布器。
 */
final class NullPluginLifecycleEventPublisher implements PluginLifecycleEventPublisherInterface
{
    /** @var list<array<string,mixed>> */
    private array $events = [];

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
    ): array {
        $eventId = sprintf(
            'noop-%s',
            substr(hash('sha256', $eventName . '|' . $idempotencyKey), 0, 32)
        );
        $event = [
            'event_id' => $eventId,
            'name' => $eventName,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'options' => $options,
        ];
        $this->events[] = $event;
        return $event;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function events(): array
    {
        return $this->events;
    }
}
