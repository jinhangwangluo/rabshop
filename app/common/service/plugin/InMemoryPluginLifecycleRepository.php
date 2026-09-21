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
use app\common\service\plugin\contract\PluginLifecycleRepositoryInterface;

/**
 * 为生命周期契约测试提供内存仓储，不得用于生产持久化。
 */
final class InMemoryPluginLifecycleRepository implements PluginLifecycleRepositoryInterface
{
    /**
     * 按编码索引的测试插件记录。
     *
     * @var array<string,array<string,mixed>>
     */
    private array $plugins = [];

    /**
     * 按任务编号索引的测试任务记录。
     *
     * @var array<string,array<string,mixed>>
     */
    private array $tasks = [];

    private int $nextTaskId = 1;

    /**
     * 装载生命周期契约测试的插件与任务种子数据。
     *
     * @param list<array<string,mixed>> $plugins 初始插件记录列表。
     * @param list<array<string,mixed>> $tasks 初始任务记录列表。
     * @return void 无返回值。
     */
    public function __construct(array $plugins = [], array $tasks = [])
    {
        foreach ($plugins as $plugin) {
            if (isset($plugin['code'])) {
                $this->plugins[(string)$plugin['code']] = $plugin;
            }
        }
        foreach ($tasks as $task) {
            $key = (string)($task['task_no'] ?? $task['id'] ?? '');
            if ($key !== '') {
                $this->tasks[$key] = $task;
            }
            $this->nextTaskId = max($this->nextTaskId, ((int)($task['id'] ?? 0)) + 1);
        }
    }

    /**
     * 按编码读取内存插件记录。
     *
     * @param string $pluginCode 插件编码。
     * @return ?array 插件记录；不存在时为 null。
     */
    public function findPlugin(string $pluginCode): ?array
    {
        return $this->plugins[$pluginCode] ?? null;
    }

    /**
     * 按编码、关键词及生命周期状态分页查询插件。
     *
     * @param array $filters 分页和已允许的插件筛选条件。
     * @return array 当前页插件列表、总数及分页信息。
     */
    public function listPlugins(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $keyword = trim((string)($filters['keyword'] ?? ''));
        $enabled = $filters['enabled'] ?? null;
        $lifecycleStatus = trim((string)($filters['lifecycle_status'] ?? ''));
        $codes = array_key_exists('codes', $filters)
            ? (is_array($filters['codes']) ? array_values(array_unique(array_filter($filters['codes'], 'is_string'))) : []) : null;
        if ($codes !== null && count($codes) > 100) {
            $codes = [];
        }

        $list = array_values(array_filter($this->plugins, static function (array $plugin) use ($keyword, $enabled, $lifecycleStatus, $codes): bool {
            if ($codes !== null && !in_array((string)($plugin['code'] ?? ''), $codes, true)) {
                return false;
            }
            if ($keyword !== '') {
                $haystack = implode(' ', [
                    (string)($plugin['code'] ?? ''),
                    (string)($plugin['name'] ?? ''),
                    (string)($plugin['version'] ?? ''),
                ]);
                if (stripos($haystack, $keyword) === false) {
                    return false;
                }
            }
            if ($enabled !== null && $enabled !== '' && (int)($plugin['enabled'] ?? 0) !== (int)$enabled) {
                return false;
            }
            if ($lifecycleStatus !== '' && (string)($plugin['lifecycle_status'] ?? '') !== $lifecycleStatus) {
                return false;
            }
            return true;
        }));

        usort($list, static function (array $left, array $right): int {
            return ((int)($right['id'] ?? 0)) <=> ((int)($left['id'] ?? 0));
        });

        $total = count($list);
        $offset = ($page - 1) * $limit;
        $rows = array_slice($list, $offset, $limit);

        return [
            'list' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 在内存中创建插件记录，并检查编码唯一性。
     *
     * @param array $data 待创建的插件字段。
     * @return array 创建后的插件记录。
     */
    public function createPlugin(array $data): array
    {
        $code = (string)($data['code'] ?? '');
        if ($code === '') {
            throw new PluginLifecycleException('插件编码不能为空', 'INVALID_PLUGIN_CODE');
        }
        if (isset($this->plugins[$code])) {
            throw new PluginLifecycleException('插件已存在', 'PLUGIN_ALREADY_EXISTS');
        }
        $data += ['id' => count($this->plugins) + 1];
        $this->plugins[$code] = $data;
        return $data;
    }

    /**
     * 合并内存插件记录中的状态变更。
     *
     * @param string $pluginCode 插件编码。
     * @param array $data 待合并的插件字段。
     * @return array 更新后的插件记录。
     */
    public function updatePlugin(string $pluginCode, array $data): array
    {
        if (!isset($this->plugins[$pluginCode])) {
            throw new PluginLifecycleException('插件不存在', 'PLUGIN_NOT_FOUND');
        }
        $this->plugins[$pluginCode] = array_merge($this->plugins[$pluginCode], $data);
        return $this->plugins[$pluginCode];
    }

    /**
     * 通过任务 ID 或任务编号读取执行记录。
     *
     * @param string|int $taskId 任务 ID 或任务编号。
     * @return ?array 任务记录；不存在时为 null。
     */
    public function findTask(string|int $taskId): ?array
    {
        foreach ($this->tasks as $task) {
            if ((string)($task['id'] ?? '') === (string)$taskId
                || (string)($task['task_no'] ?? '') === (string)$taskId
            ) {
                return $task;
            }
        }
        return null;
    }

    /**
     * 按幂等键读取既有生命周期任务，避免重复执行。
     *
     * @param string $idempotencyKey 生命周期请求的幂等键。
     * @return ?array 既有任务记录；不存在时为 null。
     */
    public function findTaskByIdempotency(string $idempotencyKey): ?array
    {
        foreach ($this->tasks as $task) {
            if ((string)($task['idempotency_key'] ?? '') === $idempotencyKey) {
                return $task;
            }
        }
        return null;
    }

    /**
     * 在内存中创建任务并维护幂等键唯一性。
     *
     * @param array $data 待创建的任务字段。
     * @return array 包含自增 ID 和任务编号的记录。
     */
    public function createTask(array $data): array
    {
        $key = (string)($data['idempotency_key'] ?? '');
        if ($key !== '' && $this->findTaskByIdempotency($key) !== null) {
            throw new PluginLifecycleException('任务幂等键已存在', 'TASK_IDEMPOTENCY_CONFLICT');
        }
        $data['id'] = $this->nextTaskId++;
        $data['task_no'] = (string)($data['task_no'] ?? ('pt_' . date('YmdHis') . '_' . $data['id']));
        $this->tasks[$data['task_no']] = $data;
        return $data;
    }

    /**
     * 合并内存任务记录中的状态变更。
     *
     * @param string|int $taskId 任务 ID 或任务编号。
     * @param array $data 待合并的任务字段。
     * @return array 更新后的任务记录。
     */
    public function updateTask(string|int $taskId, array $data): array
    {
        foreach ($this->tasks as $key => $task) {
            if ((string)($task['id'] ?? '') === (string)$taskId
                || (string)($task['task_no'] ?? '') === (string)$taskId
            ) {
                $this->tasks[$key] = array_merge($task, $data);
                return $this->tasks[$key];
            }
        }
        throw new PluginLifecycleException('插件任务不存在', 'TASK_NOT_FOUND');
    }

    /**
     * 获取测试中保存的全部插件记录。
     *
     * @return array<string,array<string,mixed>> 按插件编码索引的插件记录。
     */
    public function plugins(): array
    {
        return $this->plugins;
    }

    /**
     * 获取测试中保存的全部任务记录。
     *
     * @return array<string,array<string,mixed>> 按任务编号索引的任务记录。
     */
    public function tasks(): array
    {
        return $this->tasks;
    }
}
