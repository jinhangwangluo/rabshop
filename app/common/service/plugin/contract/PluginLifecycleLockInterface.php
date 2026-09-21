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
 * 插件发布互斥锁端口。
 */
interface PluginLifecycleLockInterface
{
    /**
     * @return string 锁句柄；获取失败抛出异常。
     */
    public function acquire(string $pluginCode): string;

    public function release(string $handle): void;
}
