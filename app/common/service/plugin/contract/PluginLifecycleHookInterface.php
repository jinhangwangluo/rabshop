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
 * 定义宿主插件行锁内执行的插件业务卸载检查与清理契约。
 */
interface PluginLifecycleHookInterface
{
    /**
     * 检查未完成业务任务，阻止不安全卸载且不自动取消订单。
     *
     * @param array $context 已安装身份和调用方明确指定的卸载策略。
     * @return void 无返回值；不满足卸载条件时抛出业务异常。
     */
    public function assertUninstallable(array $context): void;

    /**
     * 按已确认策略清理插件自有业务数据，永久订单与财务证据必须保留。
     *
     * @param array $context 安装身份和卸载策略，不允许删除共享订单或财务证据。
     * @return void 无返回值。
     */
    public function cleanup(array $context): void;
}
