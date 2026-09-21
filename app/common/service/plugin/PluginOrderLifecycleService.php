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

use Plugin\Hotel\Service\BookingService;

/** 宿主订单事实同步到已成交的插件证据；必须在原订单事务内调用。 */
final class PluginOrderLifecycleService
{
    /**
     * @param array $order 宿主已加锁并更新的订单快照。
     * @param string $action paid/cancelled/refunded。
     * @param string $refundSn 渠道确认的退款单号。
     * @return void 房态失败使宿主状态事务回滚。
     */
    public function sync(array $order, string $action, string $refundSn = ''): void
    {
        if (!app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)) {
            return;
        }
        app()->make(BookingService::class)->syncHostOrder($order, $action, $refundSn);
    }
}
