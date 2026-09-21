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
 * 插件返回值不符合事件处理器契约时抛出的异常。
 *
 * 表示“处理器跑完了但结果不能用”：guard 返回非布尔值、filter 未返回载荷
 * 数组、filter 试图修改宿主字段或其他插件的命名空间、计算器返回重复报价等，
 * 都属于编码错误而非业务拒绝，因此在同步链路上不会被当成正常拒绝处理。
 *
 * 由 PluginEventBus 抛出，并在投递日志中归类为 invalid_result 错误码；
 * 同步 guard/filter 场景下它会导致整条事件失败关闭（fail closed），
 * 不会被静默忽略。
 */
class PluginEventInvalidResultException extends PluginEventException
{
}
