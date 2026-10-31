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

use app\common\model\plugin\PluginEventLog;
use app\common\plugin\event\PluginEventEnvelope;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\ModelNotFoundException;
use think\facade\Log;

/**
 * 单次投递执行日志、重试台账和 dead-letter 状态服务。
 */
class PluginEventLogService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD_LETTER = 'dead_letter';
    public const STATUS_SKIPPED_DISABLED = 'skipped_disabled';
    public const STATUS_SKIPPED = 'skipped';

    private const LOCK_TTL_SECONDS = 300;

    /** @var array<int, array<string, mixed>> */
    private array $memoryRows = [];
    private int $memorySequence = 0;
    private ?bool $memoryStore = null;
    private ?bool $instanceMemoryStore = null;

    /**
     * @return class-string<PluginEventLog>
     */
    protected function setModel(): string
    {
        return PluginEventLog::class;
    }

    /**
     * 强制当前服务实例使用内存日志适配器。
     *
     * @param bool $enabled 是否绕过数据库模型。
     * @return self
     */
    public function useMemoryStore(bool $enabled = true): self
    {
        $this->instanceMemoryStore = $enabled;
        $this->memoryStore = $enabled;
        return $this;
    }

    /**
     * 返回当前实例内存仓中的日志行，不读取数据库。
     *
     * @return array<int, array<string, mixed>>
     */
    public function memoryRows(): array
    {
        return array_values($this->memoryRows);
    }

    /**
     * 清空当前实例的内存适配器，并在下一次调用时重新启用。
     *
     * @return self
     */
    public function resetMemory(): self
    {
        $this->memoryRows = [];
        $this->memorySequence = 0;
        $this->memoryStore = true;
        $this->instanceMemoryStore = true;
        return $this;
    }

    /**
     * 开始一次投递尝试。调用用户代码前先插入一条日志。
     *
     * 另一 worker 如果拿到相同的 delivery_key + attempt，会得到
     * `claimed=false`，必须保持这次投递不变。
     *
     * @param PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $delivery
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function beginDelivery(
        PluginEventEnvelope|array $event,
        array $delivery,
        int $attempt = 1,
        array $options = []
    ): array {
        $envelope = $event instanceof PluginEventEnvelope ? $event : PluginEventEnvelope::fromArray($event);
        $pluginCode = trim((string)($delivery['plugin_code'] ?? $delivery['pluginCode'] ?? ''));
        $handler = trim((string)($delivery['handler'] ?? ''));
        if ($pluginCode === '' || $handler === '') {
            throw new \InvalidArgumentException('Delivery requires plugin_code and handler');
        }
        $attempt = max(1, $attempt);
        $deliveryKey = trim((string)($delivery['delivery_key'] ?? $delivery['deliveryKey'] ?? ''));
        if ($deliveryKey === '') {
            $deliveryKey = $this->buildDeliveryKey($envelope->getEventId(), $pluginCode, $handler);
        }
        $idempotencyKey = trim((string)($delivery['idempotency_key'] ?? $delivery['idempotencyKey'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = $this->buildIdempotencyKey($envelope->getIdempotencyKey(), $pluginCode, $handler);
        }
        $existing = $this->findByDeliveryAttempt($deliveryKey, $attempt);
        if ($existing !== null) {
            $existing['claimed'] = $this->canReclaim($existing, $options);
            if (!$existing['claimed']) {
                $existing['duplicate'] = true;
                return $existing;
            }
            $changes = [
                'status' => self::STATUS_PROCESSING,
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => null,
                'error_code' => '',
                'error_message' => '',
            ];
            return $this->updateLog($existing, $changes) + ['claimed' => true, 'duplicate' => false];
        }

        $row = [
            'event_id' => $envelope->getEventId(),
            'delivery_key' => mb_substr($deliveryKey, 0, 190),
            'plugin_code' => mb_substr($pluginCode, 0, 64),
            'handler' => mb_substr($handler, 0, 190),
            'attempt' => $attempt,
            'status' => self::STATUS_PROCESSING,
            'duration_ms' => 0,
            'idempotency_key' => mb_substr($idempotencyKey, 0, 128),
            'payload_hash' => $this->payloadHash($envelope),
            'error_code' => '',
            'error_message' => '',
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => null,
            'create_time' => date('Y-m-d H:i:s'),
        ];
        if ($this->shouldUseMemory()) {
            $stored = $this->memoryInsert($row);
        } else {
            try {
                /** @var PluginEventLog $model */
                $model = $this->getModel()->create($row);
                $stored = $this->normaliseRow($model->toArray());
            } catch (\Throwable $exception) {
                // 可能有并发 worker 先插入了 unique delivery_key + attempt
                // 行；先重读，再切换到进程内兜底适配器。
                $existing = $this->findByDeliveryAttempt($deliveryKey, $attempt);
                if ($existing !== null) {
                    $existing['claimed'] = false;
                    $existing['duplicate'] = true;
                    return $existing;
                }
                $this->fallbackToMemory($exception, 'begin_delivery');
                $stored = $this->memoryInsert($row);
            }
        }
        return $stored + ['claimed' => true, 'duplicate' => false];
    }

    /**
     * 保留给把第一条记录称为 reservation 的调用方的别名。
     *
     * @param PluginEventEnvelope|array<string, mixed> $event
     * @param array<string, mixed> $delivery
     * @return array<string, mixed>
     */
    public function reserve(
        PluginEventEnvelope|array $event,
        array $delivery,
        int $attempt = 1,
        array $options = []
    ): array {
        return $this->beginDelivery($event, $delivery, $attempt, $options);
    }

    /**
     * 将已认领的尝试标记为成功。
     *
     * @param array<string, mixed>|int|string $log
     * @return array<string, mixed>|null
     */
    public function markSuccess(array|int|string $log, int $durationMs = 0): ?array
    {
        return $this->mark($log, self::STATUS_SUCCEEDED, [
            'duration_ms' => max(0, $durationMs),
            'finished_at' => date('Y-m-d H:i:s'),
            'error_code' => '',
            'error_message' => '',
        ]);
    }

    /**
     * 标记失败尝试，并按尝试次数决定是 retrying 还是 dead_letter。
     *
     * @param array<string, mixed>|int|string $log
     * @return array<string, mixed>|null
     */
    public function markFailure(
        array|int|string $log,
        string $errorCode = 'handler_error',
        string $errorMessage = '',
        int $durationMs = 0,
        int $maxAttempts = 3,
        bool $retryable = true
    ): ?array {
        $row = is_array($log) ? $log : $this->get($log);
        if ($row === null) {
            return null;
        }
        $attempt = max(1, (int)($row['attempt'] ?? 1));
        $status = $retryable && $attempt < max(1, $maxAttempts)
            ? self::STATUS_RETRYING
            : ($retryable ? self::STATUS_DEAD_LETTER : self::STATUS_FAILED);
        return $this->mark($row, $status, [
            'duration_ms' => max(0, $durationMs),
            'finished_at' => date('Y-m-d H:i:s'),
            'error_code' => $this->normaliseErrorCode($errorCode),
            'error_message' => $this->sanitizeError($errorMessage),
        ]);
    }

    /**
     * Mark a delivery attempt retryable without applying the attempt limit.
     *
     * @param array<string, mixed>|int|string $log
     * @param string $errorCode Machine-readable error code.
     * @param string $errorMessage Safe error message.
     * @param int $durationMs Handler duration in milliseconds.
     * @return array<string, mixed>|null
     */
    public function markRetry(array|int|string $log, string $errorCode = 'handler_error', string $errorMessage = '', int $durationMs = 0): ?array
    {
        $row = is_array($log) ? $log : $this->get($log);
        if ($row === null) {
            return null;
        }
        return $this->mark($row, self::STATUS_RETRYING, [
            'duration_ms' => max(0, $durationMs),
            'finished_at' => date('Y-m-d H:i:s'),
            'error_code' => $this->normaliseErrorCode($errorCode),
            'error_message' => $this->sanitizeError($errorMessage),
        ]);
    }

    /**
     * Mark a delivery as skipped, commonly because its plugin is disabled.
     *
     * @param array<string, mixed>|int|string $log
     * @param string $reason Skip status/reason.
     * @return array<string, mixed>|null
     */
    public function markSkipped(array|int|string $log, string $reason = 'skipped_disabled'): ?array
    {
        $reason = $this->normaliseErrorCode($reason);
        return $this->mark($log, $reason === 'skipped_disabled' ? self::STATUS_SKIPPED_DISABLED : self::STATUS_SKIPPED, [
            'duration_ms' => 0,
            'finished_at' => date('Y-m-d H:i:s'),
            'error_code' => $reason,
            'error_message' => '',
        ]);
    }

    /**
     * Generic status update.
     *
     * @param array<string, mixed>|int $log
     * @param array<string, mixed> $changes
     * @return array<string, mixed>|null
     */
    public function mark(array|int|string $log, string $status, array $changes = []): ?array
    {
        $row = is_array($log) ? $log : $this->get($log);
        if ($row === null) {
            return null;
        }
        $changes['status'] = $this->normaliseStatus($status);
        if ($this->shouldUseMemory()) {
            return $this->memoryUpdate($row, $changes);
        }
        try {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                return $this->memoryUpdate($row, $changes);
            }
            $this->getModel()->where('id', $id)->update($changes);
            return $this->get($id);
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'mark');
            return $this->memoryUpdate($row, $changes);
        }
    }

    /**
     * 获取一条投递日志；无数据库或表不可用时从本实例内存仓读取。
     *
     * @param int|string|array<string, mixed>|float $identifier
     * @param array<int|string, mixed> $field
     * @param array<int|string, mixed> $with
     * @return array<string, mixed>|null
     */
    public function get(int|string|array|float $identifier, array $field = ['*'], array $with = [])
    {
        if (is_array($identifier)) {
            $identifier = $identifier['id'] ?? $identifier['delivery_key'] ?? '';
        }
        if ($identifier === '') {
            return null;
        }
        if ($this->shouldUseMemory()) {
            if (is_numeric($identifier)) {
                return $this->memoryRows[(int)$identifier] ?? null;
            }
            foreach ($this->memoryRows as $row) {
                if ((string)$row['delivery_key'] === (string)$identifier) {
                    return $row;
                }
            }
            return null;
        }
        try {
            $model = is_numeric($identifier)
                ? $this->getModel()->where('id', (int)$identifier)->find()
                : $this->getModel()->where('delivery_key', trim((string)$identifier))->order('attempt', 'desc')->find();
            return $model ? $this->normaliseRow($model->toArray()) : null;
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'get');
            return $this->get($identifier);
        }
    }

    /**
     * 读取处理日志资源，兼容内存适配器返回数组和数据库模型返回值。
     *
     * @param int|array<string, mixed> $id 日志 id 或包含 id/delivery_key 的条件。
     * @return array<string, mixed>
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id)
    {
        $identifier = is_array($id) ? ($id['id'] ?? $id['delivery_key'] ?? '') : $id;
        $row = $identifier !== '' ? $this->get($identifier) : null;
        if ($row === null) {
            throw new ModelNotFoundException('查询的数据不存在');
        }
        return is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : $row;
    }

    /**
     * Find a specific delivery attempt.
     *
     * @return array<string, mixed>|null
     */
    public function findByDeliveryAttempt(string $deliveryKey, int $attempt): ?array
    {
        $deliveryKey = trim($deliveryKey);
        $attempt = max(1, $attempt);
        if ($this->shouldUseMemory()) {
            foreach ($this->memoryRows as $row) {
                if ((string)$row['delivery_key'] === $deliveryKey && (int)$row['attempt'] === $attempt) {
                    return $row;
                }
            }
            return null;
        }
        try {
            $model = $this->getModel()->where('delivery_key', $deliveryKey)->where('attempt', $attempt)->find();
            return $model ? $this->normaliseRow($model->toArray()) : null;
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'find_delivery_attempt');
            return $this->findByDeliveryAttempt($deliveryKey, $attempt);
        }
    }

    /**
     * Return all attempts for one event, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function listByEvent(string $eventId): array
    {
        if ($this->shouldUseMemory()) {
            $rows = array_values(array_filter($this->memoryRows, static fn(array $row): bool => (string)$row['event_id'] === $eventId));
            usort($rows, static fn(array $a, array $b): int => ((int)$a['id']) <=> ((int)$b['id']));
            return $rows;
        }
        try {
            $rows = $this->getModel()->where('event_id', $eventId)->order('id', 'asc')->select()->toArray();
            return array_map(fn(array $row): array => $this->normaliseRow($row), $rows);
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'list_by_event');
            return $this->listByEvent($eventId);
        }
    }

    /**
     * Get the most recent attempt for a delivery key.
     */
    /**
     * Return the latest attempt number for a delivery key.
     *
     * @param string $deliveryKey Stable event/plugin/handler key.
     * @return int
     */
    public function latestAttempt(string $deliveryKey): int
    {
        $rows = $this->listByDeliveryKey($deliveryKey);
        if ($rows === []) {
            return 0;
        }
        return max(array_map(static fn(array $row): int => (int)$row['attempt'], $rows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByDeliveryKey(string $deliveryKey): array
    {
        if ($this->shouldUseMemory()) {
            $rows = array_values(array_filter($this->memoryRows, static fn(array $row): bool => (string)$row['delivery_key'] === $deliveryKey));
            usort($rows, static fn(array $a, array $b): int => ((int)$a['attempt']) <=> ((int)$b['attempt']));
            return $rows;
        }
        try {
            $rows = $this->getModel()->where('delivery_key', $deliveryKey)->order('attempt', 'asc')->select()->toArray();
            return array_map(fn(array $row): array => $this->normaliseRow($row), $rows);
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'list_by_delivery');
            return $this->listByDeliveryKey($deliveryKey);
        }
    }

    /**
     * Aggregate statuses for an event. Skipped deliveries are intentionally
     * non-failing; dead letters make the aggregate partial_failed.
     *
     * @return array<string, mixed>
     */
    public function aggregate(string $eventId): array
    {
        $rows = $this->listByEvent($eventId);
        $latest = [];
        foreach ($rows as $row) {
            $key = (string)$row['delivery_key'];
            if (!isset($latest[$key]) || (int)$row['attempt'] > (int)$latest[$key]['attempt']) {
                $latest[$key] = $row;
            }
        }
        $statuses = array_values(array_map(static fn(array $row): string => (string)$row['status'], $latest));
        $hasDeadLetter = in_array(self::STATUS_DEAD_LETTER, $statuses, true) || in_array(self::STATUS_FAILED, $statuses, true);
        $hasPending = array_intersect($statuses, [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_RETRYING]) !== [];
        $status = $hasDeadLetter ? 'partial_failed' : ($hasPending ? 'processing' : 'succeeded');
        return [
            'event_id' => $eventId,
            'status' => $status,
            'total' => count($latest),
            'succeeded' => count(array_filter($statuses, static fn(string $value): bool => $value === self::STATUS_SUCCEEDED)),
            'failed' => count(array_filter($statuses, static fn(string $value): bool => in_array($value, [self::STATUS_FAILED, self::STATUS_DEAD_LETTER], true))),
            'retrying' => count(array_filter($statuses, static fn(string $value): bool => $value === self::STATUS_RETRYING)),
            'skipped' => count(array_filter($statuses, static fn(string $value): bool => in_array($value, [self::STATUS_SKIPPED, self::STATUS_SKIPPED_DISABLED], true))),
            'deliveries' => array_values($latest),
        ];
    }

    /**
     * Build the stable idempotency key used for all attempts of one delivery.
     *
     * @return string
     */
    public function buildDeliveryKey(string $eventId, string $pluginCode, string $handler): string
    {
        $raw = trim($eventId) . ':' . trim($pluginCode) . ':' . trim($handler);
        return strlen($raw) <= 190 ? $raw : substr($raw, 0, 126) . ':' . substr(hash('sha256', $raw), 0, 63);
    }

    /**
     * Build the per-handler idempotency key persisted in the log.
     *
     * @return string
     */
    public function buildIdempotencyKey(string $eventKey, string $pluginCode, string $handler): string
    {
        $raw = trim($eventKey) . ':' . trim($pluginCode) . ':' . trim($handler);
        return strlen($raw) <= 128 ? $raw : substr(hash('sha256', $raw), 0, 128);
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $options
     */
    private function canReclaim(array $existing, array $options): bool
    {
        if (in_array((string)($existing['status'] ?? ''), [self::STATUS_SUCCEEDED, self::STATUS_DEAD_LETTER, self::STATUS_FAILED, self::STATUS_SKIPPED, self::STATUS_SKIPPED_DISABLED], true)) {
            return false;
        }
        if ((string)($existing['status'] ?? '') !== self::STATUS_PROCESSING) {
            return true;
        }
        if (($options['force'] ?? false) === true) {
            return true;
        }
        $startedAt = strtotime((string)($existing['started_at'] ?? '')) ?: 0;
        return $startedAt <= time() - self::LOCK_TTL_SECONDS;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function updateLog(array $row, array $changes): array
    {
        $id = (int)($row['id'] ?? 0);
        if ($this->shouldUseMemory() || $id <= 0) {
            return $this->memoryUpdate($row, $changes);
        }
        try {
            $this->getModel()->where('id', $id)->update($changes);
            return $this->get($id) ?? array_merge($row, $changes);
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'update_log');
            return $this->memoryUpdate($row, $changes);
        }
    }

    /** @param array<string, mixed> $row */
    private function memoryInsert(array $row): array
    {
        $row['id'] = ++$this->memorySequence;
        $this->memoryRows[$row['id']] = $row;
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function memoryUpdate(array $row, array $changes): array
    {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $this->memoryRows[$id] = array_merge($this->memoryRows[$id] ?? $row, $changes);
            return $this->memoryRows[$id];
        }
        return array_merge($row, $changes);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normaliseRow(array $row): array
    {
        foreach (['attempt', 'duration_ms', 'id'] as $field) {
            $row[$field] = (int)($row[$field] ?? 0);
        }
        foreach (['event_id', 'delivery_key', 'plugin_code', 'handler', 'status', 'idempotency_key', 'payload_hash', 'error_code', 'error_message'] as $field) {
            $row[$field] = (string)($row[$field] ?? '');
        }
        return $row;
    }

    private function normaliseStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_SUCCEEDED, self::STATUS_RETRYING, self::STATUS_FAILED, self::STATUS_DEAD_LETTER, self::STATUS_SKIPPED_DISABLED, self::STATUS_SKIPPED];
        return in_array($status, $allowed, true) ? $status : self::STATUS_FAILED;
    }

    private function normaliseErrorCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9_.:-]/', '_', $code) ?: 'handler_error';
        return mb_substr($code, 0, 64);
    }

    private function sanitizeError(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?: '';
        $message = preg_replace('/(?:password|secret|token|authorization)\s*[:=]\s*[^\s,;]+/i', '$1:[redacted]', $message) ?: $message;
        return mb_substr(trim($message), 0, 500);
    }

    private function payloadHash(PluginEventEnvelope $event): string
    {
        return $event->payloadHash();
    }

    private function shouldUseMemory(): bool
    {
        if ($this->instanceMemoryStore !== null) {
            return $this->instanceMemoryStore;
        }
        if ($this->memoryStore !== null) {
            return $this->memoryStore;
        }
        try {
            $configured = app()->config->get('plugin.event.storage', 'database');
            if (in_array(strtolower((string)$configured), ['memory', 'array', 'none'], true)) {
                $this->memoryStore = true;
                return true;
            }
        } catch (\Throwable) {
        }
        return false;
    }

    private function fallbackToMemory(\Throwable $exception, string $operation): void
    {
        $this->memoryStore = true;
        $this->instanceMemoryStore = true;
        try {
            Log::warning('[PluginEventLogService] ' . $operation . ' fallback: ' . $this->sanitizeError($exception->getMessage()));
        } catch (\Throwable) {
        }
    }
}
