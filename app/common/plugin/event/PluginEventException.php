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
 * 插件事件处理的基础异常。
 *
 * 插件事件链路上所有可识别失败的共同父类：PluginEventBus 用
 * instanceof 把它细分成 handler_timeout / handler_rejected / invalid_result
 * 等错误码写入投递日志，PluginBusinessEventService 也直接抛它表示事件内核
 * 不可用。继承 \RuntimeException，调用方不必强制捕获，交给统一的异常处理
 * 机制兜底即可。
 *
 * 与普通异常的区别是携带 $context：记录事件 id、事件名、插件编码和处理器
 * 名称等定位信息，供日志和排查使用。写入日志前仍会经过错误信息脱敏，
 * 因此 context 中不要放入口令、令牌等敏感数据。
 */
class PluginEventException extends \RuntimeException
{
    /**
     * 异常发生时的定位上下文（事件 id、事件名、插件编码、处理器名等）。
     *
     * @var array<string, mixed>
     */
    protected array $context;

    /**
     * 构造插件事件异常。
     *
     * 消息在前、上下文在后，便于在链路上直接写 throw new ...('原因', [...])；
     * context 只承载排查信息，不参与异常匹配和控制流判断。
     *
     * @param string $message 面向日志的错误描述。
     * @param array<string, mixed> $context 定位上下文，禁止放入敏感数据。
     * @param int $code 异常码，默认 0。
     * @param \Throwable|null $previous 原始异常，用于保留被包裹的失败原因。
     */
    public function __construct(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * 返回异常上下文。
     *
     * 由日志与排查逻辑（例如写入投递日志、拼接错误明细）读取；
     * 返回的是构造时的快照副本，调用方修改它不会影响异常本身。
     *
     * @return array<string, mixed> 定位上下文数据。
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
