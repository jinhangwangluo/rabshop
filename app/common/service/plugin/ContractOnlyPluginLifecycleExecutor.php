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

use app\common\service\plugin\contract\PluginLifecycleExecutorInterface;
use Throwable;

/**
 * v1.2 契约模拟执行器。
 *
 * 它不会改写代码、迁移数据库或 reload Worker，只返回可审计的模拟结果。
 */
final class ContractOnlyPluginLifecycleExecutor implements PluginLifecycleExecutorInterface
{
    /**
     * @param string $operation
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function execute(string $operation, array $context): array
    {
        return [
            'simulated' => true,
            'operation' => $operation,
            'plugin_code' => (string)($context['plugin_code'] ?? ''),
            'to_version' => (string)($context['to_version'] ?? ''),
        ];
    }

    public function recover(string $operation, array $context, Throwable $failure): void
    {
        // 契约阶段没有外部副作用，故无需恢复；真实安装器必须覆盖此实现。
    }
}
