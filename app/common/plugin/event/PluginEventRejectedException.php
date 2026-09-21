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
 * 同步 guard 或 filter 拒绝事件时抛出的异常。
 *
 * 这是插件“主动说不”的标准表达：guard 返回 false、filter 返回拒绝动作，
 * 以及 guard/filter 内部抛出其他异常被总线包装（作为 $previous 传入）时，
 * 都会以该异常向上抛出，让宿主在事务内立刻中止本次业务操作。
 *
 * 由 PluginEventBus 抛出，投递日志归类为 handler_rejected 错误码。
 * 构造时提供默认消息，插件侧可只关心上下文而不必重复写文案。
 */
class PluginEventRejectedException extends PluginEventException
{
    /**
     * 构造事件拒绝异常。
     *
     * 默认消息覆盖“被拒绝”这一通用语义，插件只在需要区别原因时才覆盖
     * $message；$context 用于携带事件 id、插件编码等定位信息。
     *
     * @param string $message 拒绝原因描述。
     * @param array<string, mixed> $context 定位上下文，禁止放入敏感数据。
     * @param int $code 异常码，默认 0。
     * @param \Throwable|null $previous 被包装的原始异常（如 guard 内部报错）。
     */
    public function __construct(
        string $message = 'Plugin event rejected',
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $context, $code, $previous);
    }
}
