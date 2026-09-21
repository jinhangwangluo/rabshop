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

namespace app\common\service\order;

use app\common\enum\OrderConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\service\plugin\PluginCheckoutCapabilityService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 用于展示、支付和取消的统一截止时间解析器。
 */
final class OrderPaymentDeadlineService
{
    /**
     * 解析已固化的截止时间，普通订单沿用原有规则。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param ?int $legacyMinutes 普通历史订单的支付时限；为空时读取现有配置。
     * @return int 订单固定支付截止时间戳；历史创建时间无效时为零。
     */
    public function deadline(array $order, ?int $legacyMinutes = null): int
    {
        if (!empty($order['pay_expire_time'])) {
            $deadline = $this->parseTimestamp(
                (string) $order['pay_expire_time'],
                app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)
            );
            if ($deadline === false) {
                throw new AdminResponseException('订单支付截止时间无效');
            }
            return $deadline;
        }
        $created = $this->parseTimestamp(
            (string) ($order['create_time'] ?? ''),
            app()->make(PluginCheckoutCapabilityService::class)->isHotelOrder($order)
        );
        if ($created === false) {
            return 0;
        }
        $minutes = $legacyMinutes ?? (int) db_config(OrderConfigKeyEnum::UNPAID_CANCEL_MINUTES, 30);
        return $created + max(1, $minutes) * 60;
    }

    /**
     * 返回待支付订单的剩余秒数。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param ?int $now 用于计算剩余时间的当前时间戳；为空时使用服务端时间。
     * @param ?int $legacyMinutes 普通历史订单的支付时限；为空时读取现有配置。
     * @return int 待支付订单剩余秒数，已过期或非待支付状态为零。
     */
    public function remaining(array $order, ?int $now = null, ?int $legacyMinutes = null): int
    {
        if ((int) ($order['status'] ?? Order::STATUS_PENDING) !== Order::STATUS_PENDING) {
            return 0;
        }
        return max(0, $this->deadline($order, $legacyMinutes) - ($now ?? time()));
    }

    /**
     * 收款前在订单锁内检查已固化的截止时间。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param ?int $paidAt 已验证的实际支付时间戳；为空时使用服务端时间。
     * @return void 无返回值；固定支付截止时间已过时抛出异常。
     */
    public function assertPayable(array $order, ?int $paidAt = null): void
    {
        // 普通订单支付行为保持不变，新促销订单固化支付截止时间。
        if (!empty($order['pay_expire_time']) && ($paidAt ?? time()) >= $this->deadline($order)) {
            throw new AdminResponseException('订单已超过支付期限');
        }
    }

    /**
     * 按订单类型解析数据库时间；酒店时间字段没有时区偏移时一律按北京时间解释。
     *
     * @param string $value 数据库时间文本。
     * @param bool $hotel 是否为酒店订单。
     * @return int|false Unix 时间戳，格式非法时返回 false。
     */
    private function parseTimestamp(string $value, bool $hotel): int|false
    {
        if (trim($value) === '') {
            return false;
        }

        if (!$hotel) {
            return strtotime($value);
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('Asia/Shanghai')))->getTimestamp();
        } catch (\Throwable) {
            return false;
        }
    }
}
