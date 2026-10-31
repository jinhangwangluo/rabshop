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

namespace app\common\plugin\event;

/**
 * 后端插件事件处理器的可选显式契约。
 *
 * 实现该接口的处理器能拿到完整的 PluginEventEnvelope；不实现的 callable
 * （闭包、函数名、类@方法）同样被 PluginRegistry 支持，由事件总线按声明的
 * 处理器模式调整首个入参——旧式处理器只收载荷数组，因此接口只是“显式声明”
 * 而非强制要求，新写的处理器建议实现它以便直接读取事件元数据
 * （event_id、trace_id、subject 等）。
 *
 * 处理器由 PluginEventBus 按 guard、filter、observer 等模式调用，返回值
 * 需要符合对应模式的约定：guard 返回布尔判定，filter 返回改写后的载荷，
 * observer 返回 false 或失败状态表示投递失败。返回值不符合约定时由总线
 * 抛 PluginEventInvalidResultException，本接口不做返回值约束。
 */
interface PluginEventHandlerInterface
{
    /**
     * 处理一次插件事件。
     *
     * 每个订阅方只会收到按白名单裁剪过的信封，看不到未声明的载荷字段；
     * 抛出的异常由总线统一分类：PluginEventRejectedException 表示主动拒绝，
     * 其他异常会被包装成拒绝并在同步链路上快速失败。
     *
     * @param PluginEventEnvelope $event 已裁剪的不可变事件信封。
     * @param array<string, mixed> $context 运行上下文（merchant_id、surface、event 等）。
     * @return mixed 处理结果，含义随处理器模式而定。
     */
    public function handle(PluginEventEnvelope $event, array $context = []): mixed;
}
