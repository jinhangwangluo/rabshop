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
 * 同步处理器超出配置的时间预算时抛出的异常。
 *
 * 由 PluginEventBus::assertWithinTimeout() 在处理器返回后按描述符中的
 * timeout_ms 判定抛出：超时说明插件行为已不可控，同步链路上按失败关闭
 * 处理，投递日志归类为 handler_timeout 错误码。
 *
 * 注意异常本身只做判定标记，不负责中断执行——它由总线在调用返回后抛出，
 * 调用方不应依赖它来终止仍在运行的插件代码。
 */
class PluginEventTimeoutException extends PluginEventException
{
}
