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

use app\common\plugin\event\PluginEventBus;
use app\common\plugin\event\PluginEventException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 业务 Service 使用的插件事件门面。
 *
 * 业务层不需要知道 Registry、Outbox 或队列的具体实现。观察者事件默认
 * 允许在内核暂不可用时记录并继续核心流程；同步 guard/filter 始终把
 * 内核异常抛给调用方，确保不会绕过插件校验。
 */
final class PluginBusinessEventService
{
    public function __construct(
        private readonly ?PluginEventBus $eventBus = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * 发布观察者事件。
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function publish(string $eventName, array $payload = [], array $options = []): array
    {
        $strict = array_key_exists('strict', $options)
            ? (bool)$options['strict']
            : $this->strictFromConfig();
        unset($options['strict']);
        $options['mode'] = (string)($options['mode'] ?? 'observer');

        try {
            $bus = $this->resolveBus();
            $result = $bus->publish($eventName, $payload, $options);
            return is_array($result) ? $result : ['event_id' => ''];
        } catch (Throwable $exception) {
            $this->log($eventName, $exception);
            if ($strict) {
                throw $exception;
            }
            return [
                'event_id' => '',
                'name' => $eventName,
                'status' => 'unavailable',
                'degraded' => true,
            ];
        }
    }

    /**
     * 同步执行 guard/filter。任何拒绝、异常或超时都会向上抛出。
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function dispatchSync(string $eventName, array $payload = [], array $options = []): array
    {
        $bus = $this->resolveBus();
        $result = $bus->dispatchSync($eventName, $payload, $options);
        return is_array($result) ? $result : ['allowed' => true, 'payload' => $payload];
    }

    /**
     * 同步执行插件报价处理器。
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function dispatchCalculation(string $eventName, array $payload = [], array $options = []): array
    {
        $bus = $this->resolveBus();
        $result = $bus->dispatchCalculation($eventName, $payload, $options);
        return is_array($result) ? $result : ['allowed' => true, 'quotes' => []];
    }

    /**
     * 在当前数据库事务中执行插件业务处理器。
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function dispatchTransactional(string $eventName, array $payload = [], array $options = []): array
    {
        $bus = $this->resolveBus();
        $result = $bus->dispatchTransactional($eventName, $payload, $options);
        return is_array($result) ? $result : ['allowed' => true, 'results' => []];
    }

    /**
     * 生成业务事件幂等键，避免不同入口重复发布相同事实。
     */
    public function idempotencyKey(string $eventName, string|int $subjectId, mixed $version = ''): string
    {
        $seed = implode('|', [$eventName, (string)$subjectId, (string)$version]);
        return 'business:' . substr(hash('sha256', $seed), 0, 56);
    }

    private function resolveBus(): PluginEventBus
    {
        if ($this->eventBus !== null) {
            return $this->eventBus;
        }
        if (!function_exists('app')) {
            throw new PluginEventException('插件事件内核不可用');
        }
        $bus = app()->make(PluginEventBus::class);
        if (!$bus instanceof PluginEventBus) {
            throw new PluginEventException('插件事件内核不可用');
        }
        return $bus;
    }

    private function strictFromConfig(): bool
    {
        try {
            return (bool)config('plugin.event.strict', false);
        } catch (Throwable) {
            return false;
        }
    }

    private function log(string $eventName, Throwable $exception): void
    {
        try {
            if ($this->logger !== null) {
                $this->logger->warning('plugin event degraded', [
                    'event' => $eventName,
                    'error_code' => $exception::class,
                ]);
                return;
            }
            if (function_exists('logger')) {
                logger()->warning('plugin event degraded', [
                    'event' => $eventName,
                    'error_code' => $exception::class,
                ]);
            }
        } catch (Throwable) {
            // 事件降级不能再次影响核心业务。
        }
    }
}
