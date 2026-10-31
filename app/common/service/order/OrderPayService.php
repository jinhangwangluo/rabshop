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

use app\common\enum\PaymentConfigKeyEnum;
use app\common\enum\PayTypeEnum;
use app\common\enum\WechatConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\message\MessagePushDefinitionService;
use app\common\service\message\MessagePushEventService;
use app\common\service\setting\ConfigService;
use app\common\service\store\StoreRefundProductService;
use app\common\service\user\PlatformAuthService;
use app\common\service\user\UserAssetService;
use Rabbit\service\payment\PaymentInterface;
use Rabbit\service\payment\PaymentService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Throwable;
use think\facade\Log;

/**
 * 订单支付与退款编排服务。
 *
 * 负责统一入口支付分发、第三方支付发起、支付/退款回调验签处理、
 * 退款单与退款商品明细落库，以及订单支付/退款状态同步。
 */
class OrderPayService extends BaseService
{
    /**
     * 设置当前服务对应的订单模型。
     *
     * @return string 模型类名。
     */
    protected function setModel(): string
    {
        return Order::class;
    }

    /**
     * 支付订单统一入口。
     *
     * 根据支付方式分发到余额、微信或支付宝支付；已支付订单会直接返回当前订单状态。
     *
     * @param int $userId 用户标识。
     * @param string $orderSn 订单号。
     * @param string $payType 支付方式，支持微信、支付宝、余额及其别名。
     * @param array<string, mixed> $options 渠道扩展参数，例如微信 trade_type、支付宝 return_url。
     * @return array<string, mixed> 支付发起结果或订单状态载荷。
     * @throws AdminResponseException
     */
    public function pay(int $userId, string $orderSn, string $payType, array $options = []): array
    {
        if ($orderSn === '') {
            throw new AdminResponseException('订单号不能为空');
        }

        $payType = $this->normalizePayType($payType);

        if ($payType === PayTypeEnum::PAY_TYPE_YUE) {
            return app()->make(OrderService::class)->payByBalance($userId, $orderSn);
        }
        if ($payType === PayTypeEnum::PAY_TYPE_WECHAT) {
            return $this->lockedExternalPayment($userId, $orderSn, $payType, $options);
        }
        if ($payType === PayTypeEnum::PAY_TYPE_ALIPAY) {
            return $this->lockedExternalPayment($userId, $orderSn, $payType, $options);
        }

        throw new AdminResponseException('暂不支持该支付方式');
    }

    /**
     * 串行处理支付发起与取消，持久化结果未确认的外部支付请求。
     *
     * @param int $userId 当前业务用户编号。
     * @param string $orderSn 宿主订单的业务单号。
     * @param string $payType 已选择或验证的支付渠道编码。
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @return array 订单锁内确认的已支付状态或原渠道支付发起结果。
     */
    protected function lockedExternalPayment(int $userId, string $orderSn, string $payType, array $options): array
    {
        $result = $this->transaction(function () use ($userId, $orderSn, $payType, $options): array {
            $order = $this->getModel()->where('user_id', $userId)->where('order_sn', $orderSn)->lock(true)->find();
            if (!$order) {
                throw new AdminResponseException('订单不存在');
            }
            if ((int) $order['status'] !== Order::STATUS_PENDING) {
                if (!empty($order['pay_time'])) {
                    return $this->buildOrderStatePayload($order->toArray());
                }
                throw new AdminResponseException('当前订单状态不支持支付');
            }
            app()->make(OrderPaymentDeadlineService::class)->assertPayable($order->toArray());
            $attempt = app()->make(OrderPayRecordService::class)->get(['order_sn' => $orderSn]);
            $tradeType = $payType === PayTypeEnum::PAY_TYPE_WECHAT
                ? $this->normalizeWechatTradeType((string) ($options['trade_type'] ?? 'oa_jsapi')) : 'wap';
            if (!empty($order['pay_expire_time']) && $attempt
                && ((string) $attempt['pay_type'] !== $payType || (string) $attempt['trade_type'] !== $tradeType)) {
                throw new AdminResponseException('活动订单请使用原支付渠道，或取消后重新抢购');
            }
            // 在此捕获异常，确保支付渠道可能已收到的支付发起记录仍能提交。
            try {
                return $payType === PayTypeEnum::PAY_TYPE_WECHAT
                    ? $this->payByWechat($userId, $orderSn, $options)
                    : $this->payByAlipay($userId, $orderSn, $options);
            } catch (Throwable) {
                return ['external_payment_error' => true];
            }
        });
        if (!empty($result['external_payment_error'])) {
            throw new AdminResponseException('支付发起失败，请稍后使用原渠道重试');
        }
        return $result;
    }

    /**
     * 持有宿主订单锁时，关闭支付渠道中待支付的促销订单。
     *
     * @param array $order 已锁定的订单数据。
     * @return bool 仅确认可安全释放库存时返回真；已处理实收款时返回假。
     */
    public function closePromotionPayment(array $order): bool
    {
        if (empty($order['pay_expire_time'])) {
            return true;
        }
        $record = app()->make(OrderPayRecordService::class)->get(['order_sn' => $order['order_sn']]);
        if (!$record || !in_array((string) $record['pay_type'], [PayTypeEnum::PAY_TYPE_WECHAT, PayTypeEnum::PAY_TYPE_ALIPAY], true)) {
            return true;
        }
        $payType = (string) $record['pay_type'];
        $tradeType = (string) $record['trade_type'];
        $driver = $payType === PayTypeEnum::PAY_TYPE_WECHAT
            ? $this->makeWechatPay($this->getWechatPayConfigByTradeType($tradeType)) : $this->makeAlipayPay();
        try {
            $query = $driver->query(['out_trade_no' => (string) $order['order_sn']]);
        } catch (\Rabbit\service\wechat\WechatException $error) {
            if (str_starts_with($error->getMessage(), '[ORDERNOTEXIST]')) {
                return $this->assertAbsentAttemptExpired($order);
            }
            throw new AdminResponseException('支付渠道关单未确认，库存暂不释放，系统将重试');
        }
        if ($payType === PayTypeEnum::PAY_TYPE_ALIPAY && ($query['sub_code'] ?? '') === 'ACQ.TRADE_NOT_EXIST') {
            return $this->assertAbsentAttemptExpired($order);
        }
        $state = strtoupper((string) ($query['trade_state'] ?? $query['trade_status'] ?? ''));
        if (in_array($state, ['SUCCESS', 'TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            $this->acceptPromotionReceipt(
                (string) $order['order_sn'], $payType, $tradeType,
                (string) ($query['transaction_id'] ?? $query['trade_no'] ?? ''),
                (string) ($query['success_time'] ?? $query['send_pay_date'] ?? ''),
                $payType === PayTypeEnum::PAY_TYPE_WECHAT ? (int) ($query['amount']['total'] ?? 0)
                    : $this->yuanToFen((float) ($query['total_amount'] ?? 0)),
                $query,
                true
            );
            return false;
        }
        if (in_array($state, ['CLOSED', 'REVOKED', 'TRADE_CLOSED'], true)) {
            return true;
        }
        if (!in_array($state, ['NOTPAY', 'USERPAYING', 'PAYERROR', 'WAIT_BUYER_PAY'], true)) {
            throw new AdminResponseException('支付渠道状态不明确，库存暂不释放，系统将重试');
        }
        $closed = $driver->close(['out_trade_no' => (string) $order['order_sn']]);
        if ($payType === PayTypeEnum::PAY_TYPE_ALIPAY && (string) ($closed['code'] ?? '') !== '10000') {
            throw new AdminResponseException('支付宝关单未确认，库存暂不释放，系统将重试');
        }
        return true;
    }

    /**
     * 支付发起状态未知时，不能仅因过早收到 NOT_FOUND 响应就释放库存。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return bool 仅超过固定截止时间及安全缓冲期后返回真，否则抛出异常。
     */
    protected function assertAbsentAttemptExpired(array $order): bool
    {
        if (time() < app()->make(OrderPaymentDeadlineService::class)->deadline($order) + 60) {
            throw new AdminResponseException('支付发起状态待确认，库存暂不释放，系统将重试');
        }
        return true;
    }

    /**
     * 将已验证的促销实收凭证与库存预留的支付或释放放在同一原子操作中处理。
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @param string $payType 已选择或验证的支付渠道编码。
     * @param string $tradeType 支付渠道配置类型，区分公众号、小程序和网页。
     * @param string $transactionId 已验证的支付渠道交易标识。
     * @param string $payTime 已验证的渠道实际支付成功时间。
     * @param int $amountFen 已验证的实收金额，单位为分。
     * @param array $raw 已解密或验签的支付渠道原始业务报文。
     * @param bool $verifiedQuery 是否为已验签的主动支付查询结果，而非异步通知。
     * @return array|null 历史普通订单返回空值；其他订单返回已处理的业务状态。
     */
    protected function acceptPromotionReceipt(string $orderSn, string $payType, string $tradeType, string $transactionId, string $payTime, int $amountFen, array $raw, bool $verifiedQuery = false): ?array
    {
        return $this->transaction(function () use ($orderSn, $payType, $tradeType, $transactionId, $payTime, $amountFen, $raw, $verifiedQuery): ?array {
            $order = $this->getModel()->where('order_sn', $orderSn)->lock(true)->find();
            if (!$order) {
                throw new AdminResponseException('订单不存在');
            }
            if (empty($order['pay_expire_time'])) {
                return null;
            }
            $paidAt = $payTime === '' ? false : strtotime($payTime);
            if ($paidAt === false || $paidAt <= 0 || $paidAt > time() + 300 || $transactionId === ''
                || $amountFen !== $this->yuanToFen((float) $order['pay_amount'])) {
                throw new AdminResponseException('活动支付回调凭证或实收金额不一致');
            }
            $records = app()->make(OrderPayRecordService::class);
            $attempt = $records->get(['order_sn' => $orderSn]);
            $sameWechatConfiguration = $payType === PayTypeEnum::PAY_TYPE_WECHAT
                && $tradeType === 'oa_jsapi' && (string) ($attempt['trade_type'] ?? '') === 'h5';
            if (!$attempt || (string) $attempt['pay_type'] !== $payType
                || ((string) $attempt['trade_type'] !== $tradeType && !$sameWechatConfiguration)) {
                throw new AdminResponseException('活动支付渠道与发起记录不一致');
            }
            $tradeType = (string) $attempt['trade_type'];
            $config = $payType === PayTypeEnum::PAY_TYPE_WECHAT ? $this->getWechatPayConfigByTradeType($tradeType) : $this->getAlipayConfig();
            if ($payType === PayTypeEnum::PAY_TYPE_WECHAT) {
                if ((string) ($raw['appid'] ?? '') !== (string) $config['app_id'] || (string) ($raw['mchid'] ?? '') !== (string) $config['mch_id']) {
                    throw new AdminResponseException('活动支付应用或商户身份不一致');
                }
            } elseif ($verifiedQuery) {
                // 已验签的 trade.query 节点不含通知中的 app_id，须校验其自身来源与订单身份。
                if (($raw['_sign_verified'] ?? false) !== true || (string) ($raw['out_trade_no'] ?? '') !== $orderSn) {
                    throw new AdminResponseException('活动支付查询凭证未通过验证');
                }
            } elseif ((string) ($raw['app_id'] ?? '') !== (string) $config['app_id']) {
                throw new AdminResponseException('活动支付应用身份不一致');
            }
            if (!empty($order['pay_time'])) {
                if ((string) ($attempt['transaction_id'] ?? '') !== '' && (string) $attempt['transaction_id'] !== $transactionId) {
                    throw new AdminResponseException('活动支付实收凭证冲突');
                }
                return ['order_sn' => $orderSn, 'receipt_state' => 'paid'];
            }
            if ((int) $order['status'] === Order::STATUS_PENDING
                && $paidAt < app()->make(OrderPaymentDeadlineService::class)->deadline($order->toArray())) {
                app()->make(OrderService::class)->markOrderPaid((int) $order['id'], $payType, $paidAt);
                $records->markPaid($orderSn, ['transaction_id' => $transactionId, 'pay_time' => date('Y-m-d H:i:s', $paidAt), 'notify_payload' => $raw]);
                return ['order_sn' => $orderSn, 'receipt_state' => 'paid'];
            }
            if ((int) $order['status'] === Order::STATUS_PENDING) {
                app()->make(OrderService::class)->rejectExpiredReceipt((int) $order['id']);
            } elseif ((int) $order['status'] !== Order::STATUS_CANCELLED) {
                throw new AdminResponseException('活动订单状态不能接受此实收凭证');
            }
            $compensation = app()->make(OrderPaymentCompensationService::class)->register($order->toArray(), $payType, $tradeType, $transactionId, $amountFen);
            $records->update((int) $attempt['id'], [
                'status' => OrderPayRecordService::STATUS_COMPENSATING, 'transaction_id' => $transactionId,
                'pay_time' => date('Y-m-d H:i:s', $paidAt), 'notify_payload' => $raw,
                'error_message' => '订单已关闭，实收进入幂等退款补偿',
            ]);
            return ['order_sn' => $orderSn, 'receipt_state' => 'compensating', 'refund_sn' => $compensation['refund_sn']];
        });
    }

    /**
     * 使用支付渠道固定的幂等退款单号，退回被拒收的实收款。
     *
     * @param array $record 已加载的退款或永久实收补偿记录。
     * @return bool 支付渠道是否已确认固定补偿退款成功。
     */
    public function refundRejectedReceipt(array $record): bool
    {
        $wechat = $record['pay_type'] === PayTypeEnum::PAY_TYPE_WECHAT;
        $driver = $wechat ? $this->makeWechatPay($this->getWechatPayConfigByTradeType((string) $record['trade_type'])) : $this->makeAlipayPay();
        if ((int) $record['attempts'] > 0) {
            try {
                $query = $driver->queryRefund(['out_refund_no' => $record['refund_sn'], 'out_trade_no' => $record['order_sn']]);
            } catch (\Rabbit\service\wechat\WechatException $error) {
                if (!preg_match('/^\[(RESOURCE_NOT_EXISTS|REFUNDNOTEXIST)\]/', $error->getMessage())) {
                    throw $error;
                }
                $query = [];
            }
            if ($wechat && strtoupper((string) ($query['status'] ?? '')) === 'SUCCESS') {
                return true;
            }
            if (!$wechat && (string) ($query['code'] ?? '') === '10000' && (string) ($query['refund_status'] ?? '') === 'REFUND_SUCCESS') {
                return true;
            }
            if ($wechat && strtoupper((string) ($query['status'] ?? '')) === 'PROCESSING') {
                return false;
            }
        }
        $payload = [
            'out_trade_no' => $record['order_sn'], 'transaction_id' => $record['transaction_id'],
            'out_refund_no' => $record['refund_sn'], 'refund_amount_fen' => (int) $record['amount_fen'],
            'total_amount_fen' => (int) $record['amount_fen'],
            'refund_amount' => (float) bcdiv((string) $record['amount_fen'], '100', 2),
            'reason' => '订单关闭后迟到实收补偿',
        ];
        if ($wechat) {
            $config = $this->getWechatPayConfigByTradeType((string) $record['trade_type']);
            $payload['notify_url'] = $this->resolveNotifyUrl((string) ($config['refund_notify_url'] ?? ''), '/api/mobile/order/wechatRefundNotify');
        }
        $result = $driver->refund($payload);
        return $wechat ? strtoupper((string) ($result['status'] ?? '')) === 'SUCCESS' : (string) ($result['code'] ?? '') === '10000';
    }

    /**
     * 用户发起退款申请。
     *
     * 会校验订单归属与状态，并创建退款主单及退款商品明细；实际退款仍按渠道流程处理。
     *
     * @param int $userId 用户标识。
     * @param string $orderSn 订单号。
     * @param string $reason 退款原因，为空时使用默认原因。
     * @param array<int, array<string, mixed>> $items 指定退款商品明细；为空时按整单退款处理。
     * @return array<string, mixed> 退款申请处理结果。
     * @throws AdminResponseException
     */
    public function refund(int $userId, string $orderSn, string $reason = '', array $items = []): array
    {
        $order = app()->make(OrderService::class)->getUserOrderByOrderSn($userId, $orderSn);
        if (app()->make(\app\common\service\plugin\PluginCheckoutCapabilityService::class)->isHotelOrder($order)) {
            throw new AdminResponseException('酒店预订请在预订详情申请取消');
        }
        $status = (int) ($order['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int) ($order['refund_status'] ?? 0);
        $refundAmount = (float) ($order['pay_amount'] ?? 0);
        if ((int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_MAIN) {
            throw new AdminResponseException('主支付单请在子订单中申请退款');
        }

        if ($refundStatus === 2 || $status === Order::STATUS_REFUNDED) {
            return [
                'order_sn' => $orderSn,
                'refund_sn' => '',
                'refund_status' => 2,
                'refund_amount' => $refundAmount,
                'status_text' => '已退款',
            ];
        }

        if (!in_array($status, $this->refundableStatuses(), true)) {
            throw new AdminResponseException('当前订单状态不支持退款');
        }

        $refundReason = trim($reason) !== '' ? trim($reason) : '用户申请退款';

        return $this->processRefund($order, get_sn('RF'), $refundAmount, $refundReason, '', false, $items);
    }

    /**
     * 平台审核通过退款后执行实际退款。
     *
     * 优先复用待处理退款记录的退款单号，避免审核通过时重复生成退款单。
     *
     * @param int $orderId 订单标识。
     * @return array<string, mixed> 退款执行结果。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws AdminResponseException
     */
    public function approveRefund(int $orderId, bool $hotelCancellation = false): array
    {
        $orderModel = app()->make(OrderService::class)->get($orderId);
        if (!$orderModel) {
            throw new AdminResponseException('订单不存在');
        }

        $order = $orderModel->toArray();
        if (app()->make(\app\common\service\plugin\PluginCheckoutCapabilityService::class)->isHotelOrder($order)
            && !$hotelCancellation) {
            throw new AdminResponseException('酒店取消请在酒店预订审核页面处理');
        }
        $status = (int) ($order['status'] ?? Order::STATUS_PENDING);
        $refundStatus = (int) ($order['refund_status'] ?? 0);
        $orderSn = (string) ($order['order_sn'] ?? '');
        $payAmount = $this->normalizeMoney($order['pay_amount'] ?? 0);
        $refundAmount = $this->normalizeMoney($order['refund_amount'] ?? 0);
        $refundAmount = $refundAmount > 0
            ? ($payAmount > 0 ? $this->moneyMin($refundAmount, $payAmount) : $refundAmount)
            : $payAmount;
        $refundReason = trim((string) ($order['refund_reason'] ?? ''));

        if ($refundStatus === 2 || $status === Order::STATUS_REFUNDED) {
            return [
                'order_sn' => $orderSn,
                'refund_sn' => '',
                'refund_status' => 2,
                'refund_amount' => $refundAmount,
                'status_text' => '已退款',
            ];
        }

        if (!in_array($status, [Order::STATUS_REFUNDING, Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW], true)
            && $refundStatus !== 1
        ) {
            throw new AdminResponseException('当前订单状态不支持退款审核通过');
        }

        if ($orderSn === '') {
            throw new AdminResponseException('订单号不能为空');
        }

        $pendingRecord = app()->make(OrderRefundService::class)->getPendingByOrderSn($orderSn);
        $refundReason = $refundReason !== '' ? $refundReason : '平台审核通过退款';

        return $this->processRefund(
            $order,
            $pendingRecord ? (string)($pendingRecord['refund_sn'] ?? '') : get_sn('RF'),
            $refundAmount,
            $refundReason,
            'admin_approve',
            true,
            []
        );
    }

    /**
     * 执行退款核心流程。
     *
     * 在事务内锁定订单，处理退款主单、退款商品明细、渠道退款调用和订单状态同步。
     * 微信、支付宝会调用支付渠道退款；余额、免费订单和其它非微信渠道按本地结果直接完成。
     *
     * @param array<string, mixed> $order 订单快照。
     * @param string $refundSn 退款单号；为空时自动生成或复用待处理记录单号。
     * @param float $refundAmount 申请退款金额。
     * @param string $refundReason 退款原因。
     * @param string $source 退款来源标识，例如 admin_approve。
     * @param bool $reusePending 是否复用已有待处理退款记录继续执行。
     * @param array<int, array<string, mixed>> $items 退款商品明细；为空时按已有明细或整单计算。
     * @return array<string, mixed> 退款状态载荷。
     * @throws AdminResponseException
     */
    protected function processRefund(
        array $order,
        string $refundSn,
        float $refundAmount,
        string $refundReason,
        string $source = '',
        bool $reusePending = false,
        array $items = []
    ): array
    {
        $orderSn = (string) ($order['order_sn'] ?? '');
        $userId = (int) ($order['user_id'] ?? 0);

        if ($orderSn === '') {
            throw new AdminResponseException('订单号不能为空');
        }

        return $this->transaction(function () use (
            $order,
            $orderSn,
            $userId,
            $refundSn,
            $refundAmount,
            $refundReason,
            $source,
            $reusePending,
            $items
        ) {
            $orderService = app()->make(OrderService::class);
            $refundRecordService = app()->make(OrderRefundService::class);

            $lockedOrder = $this->getModel()
                ->where('order_sn', $orderSn)
                ->lock(true)
                ->find();
            if (!$lockedOrder) {
                throw new AdminResponseException('订单不存在');
            }

            $lockedOrderData = $lockedOrder->toArray();
            $status = (int) ($lockedOrderData['status'] ?? Order::STATUS_PENDING);
            $refundStatus = (int) ($lockedOrderData['refund_status'] ?? 0);
            $payAmount = $this->normalizeMoney($lockedOrderData['pay_amount'] ?? 0);
            $refundAmount = $this->normalizeMoney($refundAmount);
            $refundAmount = $refundAmount > 0
                ? ($payAmount > 0 ? $this->moneyMin($refundAmount, $payAmount) : $refundAmount)
                : $payAmount;

            if ($refundStatus === 2 || $status === Order::STATUS_REFUNDED) {
                return [
                    'order_sn' => $orderSn,
                    'refund_sn' => '',
                    'refund_status' => 2,
                    'refund_amount' => $refundAmount,
                    'status_text' => '已退款',
                ];
            }

            $pendingRecord = $refundRecordService->getPendingByOrderSn($orderSn);
            if ($pendingRecord && !$reusePending) {
                return [
                    'order_sn' => $orderSn,
                    'refund_sn' => (string) ($pendingRecord['refund_sn'] ?? ''),
                    'refund_status' => 1,
                    'refund_amount' => (float) ($pendingRecord['refund_amount'] ?? $refundAmount),
                    'status_text' => '退款处理中',
                ];
            }

            if (!in_array($status, [Order::STATUS_REFUNDING, Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW], true)
                && $refundStatus !== 1
            ) {
                throw new AdminResponseException('当前订单状态不支持退款');
            }

            // 待审核退款在审核通过阶段应复用原退款单号，避免渠道侧重复退款单。
            $payType = $this->normalizePayType((string) ($lockedOrderData['pay_type'] ?? ''));
            $currentUserId = (int) ($lockedOrderData['user_id'] ?? $userId);
            if ($refundSn === '') {
                $refundSn = $pendingRecord ? (string)($pendingRecord['refund_sn'] ?? '') : get_sn('RF');
            }

            // 拆单退款以 store_refund_product 明细为准；已有明细时优先复用，避免重复生成退款商品。
            $refundProductService = app()->make(StoreRefundProductService::class);
            $hasExistingRefundProducts = $refundProductService->hasByRefundSn($refundSn);
            if ($refundStatus === 3 && !$pendingRecord && $items === []) {
                $refundAmount = 0;
            }
            if ($pendingRecord && $hasExistingRefundProducts && $items === []) {
                $refundAmount = (float)($pendingRecord['refund_amount'] ?? $refundAmount);
            } else {
                $refundPreview = $refundProductService->previewRefundProducts(
                    $lockedOrderData,
                    $items,
                    $refundAmount,
                    $refundSn
                );
                $refundAmount = (float)($refundPreview['refund_amount'] ?? 0);
            }

            $recordPayload = [
                'order_id' => (int) ($lockedOrderData['id'] ?? 0),
                'order_sn' => $orderSn,
                'refund_sn' => $refundSn,
                'user_id' => $currentUserId,
                'pay_type' => $payType,
                'refund_amount' => $refundAmount,
                'reason' => $refundReason,
                'transaction_id' => $this->resolveTransactionId($orderSn),
                'request_payload' => [
                    'order_sn' => $orderSn,
                    'refund_sn' => $refundSn,
                    'refund_amount' => $refundAmount,
                    'reason' => $refundReason,
                ],
            ];
            if ($source !== '') {
                $recordPayload['request_payload']['source'] = $source;
            }

            $refundRecord = $this->saveRefundRecord($refundRecordService, $pendingRecord, $recordPayload);
            if (!($hasExistingRefundProducts && $items === [])) {
                $refundProductService->createOrUpdateForRefund(
                    $lockedOrderData,
                    $refundRecord,
                    $items,
                    $refundAmount
                );
            }

            // 余额、免费订单和零金额退款不需要调用三方渠道，直接完成本地资金和订单状态处理。
            if ($payType === PayTypeEnum::PAY_TYPE_YUE || $payType === PayTypeEnum::PAY_TYPE_FREE || $refundAmount <= 0) {
                if ($payType === PayTypeEnum::PAY_TYPE_YUE && $refundAmount > 0) {
                    app()->make(UserAssetService::class)->changeAsset(
                        $currentUserId,
                        'balance',
                        $refundAmount,
                        'in',
                        '订单退款退回余额',
                        [
                            'source_type' => 'order_refund_balance',
                            'order_no' => $orderSn,
                            'source_desc' => '订单退款退回余额',
                        ]
                    );
                }

                $this->saveRefundRecord($refundRecordService, $refundRecord, array_merge($recordPayload, [
                    'status' => OrderRefundService::STATUS_SUCCESS,
                    'refund_time' => date('Y-m-d H:i:s'),
                    'notify_time' => date('Y-m-d H:i:s'),
                    'response_payload' => ['channel' => $payType, 'status' => 'SUCCESS'],
                    'notify_payload' => ['channel' => $payType, 'status' => 'SUCCESS'],
                ]));
                $refundResult = $orderService->markRefundedOrder($lockedOrder, $refundAmount, $refundReason, $refundSn);
                $this->notifyRefundChanged($lockedOrderData, $refundAmount, $refundReason, $refundSn, $source);
                $this->pushRefundSuccess($refundResult ?: $lockedOrderData, $refundAmount, $refundReason, $refundSn);

                return [
                    'order_sn' => $orderSn,
                    'refund_sn' => $refundSn,
                    'refund_status' => (int)($refundResult['refund_status'] ?? 2),
                    'refund_amount' => (float)($refundResult['refund_amount'] ?? $refundAmount),
                    'status_text' => (int)($refundResult['refund_status'] ?? 2) === 3 ? '部分退款' : '已退款',
                ];
            }

            if ($payType === PayTypeEnum::PAY_TYPE_ALIPAY) {
                $refundStartAt = microtime(true);
                $transactionId = (string)($recordPayload['transaction_id'] ?? '');
                $requestPayload = [
                    'out_trade_no' => $orderSn,
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $refundReason,
                    'out_request_no' => $refundSn,
                ];
                if ($transactionId !== '') {
                    $requestPayload['trade_no'] = $transactionId;
                }

                try {
                    $response = $this->makeAlipayPay($this->getAlipayConfig())->refund([
                        'out_trade_no' => $orderSn,
                        'transaction_id' => $transactionId,
                        'out_refund_no' => $refundSn,
                        'refund_amount' => $refundAmount,
                        'reason' => $refundReason,
                    ]);
                } catch (Throwable $throwable) {
                    throw new AdminResponseException($throwable->getMessage() ?: '支付宝退款失败');
                }

                if ((bool)($response['_success'] ?? false)) {
                    $this->saveRefundRecord($refundRecordService, $refundRecord, array_merge($recordPayload, [
                        'status' => OrderRefundService::STATUS_SUCCESS,
                        'refund_id' => (string)($response['trade_no'] ?? $transactionId),
                        'refund_time' => $this->resolveAlipaySuccessTime((string)($response['gmt_refund_pay'] ?? '')),
                        'notify_time' => date('Y-m-d H:i:s'),
                        'request_payload' => $requestPayload,
                        'response_payload' => $response,
                        'notify_payload' => ['channel' => $payType, 'status' => 'SUCCESS'],
                    ]));
                    $refundResult = $orderService->markRefundedOrder($lockedOrder, $refundAmount, $refundReason, $refundSn);
                    $this->notifyRefundChanged($lockedOrderData, $refundAmount, $refundReason, $refundSn, $source);
                    $this->pushRefundSuccess($refundResult ?: $lockedOrderData, $refundAmount, $refundReason, $refundSn);

                    return [
                        'order_sn' => $orderSn,
                        'refund_sn' => $refundSn,
                        'refund_status' => (int)($refundResult['refund_status'] ?? 2),
                        'refund_amount' => (float)($refundResult['refund_amount'] ?? $refundAmount),
                        'status_text' => (int)($refundResult['refund_status'] ?? 2) === 3 ? '部分退款' : '已退款',
                    ];
                }

                throw new AdminResponseException('支付宝退款申请失败：' . $this->resolveAlipayErrorMessage($response));
            }

            // 其它非微信渠道当前按人工成功路径处理，保留退款记录便于后续追踪。
            if ($payType !== PayTypeEnum::PAY_TYPE_WECHAT) {
                $this->saveRefundRecord($refundRecordService, $refundRecord, array_merge($recordPayload, [
                    'status' => OrderRefundService::STATUS_SUCCESS,
                    'refund_time' => date('Y-m-d H:i:s'),
                    'notify_time' => date('Y-m-d H:i:s'),
                    'response_payload' => ['channel' => $payType, 'status' => 'MANUAL_SUCCESS'],
                    'notify_payload' => ['channel' => $payType, 'status' => 'MANUAL_SUCCESS'],
                ]));
                $refundResult = $orderService->markRefundedOrder($lockedOrder, $refundAmount, $refundReason, $refundSn);
                $this->notifyRefundChanged($lockedOrderData, $refundAmount, $refundReason, $refundSn, $source);
                $this->pushRefundSuccess($refundResult ?: $lockedOrderData, $refundAmount, $refundReason, $refundSn);

                return [
                    'order_sn' => $orderSn,
                    'refund_sn' => $refundSn,
                    'refund_status' => (int)($refundResult['refund_status'] ?? 2),
                    'refund_amount' => (float)($refundResult['refund_amount'] ?? $refundAmount),
                    'status_text' => (int)($refundResult['refund_status'] ?? 2) === 3 ? '部分退款' : '已退款',
                ];
            }

            $refundStartAt = microtime(true);
            $requestPayload = [];
            $wechatConfigLog = [];
            try {
                // 微信退款需要根据原支付交易类型选择对应 appid 和商户配置。
                $wechatConfig = $this->getWechatRefundConfig($lockedOrderData ?: $order);
                $wechatConfigLog = [
                    'appid' => (string) ($wechatConfig['app_id'] ?? ''),
                    'mchid' => (string) ($wechatConfig['mch_id'] ?? ''),
                    'has_public_key_id' => trim((string) ($wechatConfig['public_key_id'] ?? '')) !== '',
                    'has_public_key' => trim((string) ($wechatConfig['public_key'] ?? '')) !== '',
                    'has_mch_serial_no' => trim((string) ($wechatConfig['mch_serial_no'] ?? '')) !== '',
                    'has_cert' => trim((string) ($wechatConfig['cert'] ?? '')) !== '',
                    'has_ssl_key' => trim((string) ($wechatConfig['ssl_key'] ?? '')) !== '',
                ];
                $refundNotifyUrl = $this->resolveNotifyUrl(
                    (string) ($wechatConfig['refund_notify_url'] ?? ''),
                    '/api/mobile/order/wechatRefundNotify'
                );
                $wechatPay = $this->makeWechatPay($wechatConfig);
                $totalFee = $this->yuanToFen($payAmount);
                $refundFee = $this->yuanToFen($refundAmount);
                $requestPayload = [
                    'out_trade_no' => $orderSn,
                    'out_refund_no' => $refundSn,
                    'amount' => [
                        'total' => $totalFee,
                        'refund' => $refundFee,
                        'currency' => 'CNY',
                    ],
                    'reason' => $refundReason,
                    'notify_url' => $refundNotifyUrl,
                    'appid' => (string) ($wechatConfig['app_id'] ?? ''),
                    'mchid' => (string) ($wechatConfig['mch_id'] ?? ''),
                ];

                $response = $wechatPay->refund([
                    'out_trade_no' => $orderSn,
                    'out_refund_no' => $refundSn,
                    'total_amount' => $payAmount,
                    'refund_amount' => $refundAmount,
                    'reason' => $refundReason,
                    'notify_url' => $refundNotifyUrl,
                ]);

            } catch (Throwable $throwable) {
                throw new AdminResponseException($throwable->getMessage() ?: '退款失败');
            }

            $refundStatusText = strtoupper((string) ($response['status'] ?? 'PROCESSING'));
            if ($refundStatusText === 'SUCCESS') {
                $this->saveRefundRecord($refundRecordService, $refundRecord, array_merge($recordPayload, [
                    'status' => OrderRefundService::STATUS_SUCCESS,
                    'refund_id' => (string) ($response['refund_id'] ?? ''),
                    'refund_time' => date('Y-m-d H:i:s'),
                    'notify_time' => date('Y-m-d H:i:s'),
                    'response_payload' => $response,
                ]));
                $refundResult = $orderService->markRefundedOrder($lockedOrder, $refundAmount, $refundReason, $refundSn);
                $this->notifyRefundChanged($lockedOrderData, $refundAmount, $refundReason, $refundSn, $source);
                $this->pushRefundSuccess($refundResult ?: $lockedOrderData, $refundAmount, $refundReason, $refundSn);

                return [
                    'order_sn' => $orderSn,
                    'refund_sn' => $refundSn,
                    'refund_status' => (int)($refundResult['refund_status'] ?? 2),
                    'refund_amount' => (float)($refundResult['refund_amount'] ?? $refundAmount),
                    'status_text' => (int)($refundResult['refund_status'] ?? 2) === 3 ? '部分退款' : '已退款',
                ];
            }

            if (!in_array($refundStatusText, ['PROCESSING', 'CHANGE'], true)) {
                throw new AdminResponseException('微信退款申请失败');
            }

            $this->saveRefundRecord($refundRecordService, $refundRecord, array_merge($recordPayload, [
                'response_payload' => $response,
            ]));
            if ($refundStatus !== 1 && $status !== Order::STATUS_REFUNDING) {
                $orderService->markRefundingOrder($lockedOrder, $refundAmount, $refundReason);
            }
            $this->notifyRefundChanged($lockedOrderData, $refundAmount, $refundReason, $refundSn, $source);

            return [
                'order_sn' => $orderSn,
                'refund_sn' => $refundSn,
                'refund_status' => 1,
                'refund_amount' => $refundAmount,
                'status_text' => '退款处理中',
            ];
        });
    }

    /**
     * 发送退款后台消息。
     *
     * @param array<string, mixed> $order 订单快照。
     * @param float $refundAmount 退款金额。
     * @param string $refundReason 退款原因。
     * @param string $refundSn 退款单号。
     * @param string $source 退款发起来源，用于区分用户申请与平台审核。
     * @return void 无返回值；通知后台本次退款状态及业务原因。
     */
    protected function notifyRefundChanged(
        array $order,
        float $refundAmount,
        string $refundReason,
        string $refundSn,
        string $source = ''
    ): void
    {
        $payload = array_merge($order, [
            'refund_amount' => $refundAmount,
            'refund_reason' => $refundReason,
            'refund_sn' => $refundSn,
        ]);

        if ($source === 'admin_approve') {
            app()->make(BackendMessageEventService::class)->refundApproved($payload);
            return;
        }

        app()->make(BackendMessageEventService::class)->refundApplied($payload);
    }

    /**
     * 推送退款成功通知。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundReason 本次退款原因。
     * @param string $refundSn 固定的退款业务单号。
     * @return void 无返回值；向用户推送已确认成功的退款结果。
     */
    protected function pushRefundSuccess(array $order, float $refundAmount, string $refundReason, string $refundSn): void
    {
        app()->make(MessagePushEventService::class)->dispatch(
            MessagePushDefinitionService::EVENT_REFUND_SUCCESS,
            array_merge($order, [
                'refund_amount' => $refundAmount,
                'refund_reason' => $refundReason,
                'refund_sn' => $refundSn,
                'relation_type' => 'order',
                'relation_id' => (int)($order['id'] ?? $order['order_id'] ?? 0),
            ])
        );
    }

    /**
     * 推送退款失败通知。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param float $refundAmount 本次申请或确认退款的金额，单位为元。
     * @param string $refundReason 本次退款原因。
     * @param string $refundSn 固定的退款业务单号。
     * @param string $failReason 支付渠道确认的退款失败原因。
     * @return void 无返回值；向用户推送退款失败原因。
     */
    protected function pushRefundFailed(
        array $order,
        float $refundAmount,
        string $refundReason,
        string $refundSn,
        string $failReason
    ): void {
        app()->make(MessagePushEventService::class)->dispatch(
            MessagePushDefinitionService::EVENT_REFUND_FAILED,
            array_merge($order, [
                'refund_amount' => $refundAmount,
                'refund_reason' => $refundReason,
                'refund_sn' => $refundSn,
                'fail_reason' => $failReason,
                'relation_type' => 'order',
                'relation_id' => (int)($order['id'] ?? $order['order_id'] ?? 0),
            ])
        );
    }

    /**
     * 创建或更新退款记录。
     *
     * @param OrderRefundService $service 退款记录服务。
     * @param array<string, mixed>|null $pendingRecord 待处理退款记录。
     * @param array<string, mixed> $payload 退款记录数据。
     * @return array<string, mixed> 最新退款记录数据。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function saveRefundRecord(OrderRefundService $service, ?array $pendingRecord, array $payload): array
    {
        if ($pendingRecord && (int)($pendingRecord['id'] ?? 0) > 0) {
            $service->update((int)$pendingRecord['id'], $payload);
            $fresh = $service->get((int)$pendingRecord['id']);

            return $fresh ? $fresh->toArray() : array_merge($pendingRecord, $payload);
        }

        return $service->createRecord($payload);
    }

    /**
     * 处理微信支付回调。
     *
     * 自动尝试公众号和小程序支付配置验签，支付成功后同步订单与支付记录。
     *
     * @param string $rawBody 微信回调原始报文。
     * @param array<string, mixed> $headers 微信回调请求头。
     * @return array<string, mixed> 回调处理结果。
     * @throws AdminResponseException
     */
    public function handleWechatPayNotify(string $rawBody, array $headers): array
    {
        [$result, $configType] = $this->decryptWechatNotify($rawBody, $headers, false);
        $orderSn = (string) ($result['out_trade_no'] ?? '');
        $transactionId = (string) ($result['transaction_id'] ?? '');
        $tradeState = strtoupper((string) ($result['trade_status'] ?? ''));
        $rawPayload = is_array($result['raw'] ?? null) ? $result['raw'] : $result;

        if ($orderSn === '') {
            throw new AdminResponseException('支付回调缺少订单号');
        }

        if (!($result['is_success'] ?? false)) {

            app()->make(OrderPayRecordService::class)->markFailed($orderSn, '微信支付未成功', [
                'response_payload' => $rawPayload,
            ]);

            return [
                'order_sn' => $orderSn,
                'trade_type' => $configType,
                'trade_state' => $tradeState,
            ];
        }

        $payTime = $this->resolveWechatSuccessTime((string) ($result['pay_time'] ?? ''));
        $promotionReceipt = $this->acceptPromotionReceipt($orderSn, PayTypeEnum::PAY_TYPE_WECHAT, $configType, $transactionId,
            (string) ($result['pay_time'] ?? ''), (int) ($rawPayload['amount']['total'] ?? 0), $rawPayload);
        if ($promotionReceipt !== null) {
            return array_merge($promotionReceipt, ['trade_type' => $configType, 'trade_state' => $tradeState]);
        }

        $paidOrder = app()->make(OrderService::class)->markOrderPaidByOrderSn(
            $orderSn,
            PayTypeEnum::PAY_TYPE_WECHAT,
            strtotime($payTime) ?: null
        );

        app()->make(OrderPayRecordService::class)->markPaid($orderSn, [
            'transaction_id' => $transactionId,
            'pay_time' => $payTime,
            'notify_payload' => $rawPayload,
            'response_payload' => $rawPayload,
        ]);

        return [
            'order_sn' => $orderSn,
            'trade_type' => $configType,
            'trade_state' => $tradeState,
        ];
    }

    /**
     * 处理微信退款回调。
     *
     * 退款成功时同步订单退款状态；退款异常或关闭时标记退款失败并回滚订单退款中状态。
     *
     * @param string $rawBody 微信退款回调原始报文。
     * @param array<string, mixed> $headers 微信回调请求头。
     * @return array<string, mixed> 回调处理结果。
     * @throws AdminResponseException
     */
    public function handleWechatRefundNotify(string $rawBody, array $headers): array
    {
        [$result, $configType] = $this->decryptWechatNotify($rawBody, $headers, true);
        $refundSn = (string) ($result['out_refund_no'] ?? '');
        $refundStatus = strtoupper((string) ($result['refund_status'] ?? ''));
        $rawPayload = is_array($result['raw'] ?? null) ? $result['raw'] : $result;
        if ($refundSn === '') {
            throw new AdminResponseException('退款回调缺少退款单号');
        }

        $compensation = app()->make(OrderPaymentCompensationService::class)->acceptWechatRefundNotification(
            $refundSn, $configType, $rawPayload, $this->getWechatPayConfigByTradeType($configType)
        );
        if ($compensation !== null) {
            return $compensation;
        }

        $refundRecordService = app()->make(OrderRefundService::class);
        $orderService = app()->make(OrderService::class);
        $refundRecord = $refundRecordService->getByRefundSn($refundSn);
        if ($refundRecord === null) {
            throw new AdminResponseException('退款记录不存在');
        }

        $orderSn = (string) ($refundRecord['order_sn'] ?? '');
        $refundAmount = (float) ($refundRecord['refund_amount'] ?? 0);
        $refundReason = (string) ($refundRecord['reason'] ?? '');

        if ($refundStatus === 'SUCCESS') {
            $refundResult = $orderService->markOrderRefundedByOrderSn($orderSn, $refundAmount, $refundReason, $refundSn);
            $refundRecordService->markSuccess($refundSn, [
                'refund_id' => (string) ($result['refund_id'] ?? ''),
                'notify_payload' => $rawPayload,
                'response_payload' => $rawPayload,
            ]);
            $this->pushRefundSuccess($refundResult, $refundAmount, $refundReason, $refundSn);
        } elseif (in_array($refundStatus, ['ABNORMAL', 'CLOSED'], true)) {
            $refundRecordService->markFailed($refundSn, '微信退款失败', [
                'notify_payload' => $rawPayload,
                'response_payload' => $rawPayload,
            ]);
            $orderService->resetRefundStatusByOrderSn($orderSn, $refundSn);
            $order = $orderService->getByOrderSn($orderSn) ?? ['order_sn' => $orderSn];
            $this->pushRefundFailed($order, $refundAmount, $refundReason, $refundSn, '微信退款失败');
        }

        return [
            'refund_sn' => $refundSn,
            'trade_type' => $configType,
            'refund_status' => $refundStatus,
        ];
    }

    /**
     * 发起微信支付。
     *
     * 支持公众号 JSAPI、小程序 JSAPI 和 H5 支付；支付金额为 0 时直接标记为免费支付成功。
     *
     * @param int $userId 用户标识。
     * @param string $orderSn 订单号。
     * @param array<string, mixed> $options 支付扩展参数，支持 trade_type。
     * @return array<string, mixed> 前端调起微信支付所需配置或订单状态。
     * @throws AdminResponseException
     */
    protected function payByWechat(int $userId, string $orderSn, array $options = []): array
    {
        $orderService = app()->make(OrderService::class);
        $order = $orderService->getUserOrderByOrderSn($userId, $orderSn);
        $status = (int) ($order['status'] ?? Order::STATUS_PENDING);
        if (in_array($status, $this->paidStatuses(), true)) {
            return $this->buildOrderStatePayload($order);
        }
        if ($status !== Order::STATUS_PENDING) {
            throw new AdminResponseException('当前订单状态不支持支付');
        }
        $this->assertNotSplitChildPayOrder($order);

        $payAmount = (float) ($order['pay_amount'] ?? 0);
        if ($payAmount <= 0) {
            return $orderService->markOrderPaidByOrderSn($orderSn, PayTypeEnum::PAY_TYPE_FREE);
        }

        $tradeType = $this->normalizeWechatTradeType((string) ($options['trade_type'] ?? 'oa_jsapi'));
        $openid = $tradeType === 'h5' ? '' : $this->resolveWechatOpenid($userId, $tradeType);
        $body = $this->buildOrderDescription($order);
        $wechatConfig = $this->getWechatPayConfigByTradeType($tradeType);
        $notifyUrl = $this->resolveNotifyUrl((string) ($wechatConfig['notify_url'] ?? ''), '/api/mobile/order/wechatNotify');
        $wechatPay = $this->makeWechatPay($wechatConfig);
        $amountFen = $this->yuanToFen($payAmount);
        $requestPayload = [
            'trade_type' => $tradeType,
            'appid' => (string)($wechatConfig['app_id'] ?? ''),
            'mchid' => (string)($wechatConfig['mch_id'] ?? ''),
            'description' => $body,
            'out_trade_no' => $orderSn,
            'notify_url' => $notifyUrl,
            'amount' => [
                'total' => $amountFen,
                'currency' => 'CNY',
            ],
        ];
        if (!empty($order['pay_expire_time'])) {
            $requestPayload['time_expire'] = date(DATE_ATOM, app()->make(OrderPaymentDeadlineService::class)->deadline($order));
        }
        if ($tradeType === 'h5') {
            $requestPayload['scene_info'] = [
                'payer_client_ip' => (string)request()->ip(),
                'h5_info' => ['type' => 'Wap'],
            ];
        } else {
            $requestPayload['payer'] = ['openid' => $openid];
        }

        Log::info($this->formatPaymentLogMessage('[order-pay] wechat payment parameters', [
            'order_sn' => $orderSn,
            'trade_type' => $tradeType,
            'request_payload' => $this->sanitizePaymentLogData($requestPayload),
        ]));

        $this->savePaymentIntent($order, PayTypeEnum::PAY_TYPE_WECHAT, $tradeType, $requestPayload);
        try {
            $response = $wechatPay->pay([
                'trade_type' => $tradeType,
                'out_trade_no' => $orderSn,
                'amount' => $payAmount,
                'subject' => $body,
                'body' => $body,
                'notify_url' => $notifyUrl,
                'openid' => $openid,
                'client_ip' => (string)request()->ip(),
                'time_expire' => $requestPayload['time_expire'] ?? '',
            ]);

            app()->make(OrderPayRecordService::class)->saveInitiatedRecord([
                'order_id' => (int) ($order['id'] ?? 0),
                'order_sn' => $orderSn,
                'user_id' => $userId,
                'pay_type' => PayTypeEnum::PAY_TYPE_WECHAT,
                'trade_type' => $tradeType,
                'amount' => $payAmount,
                'openid' => $openid,
                'prepay_id' => $this->extractPrepayId($response),
                'request_payload' => $requestPayload,
                'response_payload' => $response,
                'status' => OrderPayRecordService::STATUS_PENDING,
            ]);
        } catch (Throwable $throwable) {
            throw new AdminResponseException($throwable->getMessage() ?: '微信支付发起失败');
        }

        return [
            'order_id' => (int) ($order['id'] ?? 0),
            'order_sn' => $orderSn,
            'pay_type' => PayTypeEnum::PAY_TYPE_WECHAT,
            'trade_type' => $tradeType,
            'pay_amount' => $payAmount,
            'status' => Order::STATUS_PENDING,
            'status_text' => '待支付',
            'config' => $response,
        ];
    }

    /**
     * 发起支付宝 H5 支付。
     *
     * 支付金额为 0 时直接标记为免费支付成功；正常金额会创建支付宝 WAP 支付地址。
     *
     * @param int $userId 用户标识。
     * @param string $orderSn 订单号。
     * @param array<string, mixed> $options 支付扩展参数，支持 return_url。
     * @return array<string, mixed> 前端跳转支付宝支付所需配置或订单状态。
     * @throws AdminResponseException
     */
    protected function payByAlipay(int $userId, string $orderSn, array $options = []): array
    {
        $orderService = app()->make(OrderService::class);
        $order = $orderService->getUserOrderByOrderSn($userId, $orderSn);
        $status = (int) ($order['status'] ?? Order::STATUS_PENDING);
        if (in_array($status, $this->paidStatuses(), true)) {
            return $this->buildOrderStatePayload($order);
        }
        if ($status !== Order::STATUS_PENDING) {
            throw new AdminResponseException('当前订单状态不支持支付');
        }
        $this->assertNotSplitChildPayOrder($order);

        $payAmount = (float) ($order['pay_amount'] ?? 0);
        if ($payAmount <= 0) {
            return $orderService->markOrderPaidByOrderSn($orderSn, PayTypeEnum::PAY_TYPE_FREE);
        }

        $alipayConfig = $this->getAlipayConfig();
        $notifyUrl = $this->resolveNotifyUrl((string) ($alipayConfig['notify_url'] ?? ''), '/api/mobile/order/alipayNotify');
        $returnUrl = trim((string) ($options['return_url'] ?? ''));
        $body = $this->buildOrderDescription($order);

        $this->savePaymentIntent($order, PayTypeEnum::PAY_TYPE_ALIPAY, 'wap', ['amount' => $payAmount]);
        try {
            $response = $this->makeAlipayPay($alipayConfig)->pay([
                'trade_type' => 'wap',
                'out_trade_no' => $orderSn,
                'amount' => $payAmount,
                'subject' => $body,
                'body' => $body,
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
                'extra' => empty($order['pay_expire_time']) ? [] : ['time_expire' => (string) $order['pay_expire_time']],
            ]);
            app()->make(OrderPayRecordService::class)->saveInitiatedRecord([
                'order_id' => (int) ($order['id'] ?? 0),
                'order_sn' => $orderSn,
                'user_id' => $userId,
                'pay_type' => PayTypeEnum::PAY_TYPE_ALIPAY,
                'trade_type' => 'wap',
                'amount' => $payAmount,
                'request_payload' => [
                    'notify_url' => $notifyUrl,
                    'return_url' => $returnUrl,
                    'amount' => $payAmount,
                    'body' => $body,
                ],
                'response_payload' => ['pay_url' => $response['pay_url'] ?? ''],
                'status' => OrderPayRecordService::STATUS_PENDING,
            ]);
        } catch (Throwable $throwable) {
            throw new AdminResponseException($throwable->getMessage() ?: '支付宝支付发起失败');
        }

        return [
            'order_id' => (int) ($order['id'] ?? 0),
            'order_sn' => $orderSn,
            'pay_type' => PayTypeEnum::PAY_TYPE_ALIPAY,
            'trade_type' => 'wap',
            'pay_amount' => $payAmount,
            'status' => Order::STATUS_PENDING,
            'status_text' => '待支付',
            'config' => $response,
        ];
    }

    /**
     * 处理支付宝支付回调。
     *
     * 完成验签后，仅在 TRADE_SUCCESS 或 TRADE_FINISHED 时同步订单支付成功。
     *
     * @param array<string, mixed> $payload 支付宝回调参数。
     * @return array<string, mixed> 回调处理结果。
     * @throws AdminResponseException
     */
    public function handleAlipayNotify(array $payload): array
    {
        $notify = $this->makeAlipayPay()->notify($payload);
        $orderSn = (string) ($notify['out_trade_no'] ?? '');
        $transactionId = (string) ($notify['transaction_id'] ?? '');
        $tradeStatus = strtoupper((string) ($notify['trade_status'] ?? ''));
        if ($orderSn === '') {
            throw new AdminResponseException('支付宝回调缺少订单号');
        }

        if (!($notify['is_success'] ?? false)) {
            app()->make(OrderPayRecordService::class)->markFailed($orderSn, '支付宝支付未成功', [
                'notify_payload' => $payload,
                'response_payload' => $payload,
            ]);

            return [
                'order_sn' => $orderSn,
                'trade_status' => $tradeStatus,
            ];
        }

        $payTime = $this->resolveAlipaySuccessTime((string) ($notify['pay_time'] ?? ''));
        $promotionReceipt = $this->acceptPromotionReceipt($orderSn, PayTypeEnum::PAY_TYPE_ALIPAY, 'wap', $transactionId,
            (string) ($notify['pay_time'] ?? ''), $this->yuanToFen((float) ($payload['total_amount'] ?? 0)), $payload);
        if ($promotionReceipt !== null) {
            return array_merge($promotionReceipt, ['trade_status' => $tradeStatus]);
        }
        app()->make(OrderService::class)->markOrderPaidByOrderSn($orderSn, PayTypeEnum::PAY_TYPE_ALIPAY, strtotime($payTime) ?: null);
        app()->make(OrderPayRecordService::class)->markPaid($orderSn, [
            'transaction_id' => $transactionId,
            'pay_time' => $payTime,
            'notify_payload' => $payload,
            'response_payload' => $payload,
        ]);

        return [
            'order_sn' => $orderSn,
            'trade_status' => $tradeStatus,
        ];
    }

    /**
     * 持有共享订单锁时，在网络输入输出之前记录支付渠道的支付发起请求。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @param string $payType 已选择或验证的支付渠道编码。
     * @param string $tradeType 支付渠道配置类型，区分公众号、小程序和网页。
     * @param array $requestPayload 在外部请求前需要固化的支付发起参数。
     * @return void 无返回值；外部请求前固化原支付渠道与发起参数。
     */
    protected function savePaymentIntent(array $order, string $payType, string $tradeType, array $requestPayload): void
    {
        app()->make(OrderPayRecordService::class)->saveInitiatedRecord([
            'order_id' => (int) $order['id'], 'order_sn' => (string) $order['order_sn'],
            'user_id' => (int) $order['user_id'], 'pay_type' => $payType, 'trade_type' => $tradeType,
            'amount' => (float) $order['pay_amount'], 'request_payload' => $requestPayload,
        ]);
    }

    /**
     * 获取支付宝支付配置。
     *
     * 配置全部从数据库读取，使用订单支付回调地址。
     *
     * @return array<string, string> 支付宝运行时配置。
     * @throws AdminResponseException
     */
    protected function getAlipayConfig(): array
    {
        $values = app()->make(ConfigService::class)->getMultiConfigVlaue(
            array_column(PaymentConfigKeyEnum::ALIPAY_CONFIG_FIELDS, 'key'),
            false
        );
        $notifyUrl = trim((string) ($values['alipay_order_notify_url'] ?? ''));
        $config = [
            'app_id' => trim((string) ($values['alipay_app_id'] ?? '')),
            'private_key' => trim((string) ($values['alipay_private_key'] ?? '')),
            'public_key' => trim((string) ($values['alipay_public_key'] ?? '')),
            'notify_url' => $notifyUrl,
        ];

        foreach (['app_id', 'private_key', 'public_key'] as $field) {
            if ($config[$field] === '') {
                throw new AdminResponseException('支付宝支付配置缺少：' . $field);
            }
        }

        return $config;
    }

    /**
     * 生成支付宝支付实例。
     *
     * @param array<string, string>|null $config 支付宝运行时配置；为空时自动读取数据库配置。
     * @return PaymentInterface 已设置配置的支付宝支付驱动。
     */
    protected function makeAlipayPay(?array $config = null): PaymentInterface
    {
        $config ??= $this->getAlipayConfig();

        return app()->make(PaymentService::class)->channel(PaymentService::CHANNEL_ALIPAY, $config);
    }

    /**
     * 格式化支付宝支付成功时间。
     *
     * @param string $successTime 支付宝返回的成功时间。
     * @return string 系统统一时间格式。
     */
    protected function resolveAlipaySuccessTime(string $successTime): string
    {
        if ($successTime === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($successTime);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }

    /**
     * 解析支付宝接口错误文案。
     *
     * @param array<string, mixed> $response 支付宝接口响应。
     * @return string 可展示的错误信息。
     */
    protected function resolveAlipayErrorMessage(array $response): string
    {
        $message = trim((string)($response['sub_msg'] ?? ''));
        if ($message === '') {
            $message = trim((string)($response['msg'] ?? ''));
        }
        if ($message === '') {
            $message = '未知错误';
        }

        $code = trim((string)($response['sub_code'] ?? ($response['code'] ?? '')));

        return $code !== '' ? $message . '（' . $code . '）' : $message;
    }

    /**
     * 生成订单支付描述。
     *
     * 优先使用订单首个商品名称，并截断到支付渠道可接受的安全长度。
     *
     * @param array<string, mixed> $order 订单数据。
     * @return string 支付渠道展示的订单描述。
     */
    protected function buildOrderDescription(array $order): string
    {
        $goodsList = app()->make(OrderGoodsService::class)->getByOrderId((int) ($order['id'] ?? 0));
        if ($goodsList === []) {
            return '订单支付';
        }

        $goodsName = trim((string) ($goodsList[0]['goods_name'] ?? '订单支付'));

        return mb_substr($goodsName !== '' ? $goodsName : '订单支付', 0, 64);
    }

    /**
     * 构建订单状态载荷。
     *
     * 用于重复支付已完成订单时，直接向前端返回当前状态。
     *
     * @param array<string, mixed> $order 订单数据。
     * @return array<string, mixed> 订单状态信息。
     */
    protected function buildOrderStatePayload(array $order): array
    {
        $status = (int) ($order['status'] ?? Order::STATUS_PENDING);

        return [
            'order_id' => (int) ($order['id'] ?? 0),
            'order_sn' => (string) ($order['order_sn'] ?? ''),
            'status' => $status,
            'status_text' => match ($status) {
                Order::STATUS_PAID => '已支付',
                Order::STATUS_COMPLETED => '已完成',
                Order::STATUS_CANCELLED => '已取消',
                Order::STATUS_REFUNDED => '已退款',
                Order::STATUS_REFUNDING => '退款中',
                Order::STATUS_WAIT_REVIEW => '待评价',
                default => '待支付',
            },
            'pay_type' => (string) ($order['pay_type'] ?? ''),
            'pay_amount' => (float) ($order['pay_amount'] ?? 0),
            'pay_time' => $order['pay_time'] ?? null,
        ];
    }

    /**
     * 合单子订单不能绕过主支付单单独发起支付。
     *
     * @param array<string, mixed> $order 订单数据。
     * @return void 无返回值；合并支付业务子单直接支付时抛出异常。
     * @throws AdminResponseException
     */
    protected function assertNotSplitChildPayOrder(array $order): void
    {
        if ((int)($order['order_role'] ?? Order::ROLE_NORMAL) === Order::ROLE_CHILD
            && (int)($order['pid'] ?? 0) > 0
        ) {
            throw new AdminResponseException('合单子订单请通过主支付单支付');
        }
    }

    /**
     * 支付成功状态集合。
     *
     * @return array 已支付、待评价和已完成等支付成功状态集合。
     */
    protected function paidStatuses(): array
    {
        return [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED];
    }

    /**
     * 用户允许发起退款的订单状态集合。
     *
     * @return array 允许用户提交退款申请的业务订单状态集合。
     */
    protected function refundableStatuses(): array
    {
        return [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW];
    }

    /**
     * 解析微信支付交易类型。
     *
     * 统一前端或历史接口传入的别名，内部只保留 oa_jsapi、miniapp_jsapi、h5。
     *
     * @param string $tradeType 原始交易类型。
     * @return string 规范化后的微信交易类型。
     */
    protected function normalizeWechatTradeType(string $tradeType): string
    {
        $tradeType = strtolower(trim($tradeType));

        return match ($tradeType) {
            'miniapp', 'mini_program', 'miniapp_jsapi', 'wechat_mp' => 'miniapp_jsapi',
            'h5', 'mweb', 'wap', 'wechat_wap' => 'h5',
            'weixinh5', 'wechat_h5', 'official_account', 'wechat_oa', 'oa_jsapi' => 'oa_jsapi',
            default => 'oa_jsapi',
        };
    }

    /**
     * 统一支付方式值。
     *
     * 将微信、支付宝、余额的历史别名转换为系统枚举值。
     *
     * @param string $payType 原始支付方式。
     * @return string 规范化后的支付方式。
     */
    protected function normalizePayType(string $payType): string
    {
        return match (strtolower(trim($payType))) {
            'weixin', 'wxpay', PayTypeEnum::PAY_TYPE_WECHAT => PayTypeEnum::PAY_TYPE_WECHAT,
            'zhifubao', PayTypeEnum::PAY_TYPE_ALIPAY => PayTypeEnum::PAY_TYPE_ALIPAY,
            'balance', PayTypeEnum::PAY_TYPE_YUE => PayTypeEnum::PAY_TYPE_YUE,
            default => strtolower(trim($payType)),
        };
    }

    /**
     * 获取微信 openid。
     *
     * 小程序支付读取小程序授权 openid，公众号 JSAPI 支付读取公众号授权 openid。
     *
     * @param int $userId 用户标识。
     * @param string $tradeType 微信交易类型。
     * @return string 当前交易类型对应的 openid。
     * @throws AdminResponseException
     */
    protected function resolveWechatOpenid(int $userId, string $tradeType): string
    {
        $platform = $tradeType === 'miniapp_jsapi' ? 'wechat_mp' : 'wechat_oa';
        $auth = app()->make(PlatformAuthService::class)->getByUidAndPlatform($userId, $platform);
        $openid = trim((string) ($auth['openid'] ?? ''));
        if ($openid === '') {
            throw new AdminResponseException($tradeType === 'miniapp_jsapi' ? '请先完成小程序登录后再支付' : '请先完成公众号授权后再支付');
        }

        return $openid;
    }

    /**
     * 获取微信支付配置。
     *
     * 根据交易类型选择公众号或小程序 appid，并校验商户号、证书序列号、私钥和 API v3 密钥。
     *
     * @param string $tradeType 微信交易类型。
     * @return array<string, string> 微信支付运行时配置。
     * @throws AdminResponseException
     */
    protected function getWechatPayConfigByTradeType(string $tradeType): array
    {
        $config = $this->buildWechatPayConfig($tradeType);
        if ($config === []) {
            throw new AdminResponseException('微信支付配置未完善');
        }

        foreach (['app_id', 'mch_id', 'mch_serial_no', 'private_key', 'mch_api_v3_key'] as $field) {
            if (trim((string) ($config[$field] ?? '')) === '') {
                throw new AdminResponseException('微信支付配置缺少：' . $field);
            }
        }

        return $config;
    }

    /**
     * 构建微信支付运行时配置。
     *
     * 配置全部从数据库读取；小程序支付使用小程序 appid，公众号/H5 使用公众号 appid。
     * 支付回调地址读取订单支付专用配置。
     *
     * @param string $tradeType 微信支付交易类型。
     * @return array<string, string> 微信支付配置。
     */
    protected function buildWechatPayConfig(string $tradeType): array
    {
        $config = [];
        $appIdKey = $tradeType === 'miniapp_jsapi' ? WechatConfigKeyEnum::MINI_APPID : WechatConfigKeyEnum::APPID;
        $values = app()->make(ConfigService::class)->getMultiConfigVlaue([
            $appIdKey,
            'wechat_mch_id',
            'wechat_mch_serial_no',
            'wechat_api_v3_key',
            'wechat_cert_path',
            'wechat_key_path',
            'wechat_private_key',
            'wechat_public_key_id',
            'wechat_public_key',
            'wechat_order_notify_url',
            'wechat_refund_notify_url',
        ], false);

        $mapping = [
            $appIdKey => 'app_id',
            'wechat_mch_id' => 'mch_id',
            'wechat_public_key_id' => 'public_key_id',
            'wechat_public_key' => 'public_key',
            'wechat_mch_serial_no' => 'mch_serial_no',
        ];
        foreach ($mapping as $sourceKey => $targetKey) {
            $value = trim((string) ($values[$sourceKey] ?? ''));
            if ($value !== '') {
                $config[$targetKey] = $value;
            }
        }

        $keyPath = trim((string) ($values['wechat_key_path'] ?? ''));
        if ($keyPath !== '') {
            $config['private_key'] = $keyPath;
            $config['ssl_key'] = $keyPath;
        } else {
            $legacyPrivateKey = trim((string) ($values['wechat_private_key'] ?? ''));
            if ($legacyPrivateKey !== '') {
                $config['private_key'] = $legacyPrivateKey;
            }
        }

        $certPath = trim((string) ($values['wechat_cert_path'] ?? ''));
        if ($certPath !== '') {
            $config['cert'] = $certPath;
        }

        $apiV3Key = trim((string) ($values['wechat_api_v3_key'] ?? ''));
        if ($apiV3Key !== '') {
            $config['mch_api_v3_key'] = $apiV3Key;
            $config['secret_key'] = $apiV3Key;
        } elseif (trim((string) ($config['mch_api_v3_key'] ?? '')) !== '' && trim((string) ($config['secret_key'] ?? '')) === '') {
            $config['secret_key'] = (string) $config['mch_api_v3_key'];
        }

        $notifyUrl = trim((string) ($values['wechat_order_notify_url'] ?? ''));
        if ($notifyUrl !== '') {
            $config['notify_url'] = $notifyUrl;
        }

        $refundNotifyUrl = trim((string) ($values['wechat_refund_notify_url'] ?? ''));
        if ($refundNotifyUrl !== '') {
            $config['refund_notify_url'] = $refundNotifyUrl;
        }

        return $config;
    }

    /**
     * 获取微信退款配置。
     *
     * 根据原支付记录的交易类型选择对应 appid，保证退款请求使用和支付单一致的配置。
     *
     * @param array<string, mixed> $order 订单数据。
     * @return array<string, string> 微信退款运行时配置。
     * @throws AdminResponseException
     */
    protected function getWechatRefundConfig(array $order): array
    {
        $payRecord = app()->make(OrderPayRecordService::class)->get(['order_sn' => (string) ($order['order_sn'] ?? '')]);
        $tradeType = $payRecord ? (string) ($payRecord['trade_type'] ?? '') : 'oa_jsapi';

        return $this->getWechatPayConfigByTradeType($this->normalizeWechatTradeType($tradeType));
    }

    /**
     * 使用多套配置解密微信回调。
     *
     * 微信支付与退款回调无法直接从报文区分公众号/小程序配置时，按候选配置逐个验签解密。
     *
     * @param string $rawBody 微信回调原始报文。
     * @param array<string, mixed> $headers 微信回调请求头。
     * @param bool $isRefund 是否为退款回调。
     * @return array{0: array<string, mixed>, 1: string} 解密后的回调数据与命中的交易类型。
     * @throws AdminResponseException
     */
    protected function decryptWechatNotify(string $rawBody, array $headers, bool $isRefund): array
    {
        $errors = [];
        $configMap = [
            'oa_jsapi' => $this->buildWechatPayConfig('oa_jsapi'),
            'miniapp_jsapi' => $this->buildWechatPayConfig('miniapp_jsapi'),
        ];

        foreach ($configMap as $type => $config) {
            if ($config === []) {
                continue;
            }

            try {
                $wechatPay = $this->makeWechatPay($config);
                $payload = $isRefund
                    ? $wechatPay->refundNotify($rawBody, $headers)
                    : $wechatPay->notify($rawBody, $headers);

                Log::info($this->formatPaymentLogMessage('[order-pay] wechat notify decrypt succeeded', [
                    'trade_type' => $type,
                    'is_refund' => $isRefund,
                ]));

                return [$payload, $type];
            } catch (Throwable $throwable) {
                $errors[] = $throwable->getMessage();
                Log::warning($this->formatPaymentLogMessage('[order-pay] wechat notify decrypt attempt failed', [
                    'trade_type' => $type,
                    'is_refund' => $isRefund,
                    'message' => $throwable->getMessage(),
                ]));
            }
        }

        Log::error($this->formatPaymentLogMessage('[order-pay] wechat notify decrypt failed', [
            'is_refund' => $isRefund,
            'errors' => $errors,
        ]));

        throw new AdminResponseException($errors !== [] ? implode('；', $errors) : '微信回调验签失败');
    }

    /**
     * 生成微信支付实例。
     *
     * @param array<string, string> $config 微信支付运行时配置。
     * @return PaymentInterface 已设置配置的微信支付驱动。
     */
    protected function makeWechatPay(array $config): PaymentInterface
    {
        return app()->make(PaymentService::class)->channel(PaymentService::CHANNEL_WECHAT, $config);
    }

    /**
     * 构建绝对地址。
     *
     * @param string $path 站内路径。
     * @return string 基于当前请求域名生成的绝对地址。
     */
    protected function buildAbsoluteUrl(string $path): string
    {
        $domain = rtrim((string) app()->request->domain(), '/');

        return $domain . '/' . ltrim($path, '/');
    }

    /**
     * 解析支付回调地址，优先使用数据库配置。
     *
     * @param string $configuredUrl 数据库配置的回调地址，可以是绝对地址或站内路径。
     * @param string $defaultPath 默认站内路径。
     * @return string 可直接传给支付渠道的绝对回调地址。
     */
    protected function resolveNotifyUrl(string $configuredUrl, string $defaultPath): string
    {
        $configuredUrl = trim($configuredUrl);
        if ($configuredUrl === '') {
            return $this->buildAbsoluteUrl($defaultPath);
        }

        if (preg_match('/^https?:\/\//i', $configuredUrl)) {
            return $configuredUrl;
        }

        return $this->buildAbsoluteUrl($configuredUrl);
    }

    /**
     * 解析微信预支付标识。
     *
     * @param array<string, mixed> $response 微信统一下单返回。
     * @return string prepay_id，不存在时返回空字符串。
     */
    protected function extractPrepayId(array $response): string
    {
        $package = (string) ($response['package'] ?? '');

        return str_starts_with($package, 'prepay_id=') ? substr($package, 10) : '';
    }

    /**
     * 获取微信支付交易单号。
     *
     * @param string $orderSn 订单号。
     * @return string 支付渠道交易单号，不存在时返回空字符串。
     */
    protected function resolveTransactionId(string $orderSn): string
    {
        $payRecord = app()->make(OrderPayRecordService::class)->get(['order_sn' => $orderSn]);

        return $payRecord ? (string) ($payRecord['transaction_id'] ?? '') : '';
    }

    /**
     * 格式化微信支付成功时间。
     *
     * @param string $successTime 微信返回的成功时间。
     * @return string 系统统一时间格式。
     */
    protected function resolveWechatSuccessTime(string $successTime): string
    {
        if ($successTime === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($successTime);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }

    /**
     * 元转分。
     *
     * 先按两位小数标准化，再用 BCMath 转为整数分，避免浮点精度误差。
     *
     * @param float $amount 元单位金额。
     * @return int 分单位金额。
     */
    protected function yuanToFen(float $amount): int
    {
        return (int)bcmul((string)$this->normalizeMoney($amount), '100', 0);
    }

    /**
     * 金额标准化。
     *
     * @param float|string $amount 原始金额。
     * @return float 保留两位小数的金额。
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 高精度金额较小值。
     *
     * @param float|string $left 左侧金额。
     * @param float|string $right 右侧金额。
     * @return float 两个金额中的较小值。
     */
    protected function moneyMin(float|string $left, float|string $right): float
    {
        return bccomp((string)$left, (string)$right, 2) <= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }

    /**
     * 格式化支付日志消息。
     *
     * 当前文件日志驱动不会自动输出 context 参数，需要把上下文写入 message。
     *
     * @param string $message 日志消息。
     * @param array<string, mixed> $context 日志上下文。
     * @return string 可直接写入文件日志的消息。
     */
    protected function formatPaymentLogMessage(string $message, array $context = []): string
    {
        if ($context === []) {
            return $message;
        }

        $payload = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $message . ': ' . ($payload !== false ? $payload : var_export($context, true));
    }

    /**
     * 脱敏支付日志数据。
     *
     * 支付参数用于排查订单和回调问题，但 openid、签名、密钥、令牌及原始报文等字段不能直接写入日志。
     *
     * @param mixed $data 待记录的数据。
     * @param string $key 当前字段名。
     * @return mixed 脱敏后的数据。
     */
    protected function sanitizePaymentLogData(mixed $data, string $key = ''): mixed
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $itemKey => $itemValue) {
                $result[$itemKey] = $this->sanitizePaymentLogData($itemValue, (string)$itemKey);
            }

            return $result;
        }

        if (!is_string($data) || $data === '') {
            return $data;
        }

        if (preg_match('/openid|signature|paysign|private|secret|token|sign|raw_body/i', $key) !== 1) {
            return $data;
        }

        return [
            'length' => strlen($data),
            'sha256' => hash('sha256', $data),
        ];
    }
}
