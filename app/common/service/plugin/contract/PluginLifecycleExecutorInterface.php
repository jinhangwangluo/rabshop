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

use Throwable;

/**
 * 本地安装器执行端口。
 *
 * v1.2 默认使用契约模拟实现；真实文件覆盖、迁移和 reload 在后续安装器阶段接入。
 */
interface PluginLifecycleExecutorInterface
{
    /**
     * @param string $operation install|upgrade|disable|uninstall|rollback
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function execute(string $operation, array $context): array;

    /**
     * 恢复失败操作前的快照。恢复失败应继续向上抛出，不能伪造成功状态。
     *
     * @param string $operation
     * @param array<string,mixed> $context
     * @param Throwable $failure
     * @return void
     */
    public function recover(string $operation, array $context, Throwable $failure): void;
}
