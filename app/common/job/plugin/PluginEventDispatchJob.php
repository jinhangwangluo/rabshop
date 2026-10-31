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

namespace app\common\job\plugin;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\plugin\PluginRegistry;
use app\common\plugin\event\PluginEventBus;
use app\common\plugin\event\PluginEventEnvelope;
use app\common\service\plugin\PluginEventLogService;
use app\common\service\plugin\PluginEventOutboxService;
use think\facade\Log;

/**
 * Queue worker for one Outbox event.
 *
 * A row is claimed once, then fanned out into independent observer delivery
 * attempts. A failed observer never aborts another observer; only the failed
 * delivery is retried and eventually moved to dead_letter.
 */
class PluginEventDispatchJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    protected int $errorNum = 0;

    private ?PluginEventBus $bus;
    private ?PluginEventOutboxService $outbox;
    private ?PluginEventLogService $logs;
    private ?PluginRegistry $registry;

    /**
     * 构造事件投递任务。
     *
     * 队列反序列化时依赖通常为空，Worker 会从容器解析；测试或同步
     * 执行可注入同一组 Registry/Outbox/Log 实例以复用内存适配器。
     *
     * @param PluginEventBus|null $bus 事件总线。
     * @param PluginEventOutboxService|null $outbox Outbox 服务。
     * @param PluginEventLogService|null $logs 投递日志服务。
     * @param PluginRegistry|null $registry 插件注册表。
     */
    public function __construct(
        ?PluginEventBus $bus = null,
        ?PluginEventOutboxService $outbox = null,
        ?PluginEventLogService $logs = null,
        ?PluginRegistry $registry = null
    ) {
        $this->bus = $bus;
        $this->outbox = $outbox;
        $this->logs = $logs;
        $this->registry = $registry;
    }

    /**
     * Consume an Outbox row by numeric id or event_id.
     *
     * @param int|string|array<string, mixed> $identifier
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function handle(int|string|array $identifier, array $options = []): array
    {
        $outbox = $this->outbox();
        $logs = $this->logs();
        $bus = $this->bus();

        if (is_array($identifier)) {
            $identifier = (int)($identifier['id'] ?? $identifier['outbox_id'] ?? 0)
                ?: (string)($identifier['event_id'] ?? '');
        }
        if ($identifier === '' || $identifier === '0' || $identifier === 0) {
            return ['status' => 'ignored', 'reason' => 'missing_outbox_identifier'];
        }
        $row = $outbox->claim($identifier, $this->workerId($options['worker_id'] ?? null), $options);
        if ($row === null) {
            return ['status' => 'ignored', 'reason' => 'not_available'];
        }

        $snapshot = $outbox->getSnapshot($row);
        $event = $this->envelopeFromRow($row, $snapshot);
        $subscribers = is_array($snapshot['subscribers'] ?? null) ? $snapshot['subscribers'] : [];
        $forceReplay = (bool)($options['force_replay'] ?? false)
            || (($snapshot['replay_generation'] ?? '') !== '' && (int)($row['attempts'] ?? 0) <= 1);
        $context = [
            'merchant_id' => $event->getMerchantId(),
            'surface' => (string)($options['surface'] ?? 'backend'),
            'event_id' => $event->getEventId(),
        ];
        $maxAttempts = max(1, (int)($options['max_attempts'] ?? $options['maxAttempts'] ?? 3));
        $results = [];
        $retryDelay = 0;

        foreach ($subscribers as $descriptor) {
            if (!is_array($descriptor) || strtolower((string)($descriptor['type'] ?? 'observer')) !== 'observer') {
                continue;
            }
            $pluginCode = (string)($descriptor['plugin_code'] ?? '');
            $handler = (string)($descriptor['handler'] ?? '');
            if ($pluginCode === '' || $handler === '') {
                continue;
            }
            $deliveryKey = $logs->buildDeliveryKey($event->getEventId(), $pluginCode, $handler);
            $latestAttempt = $logs->latestAttempt($deliveryKey);
            $attempt = max(1, $latestAttempt + 1);

            // A terminal successful/skipped delivery is idempotently ignored.
            $latest = $latestAttempt > 0 ? $logs->findByDeliveryAttempt($deliveryKey, $latestAttempt) : null;
            if (!$forceReplay && is_array($latest) && in_array((string)($latest['status'] ?? ''), [
                PluginEventLogService::STATUS_SUCCEEDED,
                PluginEventLogService::STATUS_SKIPPED,
                PluginEventLogService::STATUS_SKIPPED_DISABLED,
            ], true)) {
                $results[] = [
                    'delivery_key' => $deliveryKey,
                    'plugin_code' => $pluginCode,
                    'handler' => $handler,
                    'attempt' => $latestAttempt,
                    'status' => (string)$latest['status'],
                    'duplicate' => true,
                ];
                continue;
            }

            $registry = $this->registry();
            if (!$registry->isHandlerAvailable($descriptor, $context, (bool)($options['require_version'] ?? false))) {
                $log = $logs->beginDelivery($event, array_merge($descriptor, ['delivery_key' => $deliveryKey]), $attempt, $options);
                if ((bool)($log['claimed'] ?? true)) {
                    $log = $logs->markSkipped($log, 'skipped_disabled');
                }
                $results[] = [
                    'delivery_key' => $deliveryKey,
                    'plugin_code' => $pluginCode,
                    'handler' => $handler,
                    'attempt' => $attempt,
                    'status' => PluginEventLogService::STATUS_SKIPPED_DISABLED,
                    'log' => $log,
                ];
                continue;
            }

            $result = $bus->dispatchDelivery($event, $descriptor, [
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'context' => $context,
                'retryable' => $options['retryable'] ?? true,
            ]);
            $results[] = array_merge([
                'delivery_key' => $deliveryKey,
                'plugin_code' => $pluginCode,
                'handler' => $handler,
            ], $result);
            if (($result['status'] ?? '') === PluginEventLogService::STATUS_RETRYING) {
                $retryDelay = max($retryDelay, $this->retryDelay($attempt));
            }
        }

        $statuses = array_map(static fn(array $result): string => (string)($result['status'] ?? ''), $results);
        $hasRetry = in_array(PluginEventLogService::STATUS_RETRYING, $statuses, true);
        $hasDeadLetter = (bool)array_intersect($statuses, [PluginEventLogService::STATUS_DEAD_LETTER, PluginEventLogService::STATUS_FAILED]);
        if ($hasRetry) {
            $outbox->release($row['id'] ?? $row['event_id'], $retryDelay, 'one or more plugin observers will retry');
            $this->scheduleRetry($row, $retryDelay);
            $aggregateStatus = PluginEventOutboxService::STATUS_PENDING;
        } elseif ($hasDeadLetter) {
            $outbox->markPartialFailed($row['id'] ?? $row['event_id'], [
                'last_error' => 'one or more plugin observer deliveries entered dead letter',
            ]);
            $aggregateStatus = PluginEventOutboxService::STATUS_PARTIAL_FAILED;
        } else {
            $outbox->markSucceeded($row['id'] ?? $row['event_id']);
            $aggregateStatus = PluginEventOutboxService::STATUS_SUCCEEDED;
        }

        return [
            'status' => $aggregateStatus,
            'event_id' => $event->getEventId(),
            'outbox_id' => (int)($row['id'] ?? 0),
            'deliveries' => $results,
            'retry_delay' => $retryDelay,
            'partial_failed' => $hasDeadLetter,
        ];
    }

    private function retryDelay(int $attempt): int
    {
        return min(300, max(1, 2 ** max(0, $attempt - 1)));
    }

    /**
     * Schedule a new queue invocation only for deliveries that can retry.
     *
     * @param array<string, mixed> $row
     */
    private function scheduleRetry(array $row, int $delay): void
    {
        try {
            $identifier = (int)($row['id'] ?? 0) ?: (string)$row['event_id'];
            self::dispatchSecs(max(1, $delay), $identifier);
        } catch (\Throwable $exception) {
            try {
                Log::warning('[PluginEventDispatchJob] retry scheduling failed: ' . $this->safeError($exception));
            } catch (\Throwable) {
            }
        }
    }

    private function workerId(?string $workerId): string
    {
        $workerId = trim((string)$workerId);
        if ($workerId === '') {
            $workerId = gethostname() . ':' . getmypid() . ':' . substr(hash('sha256', uniqid('', true)), 0, 12);
        }
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:-]/', '_', $workerId) ?: 'plugin-worker', 0, 64);
    }

    private function safeError(\Throwable $exception): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $exception->getMessage()) ?: '';
        return mb_substr(trim($message), 0, 300);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function envelopeFromRow(array $row, array $snapshot = []): PluginEventEnvelope
    {
        $payload = $row['payload_json'] ?? $row['payload'] ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        $actor = $row['actor_json'] ?? $row['actor'] ?? null;
        if (is_string($actor)) {
            $actor = json_decode($actor, true);
        }
        return PluginEventEnvelope::create((string)($row['name'] ?? ''), is_array($payload) ? $payload : [], [
            'event_id' => (string)($row['event_id'] ?? ''),
            'version' => (string)($row['event_version'] ?? $row['version'] ?? PluginEventEnvelope::DEFAULT_VERSION),
            'occurred_at' => (string)($row['occurred_at'] ?? $snapshot['occurred_at'] ?? $row['create_time'] ?? ''),
            'trace_id' => (string)($row['trace_id'] ?? ''),
            'actor' => is_array($actor) ? $actor : null,
            'merchant_id' => (int)($row['merchant_id'] ?? 0),
            'subject' => [
                'type' => (string)($row['subject_type'] ?? ''),
                'id' => (string)($row['subject_id'] ?? ''),
            ],
            'idempotency_key' => (string)($row['idempotency_key'] ?? $row['event_id'] ?? ''),
        ]);
    }

    private function bus(): PluginEventBus
    {
        if ($this->bus instanceof PluginEventBus) {
            return $this->bus;
        }
        try {
            if (function_exists('app')) {
                $bus = app()->make(PluginEventBus::class);
                if ($bus instanceof PluginEventBus) {
                    return $this->bus = $bus;
                }
            }
        } catch (\Throwable) {
        }
        return $this->bus = new PluginEventBus($this->registry(), $this->outbox(), $this->logs());
    }

    private function outbox(): PluginEventOutboxService
    {
        if ($this->outbox instanceof PluginEventOutboxService) {
            return $this->outbox;
        }
        try {
            if (function_exists('app')) {
                $service = app()->make(PluginEventOutboxService::class);
                if ($service instanceof PluginEventOutboxService) {
                    return $this->outbox = $service;
                }
            }
        } catch (\Throwable) {
        }
        return $this->outbox = new PluginEventOutboxService();
    }

    private function logs(): PluginEventLogService
    {
        if ($this->logs instanceof PluginEventLogService) {
            return $this->logs;
        }
        try {
            if (function_exists('app')) {
                $service = app()->make(PluginEventLogService::class);
                if ($service instanceof PluginEventLogService) {
                    return $this->logs = $service;
                }
            }
        } catch (\Throwable) {
        }
        return $this->logs = new PluginEventLogService();
    }

    private function registry(): PluginRegistry
    {
        if ($this->registry instanceof PluginRegistry) {
            return $this->registry;
        }
        try {
            if (function_exists('app')) {
                $registry = app()->make(PluginRegistry::class);
                if ($registry instanceof PluginRegistry) {
                    return $this->registry = $registry;
                }
            }
        } catch (\Throwable) {
        }
        return $this->registry = new PluginRegistry();
    }
}
