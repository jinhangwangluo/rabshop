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
use Throwable;

/**
 * 使用插件与任务模型持久化生命周期状态，向业务服务提供统一仓储契约。
 */
class DatabasePluginLifecycleRepository implements PluginLifecycleRepositoryInterface
{
    private const PLUGIN_MODEL = 'app\\common\\model\\plugin\\Plugin';
    private const TASK_MODEL = 'app\\common\\model\\plugin\\PluginTask';

    /**
     * 读取未软删除的插件安装记录。
     *
     * @param string $pluginCode 插件编码。
     * @return array<string,mixed>|null 插件记录；不存在时为 null。
     */
    public function findPlugin(string $pluginCode): ?array
    {
        $model = $this->makeModel(self::PLUGIN_MODEL);
        $query = $model->where('code', $pluginCode);
        if ($this->hasColumn($model, 'delete_time')) {
            $query->whereNull('delete_time');
        }
        $row = $query->find();
        return $this->toArrayOrNull($row);
    }

    /**
     * 按编码、关键词及生命周期状态分页查询插件。
     *
     * @param array<string,mixed> $filters 分页和已允许的插件筛选条件。
     * @return array{list:array<int,array<string,mixed>>,total:int,page:int,limit:int} 当前页插件列表、总数及分页信息。
     */
    public function listPlugins(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $keyword = trim((string)($filters['keyword'] ?? ''));
        $enabled = $filters['enabled'] ?? null;
        $lifecycleStatus = trim((string)($filters['lifecycle_status'] ?? ''));

        $model = $this->makeModel(self::PLUGIN_MODEL);
        $query = $model->whereNull('delete_time');
        if (array_key_exists('codes', $filters)) {
            $codes = is_array($filters['codes']) ? array_values(array_unique(array_filter($filters['codes'], 'is_string'))) : [];
            if ($codes === [] || count($codes) > 100) {
                return ['list' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
            }
            $query->whereIn('code', $codes);
        }
        if ($keyword !== '') {
            $query->whereLike('code|name|version', '%' . $keyword . '%');
        }
        if ($enabled !== null && $enabled !== '') {
            $query->where('enabled', (int)$enabled);
        }
        if ($lifecycleStatus !== '') {
            $query->where('lifecycle_status', $lifecycleStatus);
        }

        $countQuery = clone $query;
        $total = (int)$countQuery->count();
        $list = $query
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 创建插件安装状态记录，并统一包装存储异常。
     *
     * @param array $data 待保存的插件状态字段。
     * @return array<string,mixed> 创建后的插件记录。
     */
    public function createPlugin(array $data): array
    {
        $model = $this->makeModel(self::PLUGIN_MODEL);
        try {
            $row = $model->create($data);
        } catch (Throwable $exception) {
            throw new PluginLifecycleException('插件状态保存失败', 'PLUGIN_STORAGE_WRITE_FAILED', 0, $exception);
        }
        return $this->toArray($row);
    }

    /**
     * 更新指定插件的安装状态记录。
     *
     * @param string $pluginCode 插件编码。
     * @param array $data 待更新的状态字段。
     * @return array<string,mixed> 更新后的插件记录。
     */
    public function updatePlugin(string $pluginCode, array $data): array
    {
        $model = $this->makeModel(self::PLUGIN_MODEL);
        $query = $model->where('code', $pluginCode);
        if ($this->hasColumn($model, 'delete_time')) {
            $query->whereNull('delete_time');
        }
        $row = $query->find();
        if (!$row) {
            throw new PluginLifecycleException('插件不存在', 'PLUGIN_NOT_FOUND');
        }
        try {
            $row->save($data);
        } catch (Throwable $exception) {
            throw new PluginLifecycleException('插件状态更新失败', 'PLUGIN_STORAGE_WRITE_FAILED', 0, $exception);
        }
        return $this->toArray($row);
    }

    /**
     * 通过任务 ID 或任务编号读取执行记录。
     *
     * @param string|int $taskId 任务 ID 或任务编号。
     * @return array<string,mixed>|null 任务记录；不存在时为 null。
     */
    public function findTask(string|int $taskId): ?array
    {
        $model = $this->makeModel(self::TASK_MODEL);
        $query = is_numeric((string)$taskId)
            ? $model->where('id', (int)$taskId)
            : $model->where('task_no', (string)$taskId);
        return $this->toArrayOrNull($query->find());
    }

    /**
     * 按幂等键读取既有生命周期任务，避免重复执行。
     *
     * @param string $idempotencyKey 生命周期请求的幂等键。
     * @return array<string,mixed>|null 既有任务记录；不存在时为 null。
     */
    public function findTaskByIdempotency(string $idempotencyKey): ?array
    {
        $model = $this->makeModel(self::TASK_MODEL);
        return $this->toArrayOrNull(
            $model->where('idempotency_key', $idempotencyKey)->find()
        );
    }

    /**
     * 持久化新的生命周期任务及执行身份。
     *
     * @param array $data 任务字段和幂等信息。
     * @return array<string,mixed> 创建后的任务记录。
     */
    public function createTask(array $data): array
    {
        $model = $this->makeModel(self::TASK_MODEL);
        try {
            $row = $model->create($data);
        } catch (Throwable $exception) {
            throw new PluginLifecycleException('插件任务创建失败', 'TASK_STORAGE_WRITE_FAILED', 0, $exception);
        }
        return $this->toArray($row);
    }

    /**
     * 更新指定生命周期任务的执行状态与结果。
     *
     * @param string|int $taskId 任务 ID 或任务编号。
     * @param array $data 待更新的任务字段。
     * @return array<string,mixed> 更新后的任务记录。
     */
    public function updateTask(string|int $taskId, array $data): array
    {
        $model = $this->makeModel(self::TASK_MODEL);
        $query = is_numeric((string)$taskId)
            ? $model->where('id', (int)$taskId)
            : $model->where('task_no', (string)$taskId);
        $row = $query->find();
        if (!$row) {
            throw new PluginLifecycleException('插件任务不存在', 'TASK_NOT_FOUND');
        }
        try {
            $row->save($data);
        } catch (Throwable $exception) {
            throw new PluginLifecycleException('插件任务更新失败', 'TASK_STORAGE_WRITE_FAILED', 0, $exception);
        }
        return $this->toArray($row);
    }

    /**
     * 从容器解析持久化模型，并统一报告初始化失败。
     *
     * @param string $class 模型完整类名。
     * @return object 已初始化的模型实例。
     */
    private function makeModel(string $class): object
    {
        if (!class_exists($class) || !function_exists('app')) {
            throw new PluginLifecycleException('插件本地存储未初始化', 'PLUGIN_STORAGE_UNAVAILABLE');
        }
        try {
            // 启动期模型会被 Swoole 请求沙箱继承，不能缓存其非池化数据库连接。
            $model = app()->make($class, [], true);
        } catch (Throwable $exception) {
            throw new PluginLifecycleException('插件本地存储不可用', 'PLUGIN_STORAGE_UNAVAILABLE', 0, $exception);
        }
        if (!is_object($model)) {
            throw new PluginLifecycleException('插件本地存储不可用', 'PLUGIN_STORAGE_UNAVAILABLE');
        }
        return $model;
    }

    /**
     * 探测模型字段，元数据不可用时按既定表结构继续处理。
     *
     * @param object $model 待检查的模型实例。
     * @param string $column 待探测的字段名。
     * @return bool 模型是否包含字段；无法探测时为 true。
     */
    private function hasColumn(object $model, string $column): bool
    {
        // 模型未提供字段元数据时按规划表结构处理；查询失败会被统一包装。
        if (method_exists($model, 'getTableFields')) {
            try {
                return in_array($column, $model->getTableFields(), true);
            } catch (Throwable) {
                return true;
            }
        }
        return true;
    }

    /**
     * 将模型记录转换为数组，兼容没有 toArray 方法的对象。
     *
     * @param object $row 查询得到的模型记录。
     * @return array<string,mixed> 记录字段数组。
     */
    private function toArray(object $row): array
    {
        if (method_exists($row, 'toArray')) {
            $data = $row->toArray();
            if (is_array($data)) {
                return $data;
            }
        }
        return get_object_vars($row);
    }

    /**
     * 统一对象、数组和空查询结果的输出结构。
     *
     * @param mixed $row 原始查询结果。
     * @return array<string,mixed>|null 记录数组；结果无效或不存在时为 null。
     */
    private function toArrayOrNull(mixed $row): ?array
    {
        return is_object($row) ? $this->toArray($row) : (is_array($row) ? $row : null);
    }
}
