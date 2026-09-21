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

namespace app\common\listener\schedule;

use app\common\enum\OrderConfigKeyEnum;
use app\common\service\order\OrderService;
use app\common\service\order\OrderPaymentCompensationService;

/**
 * 订单循环检查定时任务。
 */
final class OrderCrontabListener
{
    /**
     * 注册订单循环检查。
     *
     * @return void 无返回值；注册订单超时取消、补偿退款与到期权益释放的周期任务。
     */
    public function handle(): void
    {
        $scanSeconds = max(1, (int) db_config(OrderConfigKeyEnum::UNPAID_CANCEL_SCAN_SECONDS, 60));

        app('timer')->tick(
            $scanSeconds * 1000,
            static function (): void {
                app()->make(OrderPaymentCompensationService::class)->retryDue();
            },
            'order_late_payment_compensation',
            '重试已关闭订单的迟到实收退款'
        );

        app('timer')->tick(
            $scanSeconds * 1000,
            static function (): void {
                $result = app()->make(OrderService::class)->cancelExpiredPendingOrders();
                trace(
                    'order cancel expired pending orders executed: ' . json_encode($result, JSON_UNESCAPED_UNICODE),
                    'info'
                );
            },
            'order_cancel_expired_pending_orders',
            '自动取消超时未支付订单'
        );

        app('timer')->tick(
            $scanSeconds * 1000,
            static function (): void {
                $result = app()->make(OrderService::class)->releaseDueFrozenCommissions();
                trace(
                    'order release due frozen commissions executed: ' . json_encode($result, JSON_UNESCAPED_UNICODE),
                    'info'
                );
            },
            'order_release_due_frozen_commissions',
            '释放到期冻结分销佣金'
        );

        app('timer')->tick(
            $scanSeconds * 1000,
            static function (): void {
                $result = app()->make(OrderService::class)->releaseDueGiftPoints();
                trace(
                    'order release due gift points executed: ' . json_encode($result, JSON_UNESCAPED_UNICODE),
                    'info'
                );
            },
            'order_release_due_gift_points',
            '释放到期冻结赠送积分'
        );

        app('timer')->tick(
            $scanSeconds * 1000,
            static function (): void {
                $result = app()->make(OrderService::class)->releaseDueFrozenMerchantFunds();
                trace(
                    'order release due frozen merchant funds executed: ' . json_encode($result, JSON_UNESCAPED_UNICODE),
                    'info'
                );
            },
            'order_release_due_frozen_merchant_funds',
            '释放到期商户冻结资金'
        );
    }
}
