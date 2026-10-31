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

use app\common\exception\PluginLifecycleException;
use app\common\service\plugin\contract\PluginLifecycleEventPublisherInterface;
use Throwable;

/**
 * 将生命周期事件适配到插件事件内核。
 */
class PluginEventBusPublisher implements PluginLifecycleEventPublisherInterface
{
    /**
     * @param object|null $eventBus 可注入 Fake，生产环境由容器解析 PluginEventBus。
     * @param bool $strict 事件内核不可用时是否抛错。
     */
    public function __construct(
        private readonly ?object $eventBus = null,
        private readonly bool $strict = true
    ) {
    }

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
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $eventName)) {
            throw new PluginLifecycleException('事件名称无效', 'INVALID_EVENT_NAME');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $idempotencyKey)) {
            throw new PluginLifecycleException('事件幂等键无效', 'INVALID_EVENT_IDEMPOTENCY_KEY');
        }

        $bus = $this->eventBus;
        if ($bus === null && function_exists('app')) {
            try {
                $bus = app()->make('app\\common\\plugin\\event\\PluginEventBus');
            } catch (Throwable $exception) {
                if ($this->strict) {
                    throw new PluginLifecycleException(
                        '插件事件内核不可用',
                        'EVENT_BUS_UNAVAILABLE',
                        0,
                        $exception
                    );
                }
            }
        }

        if (!is_object($bus) || !method_exists($bus, 'publish')) {
            if ($this->strict) {
                throw new PluginLifecycleException('插件事件内核不可用', 'EVENT_BUS_UNAVAILABLE');
            }
            return [];
        }

        $busOptions = array_merge([
            'idempotency_key' => $idempotencyKey,
            'mode' => 'observer',
        ], $options);

        try {
            $result = $bus->publish($eventName, $payload, $busOptions);
        } catch (Throwable $exception) {
            throw new PluginLifecycleException(
                '插件事件发布失败',
                'EVENT_PUBLISH_FAILED',
                0,
                $exception
            );
        }

        if (is_array($result)) {
            return $result;
        }

        // 兼容早期事件内核只返回 Envelope 对象的实现。
        if (is_object($result) && method_exists($result, 'toArray')) {
            $array = $result->toArray();
            return is_array($array) ? $array : ['event_id' => (string)($result->eventId ?? '')];
        }

        return ['event_id' => is_scalar($result) ? (string)$result : ''];
    }
}
