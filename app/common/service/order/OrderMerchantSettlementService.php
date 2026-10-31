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

use app\common\enum\ConfigKeyEnum;
use app\common\model\order\Order;
use app\common\model\order\OrderMerchantSettlement;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\finance\FinanceService;
use app\common\service\merchant\MerchantService;
use app\common\service\store\StoreRefundProductService;

/**
 * 订单商户结算服务
 */
class OrderMerchantSettlementService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const STATUS_PENDING = 0;
    public const STATUS_FROZEN = 1;
    public const STATUS_SETTLED = self::STATUS_FROZEN;
    public const STATUS_AVAILABLE = 2;
    public const STATUS_PARTIAL_REFUNDED = 3;
    public const STATUS_REFUNDED = 4;

    protected function setModel(): string
    {
        return OrderMerchantSettlement::class;
    }

    /**
     * 计算订单商户结算金额。
     *
     * @param int $merchantId 商户ID，0为平台自营。
     * @param float $payAmount 商户结算基准金额。合单子单会使用“用户实付 + 平台券分摊额”。
     * @param float|null $commissionRate 已预取的商户抽佣比例，null 时按商户 ID 查询。
     * @return array{merchant_id:int,pay_amount:float,commission_rate:float,commission_amount:float,merchant_income_amount:float}
     */
    public function calculateSettlement(int $merchantId, float $payAmount, ?float $commissionRate = null): array
    {
        $payAmount = $this->normalizeMoney($payAmount);
        if ($merchantId <= 0 || $payAmount <= 0) {
            return [
                'merchant_id' => 0,
                'pay_amount' => $payAmount,
                'commission_rate' => 0.0,
                'commission_amount' => 0.0,
                'merchant_income_amount' => 0.0,
            ];
        }

        if ($commissionRate === null) {
            $commissionRateMap = app()->make(MerchantService::class)->getCommissionRateMapByIds([$merchantId]);
            $commissionRate = $commissionRateMap[$merchantId] ?? 0.0;
        }
        $commissionRate = max(0.0, min(100.0, $commissionRate));
        $commissionAmount = $this->moneyPercent($payAmount, $commissionRate);

        return [
            'merchant_id' => $merchantId,
            'pay_amount' => $payAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'merchant_income_amount' => $this->moneyMax(0, $this->moneySub($payAmount, $commissionAmount)),
        ];
    }

    /**
     * 创建订单商户结算明细。
     *
     * @param array $order 订单数据。
     * @param array $settlement 结算金额数据。
     * @return void
     */
    public function createOrderSettlement(array $order, array $settlement): void
    {
        $merchantId = (int)($settlement['merchant_id'] ?? 0);
        if ($merchantId <= 0) {
            return;
        }

        $orderId = (int)($order['order_id'] ?? ($order['id'] ?? 0));
        $orderSn = (string)($order['order_sn'] ?? '');
        if ($orderId <= 0 || $orderSn === '') {
            return;
        }

        // 该方法只在新订单事务内调用，order_id 尚不可能存在结算明细，无需额外做存在性查询。
        $this->create([
            'order_id' => $orderId,
            'order_sn' => $orderSn,
            'merchant_id' => $merchantId,
            'pay_amount' => $this->normalizeMoney((float)($settlement['pay_amount'] ?? 0)),
            'commission_rate' => $this->normalizeMoney((float)($settlement['commission_rate'] ?? 0)),
            'commission_amount' => $this->normalizeMoney((float)($settlement['commission_amount'] ?? 0)),
            'merchant_income_amount' => $this->normalizeMoney((float)($settlement['merchant_income_amount'] ?? 0)),
            'status' => self::STATUS_PENDING,
            'settle_time' => null,
            'refund_time' => null,
        ]);
    }

    /**
     * 支付成功后写入商户收入与平台抽佣流水。
     *
     * 合单支付时 financePid 指向主单 order_summary 流水；子单入账和抽佣作为明细挂在下面，
     * 商户余额仍只按这些明细流水计算，不使用主单聚合流水。
     *
     * @param array $order 订单数据。
     * @param int $financePid 聚合支付流水 ID。
     * @return void
     */
    public function settlePaidOrder(array $order, int $financePid = 0): void
    {
        $orderId = (int)($order['id'] ?? ($order['order_id'] ?? 0));
        if ($orderId <= 0 || (int)($order['merchant_id'] ?? 0) <= 0) {
            return;
        }

        $list = $this->getModel()
            ->where('order_id', $orderId)
            ->where('status', self::STATUS_PENDING)
            ->field(['id', 'merchant_id', 'order_sn', 'merchant_income_amount', 'commission_amount'])
            ->select();
        foreach ($list as $settlement) {
            $this->settleRow($settlement->toArray(), $financePid);
        }
    }

    /**
     * 订单完成后设置商户冻结资金释放时间。
     *
     * @param array $order 订单数据。
     * @return void
     */
    public function markOrderCompleted(array $order): void
    {
        $orderId = (int)($order['id'] ?? ($order['order_id'] ?? 0));
        $orderSn = (string)($order['order_sn'] ?? '');
        if ($orderId <= 0 || $orderSn === '' || (int)($order['merchant_id'] ?? 0) <= 0) {
            return;
        }

        $freezeDays = max(0, (int)db_config(ConfigKeyEnum::MERCHANT_FUND_FREEZE_DAYS, 0));
        $freezeStartTime = $this->resolveFreezeStartTime($order);
        $releaseTime = $freezeDays > 0
            ? date('Y-m-d H:i:s', strtotime($freezeStartTime) + $freezeDays * 86400)
            : $freezeStartTime;

        $list = $this->getModel()
            ->where('order_id', $orderId)
            ->whereIn('status', [self::STATUS_FROZEN, self::STATUS_PARTIAL_REFUNDED])
            ->field(['id', 'merchant_id', 'status'])
            ->select();
        foreach ($list as $settlement) {
            $settlementData = $settlement->toArray();
            $merchantId = (int)($settlementData['merchant_id'] ?? 0);
            if ($merchantId <= 0) {
                continue;
            }

            $updateData = [
                'freeze_start_time' => $freezeStartTime,
                'release_time' => $releaseTime,
            ];
            if ($freezeDays === 0 && in_array((int)($settlementData['status'] ?? 0),
                [self::STATUS_FROZEN, self::STATUS_PARTIAL_REFUNDED], true)) {
                $updateData['status'] = self::STATUS_AVAILABLE;
            }

            $this->update((int)$settlementData['id'], $updateData);
            $this->markFinanceRecordsReleaseTime($orderSn, $merchantId, $freezeStartTime, $releaseTime);

            if ($freezeDays === 0) {
                $this->releaseOrderFrozenFinanceRecords($orderSn, $merchantId);
            }
        }
    }

    /**
     * 释放到期商户冻结资金。
     *
     * @param int $limit 单次处理上限。
     * @return array<string, int>
     */
    public function releaseDueFrozenFunds(int $limit = 100): array
    {
        $result = app()->make(FinanceService::class)->releaseDueFrozenMerchantFunds($limit);
        $now = date('Y-m-d H:i:s');
        $settlementIds = $this->getModel()
            ->where('status', self::STATUS_FROZEN)
            ->whereNotNull('release_time')
            ->where('release_time', '<=', $now)
            ->order('id asc')
            ->limit(max(1, min(500, $limit)))
            ->column('id');

        foreach ($settlementIds as $settlementId) {
            $this->update((int)$settlementId, [
                'status' => self::STATUS_AVAILABLE,
            ]);
        }

        $result['settlements_released'] = count($settlementIds);

        return $result;
    }

    /**
     * 退款成功后冲正商户收入与平台抽佣流水。
     *
     * 整单退款没有商品级退款单号时走这里，直接按订单结算明细全量冲正，并挂到退款聚合流水下。
     *
     * @param array $order 订单数据。
     * @param int $financePid 聚合退款流水 ID。
     * @return void
     */
    public function reverseRefundedOrder(array $order, int $financePid = 0): void
    {
        $orderId = (int)($order['id'] ?? ($order['order_id'] ?? 0));
        if ($orderId <= 0) {
            return;
        }

        $list = $this->getModel()
            ->where('order_id', $orderId)
            ->whereIn('status', [self::STATUS_FROZEN, self::STATUS_AVAILABLE, self::STATUS_PARTIAL_REFUNDED])
            ->select();
        foreach ($list as $settlement) {
            $this->reverseRow($settlement->toArray(), $financePid);
        }
    }

    /**
     * 按退款商品明细冲正商户收入与平台抽佣。
     *
     * 部分退款优先按 StoreRefundProduct 的商品行快照冲正，避免一张子单多商品退款时按整单比例误伤。
     * refundSn 为空时降级为整单冲正，兼容旧退款流程。
     *
     * @param string $refundSn 退款单号。
     * @param array $order 订单数据。
     * @param int $financePid 聚合退款流水 ID。
     * @return array<string, int>
     */
    public function reverseRefundProducts(string $refundSn, array $order, int $financePid = 0): array
    {
        if ($refundSn === '') {
            $this->reverseRefundedOrder($order, $financePid);

            return [
                'scanned' => 0,
                'success' => 0,
                'skipped' => 0,
            ];
        }

        $refundProductService = app()->make(StoreRefundProductService::class);
        $items = $refundProductService->getPendingByRefundSn($refundSn);
        $result = [
            'scanned' => count($items),
            'success' => 0,
            'skipped' => 0,
        ];

        if ($items === []) {
            return $result;
        }

        foreach ($items as $item) {
            if ($this->reverseRefundProductRow($item, $financePid)) {
                $result['success']++;
            } else {
                $result['skipped']++;
            }
        }

        $refundProductService->markSuccessByRefundSn($refundSn);

        return $result;
    }

    /**
     * 获取订单商户的历史抽佣比例。
     *
     * @param int $orderId 订单ID。
     * @param int $merchantId 商户ID。
     * @return float
     */
    public function getCommissionRateByOrderMerchant(int $orderId, int $merchantId): float
    {
        if ($orderId <= 0 || $merchantId <= 0) {
            return 0.0;
        }

        $commissionRate = $this->getModel()
            ->where('order_id', $orderId)
            ->where('merchant_id', $merchantId)
            ->value('commission_rate');
        if ($commissionRate !== null) {
            return $this->normalizeMoney((float)$commissionRate);
        }

        $commissionRateMap = app()->make(MerchantService::class)->getCommissionRateMapByIds([$merchantId]);

        return $this->normalizeMoney((float)($commissionRateMap[$merchantId] ?? 0));
    }

    /**
     * 写入单条结算入账流水。
     *
     * @param array $settlement 结算明细。
     * @param int $financePid 聚合支付流水 ID。
     * @return void
     */
    protected function settleRow(array $settlement, int $financePid = 0): void
    {
        $id = (int)($settlement['id'] ?? 0);
        $merchantId = (int)($settlement['merchant_id'] ?? 0);
        $orderSn = (string)($settlement['order_sn'] ?? '');
        $merchantIncomeAmount = $this->normalizeMoney((float)($settlement['merchant_income_amount'] ?? 0));
        $commissionAmount = $this->normalizeMoney((float)($settlement['commission_amount'] ?? 0));
        if ($id <= 0 || $merchantId <= 0 || $orderSn === '') {
            return;
        }

        $financeService = app()->make(FinanceService::class);
        if ($merchantIncomeAmount > 0) {
            // 商户收入先冻结，订单完成后按冻结期释放；pid 用于回看这笔合单支付的全部明细。
            $financeService->createRecord([
                'pid' => $financePid,
                'merchant_id' => $merchantId,
                'type' => 'order',
                'change_type' => 'in',
                'fund_status' => FinanceService::FUND_STATUS_FROZEN,
                'amount' => $merchantIncomeAmount,
                'remark' => '订单商户入账',
                'order_sn' => $orderSn,
            ]);
        }

        if ($commissionAmount > 0) {
            // 平台抽佣是平台收入明细，同样挂到主流水下，统计时不会把主流水重复算入收入。
            $financeService->createRecord([
                'pid' => $financePid,
                'merchant_id' => 0,
                'type' => 'commission',
                'change_type' => 'in',
                'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
                'amount' => $commissionAmount,
                'remark' => '平台订单抽佣',
                'order_sn' => $orderSn,
            ]);
        }

        $this->update($id, [
            'status' => self::STATUS_FROZEN,
            'settle_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 写入单条结算退款冲正流水。
     *
     * @param array $settlement 结算明细。
     * @param int $financePid 聚合退款流水 ID。
     * @return void
     */
    protected function reverseRow(array $settlement, int $financePid = 0): void
    {
        $id = (int)($settlement['id'] ?? 0);
        $merchantId = (int)($settlement['merchant_id'] ?? 0);
        $orderSn = (string)($settlement['order_sn'] ?? '');
        $merchantIncomeAmount = $this->normalizeMoney((float)($settlement['merchant_income_amount'] ?? 0));
        $commissionAmount = $this->normalizeMoney((float)($settlement['commission_amount'] ?? 0));
        if ($id <= 0 || $merchantId <= 0 || $orderSn === '') {
            return;
        }

        $this->createRefundFinanceRecords(
            $orderSn,
            $merchantId,
            $merchantIncomeAmount,
            $commissionAmount,
            '',
            $financePid
        );

        $this->update($id, [
            'status' => self::STATUS_REFUNDED,
            'refunded_amount' => $this->normalizeMoney((float)($settlement['pay_amount'] ?? 0)),
            'reversed_commission_amount' => $commissionAmount,
            'reversed_merchant_income_amount' => $merchantIncomeAmount,
            'refund_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 冲正单条退款商品明细。
     *
     * @param array $item 退款商品明细。
     * @param int $financePid 聚合退款流水 ID。
     * @return bool
     */
    protected function reverseRefundProductRow(array $item, int $financePid = 0): bool
    {
        $orderId = (int)($item['order_id'] ?? 0);
        $merchantId = (int)($item['merchant_id'] ?? 0);
        $orderSn = (string)($item['order_sn'] ?? '');
        if ($orderId <= 0 || $merchantId <= 0 || $orderSn === '') {
            return false;
        }

        $settlement = $this->getModel()
            ->where('order_id', $orderId)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$settlement || (int)($settlement['status'] ?? 0) === self::STATUS_REFUNDED) {
            return false;
        }

        $refundAmount = $this->normalizeMoney((float)($item['refund_amount'] ?? 0));
        $platformCouponAmount = $this->normalizeMoney((float)($item['platform_coupon_amount'] ?? 0));
        // 用户实际退款只退 refund_amount；商户结算冲正要加回平台券补贴，平台券另写补贴冲正流水。
        $settlementRefundAmount = $this->normalizeMoney($this->moneyAdd($refundAmount, $platformCouponAmount));
        $commissionAmount = $this->normalizeMoney((float)($item['commission_amount'] ?? 0));
        $merchantIncomeAmount = $this->normalizeMoney((float)($item['merchant_income_amount'] ?? 0));
        if ($settlementRefundAmount <= 0) {
            return false;
        }

        $this->createRefundFinanceRecords(
            $orderSn,
            $merchantId,
            $merchantIncomeAmount,
            $commissionAmount,
            (string)($item['refund_sn'] ?? ''),
            $financePid,
            $platformCouponAmount
        );

        $settlementData = $settlement->toArray();
        $refundedAmount = $this->moneyAdd((float)($settlementData['refunded_amount'] ?? 0), $settlementRefundAmount);
        $reversedCommissionAmount = $this->normalizeMoney(
            $this->moneyAdd((float)($settlementData['reversed_commission_amount'] ?? 0), $commissionAmount)
        );
        $reversedMerchantIncomeAmount = $this->normalizeMoney(
            $this->moneyAdd((float)($settlementData['reversed_merchant_income_amount'] ?? 0), $merchantIncomeAmount)
        );
        $payAmount = $this->normalizeMoney((float)($settlementData['pay_amount'] ?? 0));

        $this->update((int)$settlementData['id'], [
            'status' => $this->moneyCompare($refundedAmount, $this->moneySub($payAmount, 0.01)) >= 0
                ? self::STATUS_REFUNDED
                : self::STATUS_PARTIAL_REFUNDED,
            'refunded_amount' => $refundedAmount,
            'reversed_commission_amount' => $reversedCommissionAmount,
            'reversed_merchant_income_amount' => $reversedMerchantIncomeAmount,
            'refund_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * 写入退款冲正流水。
     *
     * merchantIncomeAmount/commissionAmount 是商户结算口径；platformCouponAmount 是平台补贴冲正口径。
     * 三类流水都挂同一个 financePid，便于从退款聚合流水追到完整明细。
     */
    protected function createRefundFinanceRecords(
        string $orderSn,
        int $merchantId,
        float $merchantIncomeAmount,
        float $commissionAmount,
        string $refundSn,
        int $financePid = 0,
        float $platformCouponAmount = 0
    ): void {
        $financeService = app()->make(FinanceService::class);
        $merchantFundStatus = $this->resolveMerchantRefundFundStatus($orderSn, $merchantId);
        $timing = $this->resolveMerchantFundTiming($orderSn, $merchantId);
        $remarkSuffix = $refundSn !== '' ? '：' . $refundSn : '';

        if ($merchantIncomeAmount > 0) {
            $financeService->createRecord([
                'pid' => $financePid,
                'merchant_id' => $merchantId,
                'type' => 'refund',
                'change_type' => 'out',
                'fund_status' => $merchantFundStatus,
                'amount' => $merchantIncomeAmount,
                'remark' => '订单退款冲正商户入账' . $remarkSuffix,
                'order_sn' => $orderSn,
                'freeze_start_time' => $merchantFundStatus === FinanceService::FUND_STATUS_FROZEN
                    ? ($timing['freeze_start_time'] ?? null)
                    : null,
                'release_time' => $merchantFundStatus === FinanceService::FUND_STATUS_FROZEN
                    ? ($timing['release_time'] ?? null)
                    : null,
            ]);
        }

        if ($commissionAmount > 0) {
            $financeService->createRecord([
                'pid' => $financePid,
                'merchant_id' => 0,
                'type' => 'commission',
                'change_type' => 'out',
                'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
                'amount' => $commissionAmount,
                'remark' => '订单退款冲正平台抽佣' . $remarkSuffix,
                'order_sn' => $orderSn,
            ]);
        }

        $platformCouponAmount = $this->normalizeMoney($platformCouponAmount);
        if ($platformCouponAmount > 0) {
            // 平台券补贴退款时是平台支出的回冲，不进入用户实退金额，也不减少商户收入口径。
            $financeService->createRecord([
                'pid' => $financePid,
                'merchant_id' => 0,
                'type' => FinanceService::TYPE_COUPON_SUBSIDY,
                'change_type' => 'in',
                'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
                'amount' => $platformCouponAmount,
                'remark' => '订单退款冲正平台券补贴' . $remarkSuffix,
                'order_sn' => $orderSn,
            ]);
        }
    }

    /**
     * 获取退款时商户资金应冲正的资金状态。
     */
    protected function resolveMerchantRefundFundStatus(string $orderSn, int $merchantId): string
    {
        $frozenIncome = app()->make(FinanceService::class)->getModel()
            ->where('order_sn', $orderSn)
            ->where('merchant_id', $merchantId)
            ->where('type', 'order')
            ->where('change_type', 'in')
            ->where('fund_status', FinanceService::FUND_STATUS_FROZEN)
            ->sum('amount');

        return (float)$frozenIncome > 0
            ? FinanceService::FUND_STATUS_FROZEN
            : FinanceService::FUND_STATUS_AVAILABLE;
    }

    /**
     * 获取商户订单资金冻结时间。
     */
    protected function resolveMerchantFundTiming(string $orderSn, int $merchantId): array
    {
        $record = app()->make(FinanceService::class)->getModel()
            ->where('order_sn', $orderSn)
            ->where('merchant_id', $merchantId)
            ->where('type', 'order')
            ->where('change_type', 'in')
            ->order('id desc')
            ->find();
        if ($record) {
            return [
                'freeze_start_time' => $record['freeze_start_time'] ?? null,
                'release_time' => $record['release_time'] ?? null,
            ];
        }

        $settlement = $this->getModel()
            ->where('order_sn', $orderSn)
            ->where('merchant_id', $merchantId)
            ->find();
        $settlementData = $settlement ? $settlement->toArray() : [];

        return [
            'freeze_start_time' => $settlementData['freeze_start_time'] ?? null,
            'release_time' => $settlementData['release_time'] ?? null,
        ];
    }

    /**
     * 设置商户订单冻结流水释放时间。
     */
    protected function markFinanceRecordsReleaseTime(
        string $orderSn,
        int $merchantId,
        string $freezeStartTime,
        string $releaseTime
    ): void {
        app()->make(FinanceService::class)->getModel()
            ->where('order_sn', $orderSn)
            ->where('merchant_id', $merchantId)
            ->where('fund_status', FinanceService::FUND_STATUS_FROZEN)
            ->update([
                'freeze_start_time' => $freezeStartTime,
                'release_time' => $releaseTime,
            ]);
    }

    /**
     * 立即释放指定订单商户冻结流水。
     */
    protected function releaseOrderFrozenFinanceRecords(string $orderSn, int $merchantId): void
    {
        app()->make(FinanceService::class)->getModel()
            ->where('order_sn', $orderSn)
            ->where('merchant_id', $merchantId)
            ->where('fund_status', FinanceService::FUND_STATUS_FROZEN)
            ->update([
                'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
            ]);
    }

    /**
     * 解析冻结起算时间。
     */
    protected function resolveFreezeStartTime(array $order): string
    {
        if (in_array((int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS), [
            Order::ORDER_TYPE_CONTENT,
            Order::ORDER_TYPE_SPECIAL,
        ], true)) {
            $payTime = trim((string)($order['pay_time'] ?? ''));
            if ($payTime !== '') {
                return $payTime;
            }
        }

        foreach (['finish_time', 'receive_time', 'pay_time'] as $field) {
            $time = trim((string)($order[$field] ?? ''));
            if ($time !== '') {
                return $time;
            }
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * 规范化金额。
     *
     * @param float|string $amount 金额。
     * @return float
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 高精度金额相加。
     */
    protected function moneyAdd(float|string $left, float|string $right): float
    {
        return (float)bcadd((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额相减。
     */
    protected function moneySub(float|string $left, float|string $right): float
    {
        return (float)bcsub((string)$left, (string)$right, 2);
    }

    /**
     * 高精度百分比金额。
     */
    protected function moneyPercent(float|string $amount, float|string $rate): float
    {
        return (float)bcdiv(bcmul((string)$amount, (string)$rate, 4), '100', 2);
    }

    /**
     * 高精度金额比较。
     */
    protected function moneyCompare(float|string $left, float|string $right): int
    {
        return bccomp((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额最大值。
     */
    protected function moneyMax(float|string $left, float|string $right): float
    {
        return $this->moneyCompare($left, $right) >= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }
}
