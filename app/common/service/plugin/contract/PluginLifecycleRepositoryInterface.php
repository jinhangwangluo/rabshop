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
 * 本地插件和任务持久化端口。
 */
interface PluginLifecycleRepositoryInterface
{
    /** @return array<string,mixed>|null */
    public function findPlugin(string $pluginCode): ?array;

    /**
     * 读取已安装插件列表。
     *
     * @param array<string,mixed> $filters
     * @return array{list:array<int,array<string,mixed>>,total:int,page:int,limit:int}
     */
    public function listPlugins(array $filters = []): array;

    /** @return array<string,mixed> */
    public function createPlugin(array $data): array;

    /** @return array<string,mixed> */
    public function updatePlugin(string $pluginCode, array $data): array;

    /** @return array<string,mixed>|null */
    public function findTask(string|int $taskId): ?array;

    /** @return array<string,mixed>|null */
    public function findTaskByIdempotency(string $idempotencyKey): ?array;

    /** @return array<string,mixed> */
    public function createTask(array $data): array;

    /** @return array<string,mixed> */
    public function updateTask(string|int $taskId, array $data): array;
}
