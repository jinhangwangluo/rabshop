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

use app\common\job\plugin\PluginEventDispatchJob;
use app\common\model\plugin\PluginEventOutbox;
use app\common\plugin\event\PluginEventEnvelope;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\ModelNotFoundException;
use think\facade\Log;

/**
 * 插件事件的 Outbox 持久化和租约服务。
 *
 * 事件生产者会在业务事务内调用 enqueue。这个服务不会在当前请求里
 * 直接执行处理器，而是交给队列任务在事务提交后异步投递。对于 CLI
 * 或单元测试等还没有 v1.2 表结构的环境，提供有限的内存存储兜底。
 */
class PluginEventOutboxService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_PARTIAL_FAILED = 'partial_failed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    private const LOCK_TTL_SECONDS = 300;

    /** @var array<int, array<string, mixed>> */
    private array $memoryRows = [];
    private int $memorySequence = 0;
    private ?bool $memoryStore = null;
    private ?bool $instanceMemoryStore = null;

    /**
     * @return class-string<PluginEventOutbox>
     */
    protected function setModel(): string
    {
        return PluginEventOutbox::class;
    }

    /**
     * 强制使用内存存储，适合测试或迁移尚未执行的节点。
     *
     * 这个存储只属于当前服务实例，不会在进程间共享。
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
     * 返回当前实例内存适配器里的记录。
     *
     * @return array<int, array<string, mixed>>
     */
    public function memoryRows(): array
    {
        return array_values($this->memoryRows);
    }

    /**
     * 清空当前实例的内存适配器，并为下一次调用重新启用它。
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
     * Persist one event and its subscription snapshot.
     *
     * @param PluginEventEnvelope|array<string, mixed> $event
     * @param array<int|string, mixed> $snapshot
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function enqueue(PluginEventEnvelope|array $event, array $snapshot = [], array $options = []): array
    {
        $envelope = $event instanceof PluginEventEnvelope ? $event : PluginEventEnvelope::fromArray($event);
        $snapshot = $this->normaliseSnapshot($snapshot, $envelope);
        $idempotencyKey = trim((string)($options['idempotency_key'] ?? $envelope->getIdempotencyKey()));
        if ($idempotencyKey === '') {
            $idempotencyKey = $envelope->getEventId();
        }
        $row = [
            'event_id' => $envelope->getEventId(),
            'name' => $envelope->getName(),
            'event_version' => $envelope->getVersion(),
            'trace_id' => $envelope->getTraceId(),
            'actor_json' => $envelope->getActor() ?? [],
            'merchant_id' => $envelope->getMerchantId(),
            'subject_type' => (string)($envelope->getSubject()['type'] ?? ''),
            'subject_id' => (string)($envelope->getSubject()['id'] ?? ''),
            'payload_json' => $envelope->getPayload(),
            'subscriber_snapshot_json' => $snapshot,
            'idempotency_key' => mb_substr($idempotencyKey, 0, 128),
            'status' => $snapshot['subscribers'] !== [] ? self::STATUS_PENDING : self::STATUS_SUCCEEDED,
            'attempts' => 0,
            'available_at' => $this->normaliseDate($options['available_at'] ?? null),
            'locked_at' => null,
            'locked_by' => '',
            'dispatched_at' => null,
            'last_error' => '',
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        if (isset($options['status']) && is_string($options['status'])) {
            $row['status'] = $this->normaliseStatus($options['status']);
        }

        $strictPersistence = ($options['strict_persistence'] ?? false) === true;
        $existing = $this->findExisting($row['event_id'], $row['idempotency_key'], $strictPersistence);
        if ($existing !== null) {
            $existing['duplicate'] = true;
            return $existing;
        }

        $stored = null;
        if ($this->shouldUseMemory()) {
            $stored = $this->memoryInsert($row);
        } else {
            try {
                /** @var PluginEventOutbox $model */
                $model = $this->getModel()->create($row);
                $stored = $this->normaliseRow($model->toArray());
            } catch (\Throwable $exception) {
                if ($strictPersistence) {
                    throw $exception;
                }
                // 可能有并发生产者在 findExisting() 和 create() 之间抢先
                // 写入唯一键；这里先重查，再回退到进程内适配器，避免
                // 同一事件在本地被重复落库。
                $existing = $this->findExisting($row['event_id'], $row['idempotency_key'], $strictPersistence);
                if ($existing !== null) {
                    $existing['duplicate'] = true;
                    return $existing;
                }
                $this->fallbackToMemory($exception, 'enqueue');
                $stored = $this->memoryInsert($row);
            }
        }

        $stored['duplicate'] = false;
        $queued = false;
        if (($options['dispatch'] ?? false) && $stored['status'] === self::STATUS_PENDING) {
            try {
                $argument = (int)($stored['id'] ?? 0) ?: (string)$stored['event_id'];
                PluginEventDispatchJob::dispatch($argument);
                $queued = true;
            } catch (\Throwable $exception) {
                // 持久化优先于队列可用性；后续的 outbox 扫描仍然可以把
                // 这条 pending 记录重新捞起来。
                $this->logFailure('queue', $exception, $stored);
            }
        }
        $stored['queued'] = $queued;
        return $stored;
    }

    /**
     * 事件生产者常用的别名。
     *
     * @param PluginEventEnvelope|array<string, mixed> $event
     * @param array<int|string, mixed> $snapshot
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function store(PluginEventEnvelope|array $event, array $snapshot = [], array $options = []): array
    {
        return $this->enqueue($event, $snapshot, $options);
    }

    /**
     * 获取一条 Outbox 记录；数据库不可用时从当前实例内存仓读取。
     *
     * @param int|string|array<string, mixed>|float $identifier
     * @param array<int|string, mixed> $field
     * @param array<int|string, mixed> $with
     * @return array<string, mixed>|null
     */
    public function get(int|string|array|float $identifier, array $field = ['*'], array $with = [])
    {
        if (is_array($identifier)) {
            $identifier = $identifier['id'] ?? $identifier['event_id'] ?? '';
        }
        if ($identifier === '') {
            return null;
        }
        if ($this->shouldUseMemory()) {
            return $this->memoryFind($identifier);
        }
        try {
            $query = $this->getModel();
            $model = is_numeric($identifier)
                ? $query->where('id', (int)$identifier)->find()
                : $query->where('event_id', trim((string)$identifier))->find();
            if (!$model) {
                return null;
            }
            return $this->normaliseRow($model->toArray());
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'get');
            return $this->memoryFind($identifier);
        }
    }

    /**
     * 读取 Outbox 资源，兼容内存适配器返回数组和数据库模型返回值。
     *
     * @param int|array<string, mixed> $id Outbox id 或包含 id/event_id 的条件。
     * @return array<string, mixed>
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id)
    {
        $identifier = is_array($id) ? ($id['id'] ?? $id['event_id'] ?? '') : $id;
        $row = $identifier !== '' ? $this->get($identifier) : null;
        if ($row === null) {
            throw new ModelNotFoundException('查询的数据不存在');
        }
        return is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : $row;
    }

    /**
     * get() 的兼容别名。
     *
     * @param int|string $identifier Outbox id 或 event_id。
     * @return array<string, mixed>|null
     */
    public function find(int|string $identifier): ?array
    {
        return $this->get($identifier);
    }

    /**
     * 按 idempotency_key 查找已存在事件。
     *
     * @param string $idempotencyKey 事件幂等键。
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return null;
        }
        if ($this->shouldUseMemory()) {
            foreach ($this->memoryRows as $row) {
                if ((string)$row['idempotency_key'] === $idempotencyKey) {
                    return $row;
                }
            }
            return null;
        }
        try {
            $model = $this->getModel()->where('idempotency_key', $idempotencyKey)->find();
            return $model ? $this->normaliseRow($model->toArray()) : null;
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'find_by_idempotency_key');
            return $this->findByIdempotencyKey($idempotencyKey);
        }
    }

    /**
     * 认领一条待处理事件，返回的租约对当前 worker 是独占的。
     *
     * @param int|string|null $identifier
     * @return array<string, mixed>|null
     */
    public function claim(int|string|null $identifier = null, ?string $workerId = null, array $options = []): ?array
    {
        $workerId = $this->normaliseWorkerId($workerId);
        $now = time();
        if ($this->shouldUseMemory()) {
            return $this->memoryClaim($identifier, $workerId, $now, $options);
        }

        try {
            $expectedStatus = self::STATUS_PENDING;
            $query = $this->getModel()->where('status', self::STATUS_PENDING);
            if (($options['force'] ?? false) !== true) {
                $query = $query->where('available_at', '<=', date('Y-m-d H:i:s'));
            }
            if ($identifier !== null) {
                $query = is_numeric($identifier)
                    ? $query->where('id', (int)$identifier)
                    : $query->where('event_id', trim((string)$identifier));
            }
            $model = $query->order('id', 'asc')->find();
            $staleBefore = date('Y-m-d H:i:s', $now - self::LOCK_TTL_SECONDS);
            if (!$model) {
                // 回收已经失联 worker 留下的租约；把 stale 条件保留在
                // update 里，保证所有权检查仍然是原子性的。
                $staleQuery = $this->getModel()
                    ->where('status', self::STATUS_PROCESSING)
                    ->whereNotNull('locked_at')
                    ->where('locked_at', '<=', $staleBefore);
                if ($identifier !== null) {
                    $staleQuery = is_numeric($identifier)
                        ? $staleQuery->where('id', (int)$identifier)
                        : $staleQuery->where('event_id', trim((string)$identifier));
                }
                $model = $staleQuery->order('id', 'asc')->find();
                if ($model) {
                    $expectedStatus = self::STATUS_PROCESSING;
                }
            }
            if (!$model) {
                return null;
            }
            $id = (int)$model->id;
            $updateQuery = $this->getModel()->where('id', $id)->where('status', $expectedStatus);
            if ($expectedStatus === self::STATUS_PROCESSING) {
                $updateQuery = $updateQuery->whereNotNull('locked_at')->where('locked_at', '<=', $staleBefore);
            }
            $updated = $updateQuery->update([
                'status' => self::STATUS_PROCESSING,
                'attempts' => ((int)$model->attempts) + 1,
                'locked_at' => date('Y-m-d H:i:s'),
                'locked_by' => $workerId,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            if ((int)$updated < 1) {
                return null;
            }
            return $this->get($id);
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'claim');
            return $this->memoryClaim($identifier, $workerId, $now, $options);
        }
    }

    /**
     * 认领下一条可用事件。
     *
     * @param string|null $workerId 用于租约的 worker 标识。
     * @return array<string, mixed>|null
     */
    public function claimNext(?string $workerId = null): ?array
    {
        return $this->claim(null, $workerId);
    }

    /**
     * 更新 Outbox 状态和可选字段。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function mark(int|string $identifier, string $status, array $data = []): ?array
    {
        $status = $this->normaliseStatus($status);
        $row = $this->get($identifier);
        if ($row === null) {
            return null;
        }
        $changes = array_merge($data, [
            'status' => $status,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        if (in_array($status, [self::STATUS_SUCCEEDED, self::STATUS_PARTIAL_FAILED, self::STATUS_FAILED, self::STATUS_SKIPPED], true)) {
            $changes['dispatched_at'] = $changes['dispatched_at'] ?? date('Y-m-d H:i:s');
            $changes['locked_at'] = null;
            $changes['locked_by'] = '';
        }
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
     * 标记事件已全部投递成功。
     *
     * @param int|string $identifier Outbox id or event_id.
     * @param array<string, mixed> $data Additional persisted fields.
     * @return array<string, mixed>|null
     */
    public function markSucceeded(int|string $identifier, array $data = []): ?array
    {
        return $this->mark($identifier, self::STATUS_SUCCEEDED, $data);
    }

    /**
     * 在一条或多条 dead letter 之后，把事件标记为部分失败。
     *
     * @param int|string $identifier Outbox id or event_id.
     * @param array<string, mixed> $data Additional persisted fields.
     * @return array<string, mixed>|null
     */
    public function markPartialFailed(int|string $identifier, array $data = []): ?array
    {
        return $this->mark($identifier, self::STATUS_PARTIAL_FAILED, $data);
    }

    /**
     * 标记事件为终态失败。
     *
     * @param int|string $identifier Outbox id or event_id.
     * @param string $error Safe error text stored in last_error.
     * @param array<string, mixed> $data Additional persisted fields.
     * @return array<string, mixed>|null
     */
    public function markFailed(int|string $identifier, string $error = '', array $data = []): ?array
    {
        return $this->mark($identifier, self::STATUS_FAILED, array_merge(['last_error' => $this->sanitizeError($error)], $data));
    }

    /**
     * 把 processing 记录退回 pending，供重试 worker 重新认领。
     *
     * @param int|string $identifier Outbox id or event_id.
     * @param int $delaySeconds available_at 的延迟秒数。
     * @param string $error Safe retry reason.
     * @return array<string, mixed>|null
     */
    public function release(int|string $identifier, int $delaySeconds = 0, string $error = ''): ?array
    {
        return $this->mark($identifier, self::STATUS_PENDING, [
            'available_at' => date('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
            'last_error' => $this->sanitizeError($error),
            'locked_at' => null,
            'locked_by' => '',
        ]);
    }

    /**
     * 返回已解码的订阅快照。
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function getSnapshot(array $row): array
    {
        $snapshot = $row['subscriber_snapshot_json'] ?? $row['subscriber_snapshot'] ?? [];
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }
        return $this->normaliseSnapshot($snapshot, null);
    }

    /**
     * 显式重放会用新的快照把事件重置回 pending。
     *
     * 调用方（通常是 PluginEventBus）必须先重建并重新校验快照，
     * 再调用这个方法。
     *
     * @param array<int|string, mixed> $snapshot
     * @param array<string, mixed> $options
     */
    public function replay(int|string $identifier, array $snapshot = [], array $options = []): ?array
    {
        $row = $this->get($identifier);
        if ($row === null) {
            return null;
        }
        $snapshot = $snapshot !== [] ? $this->normaliseSnapshot($snapshot, null) : $this->getSnapshot($row);
        // 把重放意图保存在不可变快照里；投递 worker 在首次认领时消费，
        // 同时仍保留之前的日志记录。
        $snapshot['replay_generation'] = $this->replayGeneration();
        $changes = [
            'status' => ($snapshot['subscribers'] ?? []) !== [] ? self::STATUS_PENDING : self::STATUS_SUCCEEDED,
            'subscriber_snapshot_json' => $snapshot,
            'attempts' => 0,
            'available_at' => $this->normaliseDate($options['available_at'] ?? null),
            'locked_at' => null,
            'locked_by' => '',
            'dispatched_at' => null,
            'last_error' => '',
            'update_time' => date('Y-m-d H:i:s'),
        ];
        $result = $this->shouldUseMemory()
            ? $this->memoryUpdate($row, $changes)
            : $this->updateDatabaseRow($row, $changes);
        if ($result !== null && ($options['dispatch'] ?? false)) {
            try {
                PluginEventDispatchJob::dispatch((int)($result['id'] ?? 0) ?: (string)$result['event_id']);
            } catch (\Throwable $exception) {
                $this->logFailure('replay_queue', $exception, $result);
            }
        }
        return $result;
    }

    private function replayGeneration(): string
    {
        try {
            return bin2hex(random_bytes(12));
        } catch (\Throwable) {
            return substr(hash('sha256', uniqid('', true)), 0, 24);
        }
    }

    /**
     * @param string $eventId
     * @return list<array<string, mixed>>
     */
    public function listDeliveries(string $eventId): array
    {
        $row = $this->get($eventId);
        if ($row === null) {
            return [];
        }
        $snapshot = $this->getSnapshot($row);
        return array_values(array_filter($snapshot['subscribers'] ?? [], 'is_array'));
    }

    /**
     * 根据投递状态返回聚合状态。
     *
     * @param list<string> $statuses
     */
    public function aggregateStatus(array $statuses): string
    {
        $statuses = array_values(array_filter(array_map('strval', $statuses)));
        if ($statuses === []) {
            return self::STATUS_SUCCEEDED;
        }
        if (in_array(PluginEventLogService::STATUS_DEAD_LETTER, $statuses, true)
            || in_array(PluginEventLogService::STATUS_FAILED, $statuses, true)) {
            return self::STATUS_PARTIAL_FAILED;
        }
        if (in_array(PluginEventLogService::STATUS_PENDING, $statuses, true)
            || in_array(PluginEventLogService::STATUS_PROCESSING, $statuses, true)
            || in_array(PluginEventLogService::STATUS_RETRYING, $statuses, true)) {
            return self::STATUS_PROCESSING;
        }
        return self::STATUS_SUCCEEDED;
    }

    /**
     * 按 event 或幂等键查找重复记录。
     *
     * @return array<string, mixed>|null
     */
    private function findExisting(string $eventId, string $idempotencyKey, bool $strictPersistence = false): ?array
    {
        if ($this->shouldUseMemory()) {
            foreach ($this->memoryRows as $row) {
                if ((string)$row['event_id'] === $eventId || (string)$row['idempotency_key'] === $idempotencyKey) {
                    return $row;
                }
            }
            return null;
        }
        try {
            $query = $this->getModel()->where('event_id', $eventId);
            $model = $query->find();
            if (!$model) {
                $model = $this->getModel()->where('idempotency_key', $idempotencyKey)->find();
            }
            return $model ? $this->normaliseRow($model->toArray()) : null;
        } catch (\Throwable $exception) {
            if ($strictPersistence) {
                throw $exception;
            }
            $this->fallbackToMemory($exception, 'find_existing');
            return $this->findExisting($eventId, $idempotencyKey);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function memoryInsert(array $row): array
    {
        $row['id'] = ++$this->memorySequence;
        $this->memoryRows[$row['id']] = $row;
        return $row;
    }

    /**
     * @param int|string $identifier
     * @return array<string, mixed>|null
     */
    private function memoryFind(int|string $identifier): ?array
    {
        if (is_numeric($identifier)) {
            return $this->memoryRows[(int)$identifier] ?? null;
        }
        foreach ($this->memoryRows as $row) {
            if ((string)$row['event_id'] === trim((string)$identifier)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param int|string|null $identifier
     * @return array<string, mixed>|null
     */
    private function memoryClaim(int|string|null $identifier, string $workerId, int $now, array $options = []): ?array
    {
        $candidate = null;
        foreach ($this->memoryRows as $row) {
            if ($identifier !== null && ((is_numeric($identifier) && (int)$row['id'] !== (int)$identifier)
                || (!is_numeric($identifier) && (string)$row['event_id'] !== (string)$identifier))) {
                continue;
            }
            $available = strtotime((string)($row['available_at'] ?? '')) ?: 0;
            $locked = strtotime((string)($row['locked_at'] ?? '')) ?: 0;
            $stale = $locked > 0 && $locked + self::LOCK_TTL_SECONDS <= $now;
            if (($row['status'] ?? '') !== self::STATUS_PENDING && !($stale && ($row['status'] ?? '') === self::STATUS_PROCESSING)) {
                continue;
            }
            if (($options['force'] ?? false) !== true && $available > $now) {
                continue;
            }
            $candidate = $row;
            break;
        }
        if ($candidate === null) {
            return null;
        }
        $id = (int)$candidate['id'];
        $this->memoryRows[$id]['status'] = self::STATUS_PROCESSING;
        $this->memoryRows[$id]['attempts'] = ((int)($candidate['attempts'] ?? 0)) + 1;
        $this->memoryRows[$id]['locked_at'] = date('Y-m-d H:i:s');
        $this->memoryRows[$id]['locked_by'] = $workerId;
        $this->memoryRows[$id]['update_time'] = date('Y-m-d H:i:s');
        return $this->memoryRows[$id];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function memoryUpdate(array $row, array $changes): array
    {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            return array_merge($row, $changes);
        }
        $this->memoryRows[$id] = array_merge($this->memoryRows[$id] ?? $row, $changes);
        return $this->memoryRows[$id];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $changes
     * @return array<string, mixed>|null
     */
    private function updateDatabaseRow(array $row, array $changes): ?array
    {
        try {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                return null;
            }
            $this->getModel()->where('id', $id)->update($changes);
            return $this->get($id);
        } catch (\Throwable $exception) {
            $this->fallbackToMemory($exception, 'update');
            return $this->memoryUpdate($row, $changes);
        }
    }

    /**
     * @param array<int|string, mixed> $snapshot
     * @param PluginEventEnvelope|null $envelope
     * @return array<string, mixed>
     */
    private function normaliseSnapshot(array $snapshot, ?PluginEventEnvelope $envelope): array
    {
        if (array_is_list($snapshot)) {
            $snapshot = ['subscribers' => $snapshot];
        }
        $subscribers = is_array($snapshot['subscribers'] ?? null) ? $snapshot['subscribers'] : [];
        $safeSubscribers = [];
        foreach ($subscribers as $descriptor) {
            if (!is_array($descriptor)) {
                continue;
            }
            $descriptor = $this->stripCallables($descriptor);
            $code = trim((string)($descriptor['plugin_code'] ?? $descriptor['pluginCode'] ?? ''));
            $handler = trim((string)($descriptor['handler'] ?? ''));
            if ($code === '' || $handler === '') {
                continue;
            }
            $descriptor['plugin_code'] = $code;
            $descriptor['event'] = (string)($descriptor['event'] ?? $descriptor['name'] ?? ($envelope?->getName() ?? ''));
            $descriptor['type'] = (string)($descriptor['type'] ?? 'observer');
            $descriptor['priority'] = (int)($descriptor['priority'] ?? 1000);
            $safeSubscribers[] = $descriptor;
        }
        usort($safeSubscribers, static function (array $left, array $right): int {
            $priority = ((int)($left['priority'] ?? 1000)) <=> ((int)($right['priority'] ?? 1000));
            if ($priority !== 0) {
                return $priority;
            }
            $plugin = strcmp((string)$left['plugin_code'], (string)$right['plugin_code']);
            return $plugin !== 0 ? $plugin : strcmp((string)$left['handler'], (string)$right['handler']);
        });
        $snapshot['subscribers'] = $safeSubscribers;
        $snapshot['skipped'] = is_array($snapshot['skipped'] ?? null) ? array_values($snapshot['skipped']) : [];
        $snapshot['schema_version'] = (string)($snapshot['schema_version'] ?? '1.0');
        if ($envelope !== null) {
            $snapshot['event'] = (string)($snapshot['event'] ?? $envelope->getName());
            $snapshot['event_version'] = (string)($snapshot['event_version'] ?? $envelope->getVersion());
            // 表结构刻意不重复存 occurred_at；把它保留在不可变快照里，
            // 这样 worker 才能还原原始信封，而不是拿 create_time 顶替。
            $snapshot['occurred_at'] = (string)($snapshot['occurred_at'] ?? $envelope->getOccurredAt());
        }
        return $this->stripCallables($snapshot);
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
            // 没有应用容器的独立测试先尝试数据库路径，模型不可用时再
            // 自动回退到内存适配器。
        }
        return false;
    }

    private function fallbackToMemory(\Throwable $exception, string $operation): void
    {
        $this->memoryStore = true;
        $this->instanceMemoryStore = true;
        $this->logFailure($operation, $exception, []);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function logFailure(string $operation, \Throwable $exception, array $row): void
    {
        try {
            Log::warning('[PluginEventOutboxService] ' . $operation . ' fallback: ' . $this->sanitizeError($exception->getMessage()));
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normaliseRow(array $row): array
    {
        foreach (['actor_json', 'payload_json', 'subscriber_snapshot_json'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : [];
            }
        }
        $row['id'] = (int)($row['id'] ?? 0);
        $row['merchant_id'] = (int)($row['merchant_id'] ?? 0);
        $row['attempts'] = (int)($row['attempts'] ?? 0);
        $row['status'] = (string)($row['status'] ?? self::STATUS_PENDING);
        return $row;
    }

    private function normaliseStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_SUCCEEDED, self::STATUS_PARTIAL_FAILED, self::STATUS_FAILED, self::STATUS_SKIPPED];
        return in_array($status, $allowed, true) ? $status : self::STATUS_PENDING;
    }

    private function normaliseDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }
        return date('Y-m-d H:i:s');
    }

    private function normaliseWorkerId(?string $workerId): string
    {
        $workerId = trim((string)$workerId);
        if ($workerId === '') {
            $workerId = gethostname() . ':' . getmypid() . ':' . substr(hash('sha256', uniqid('', true)), 0, 12);
        }
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:-]/', '_', $workerId) ?: 'worker', 0, 64);
    }

    private function sanitizeError(string $error): string
    {
        $error = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $error) ?: '';
        $error = preg_replace('/(?:password|secret|token|authorization)\s*[:=]\s*[^\s,;]+/i', '$1:[redacted]', $error) ?: $error;
        return mb_substr(trim($error), 0, 500);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function stripCallables(mixed $value): mixed
    {
        if (is_callable($value) && !is_string($value)) {
            return null;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if ($key === 'callable') {
                    continue;
                }
                $result[$key] = $this->stripCallables($item);
            }
            return $result;
        }
        return $value;
    }
}
