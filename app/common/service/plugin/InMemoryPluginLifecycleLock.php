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
use app\common\service\plugin\contract\PluginLifecycleLockInterface;

/**
 * 契约测试锁。生产环境应替换为跨 Worker 的发布锁实现。
 */
final class InMemoryPluginLifecycleLock implements PluginLifecycleLockInterface
{
    /** @var array<string,string> */
    private array $locks = [];

    public function acquire(string $pluginCode): string
    {
        if (isset($this->locks[$pluginCode])) {
            throw new PluginLifecycleException('插件正在执行其他生命周期任务', 'PLUGIN_LOCKED');
        }
        $handle = $pluginCode . ':' . bin2hex(random_bytes(12));
        $this->locks[$pluginCode] = $handle;
        return $handle;
    }

    public function release(string $handle): void
    {
        foreach ($this->locks as $pluginCode => $current) {
            if (hash_equals($current, $handle)) {
                unset($this->locks[$pluginCode]);
                return;
            }
        }
    }
}
