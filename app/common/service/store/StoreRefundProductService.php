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

namespace app\common\service\store;

use app\common\exception\AdminResponseException;
use app\common\model\store\StoreRefundProduct;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\order\OrderGoodsService;
use app\common\service\order\OrderMerchantSettlementService;

/**
 * 退款商品明细服务
 */
class StoreRefundProductService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const STATUS_PENDING = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_CANCELLED = 2;

    protected function setModel(): string
    {
        return StoreRefundProduct::class;
    }

    /**
     * 预览退款商品明细和合计退款金额。
     *
     * @param array $order 订单数据。
     * @param array $items 退款商品入参。
     * @param float $refundAmount 指定退款总金额。
     * @param string $excludeRefundSn 重新计算时排除的退款单号。
     * @return array{refund_amount:float,items:array}
     * @throws AdminResponseException
     */
    public function previewRefundProducts(
        array $order,
        array $items = [],
        float $refundAmount = 0,
        string $excludeRefundSn = ''
    ): array {
        $orderId = (int)($order['id'] ?? ($order['order_id'] ?? 0));
        $orderSn = (string)($order['order_sn'] ?? '');
        $payAmount = $this->normalizeMoney((float)($order['pay_amount'] ?? 0));
        if ($orderId <= 0 || $orderSn === '') {
            throw new AdminResponseException('订单数据不完整，无法生成退款商品明细');
        }

        $goodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        if ($goodsList === []) {
            throw new AdminResponseException('订单商品不存在，无法申请退款');
        }

        $lineAmountMap = $this->buildLineAmountMap($goodsList, $payAmount);
        $requestedItems = $this->normalizeRequestedItems($items);
        $targetGoodsList = $this->filterTargetGoods($goodsList, $requestedItems);
        $remainingOrderAmount = $this->remainingOrderRefundAmount($orderId, $payAmount, $excludeRefundSn);
        $refundAmount = $this->normalizeMoney($refundAmount);
        if ($refundAmount <= 0 && $requestedItems === []) {
            $refundAmount = $remainingOrderAmount;
        }

        $rows = $this->buildRefundRows(
            $order,
            $targetGoodsList,
            $lineAmountMap,
            $requestedItems,
            $refundAmount,
            $excludeRefundSn
        );
        $totalRefundAmount = $this->sumMoneyColumn($rows, 'refund_amount');
        if ($totalRefundAmount <= 0) {
            throw new AdminResponseException('退款金额必须大于0');
        }
        if ($this->moneyCompare($this->moneySub($totalRefundAmount, $remainingOrderAmount), 0.01) > 0) {
            throw new AdminResponseException('退款金额不能大于订单剩余可退款金额');
        }

        return [
            'refund_amount' => $totalRefundAmount,
            'items' => $rows,
        ];
    }

    /**
     * 创建或更新退款商品明细。
     *
     * @param array $order 订单数据。
     * @param array $refundRecord 退款主记录。
     * @param array $items 退款商品入参。
     * @param float $refundAmount 退款总金额。
     * @return array
     * @throws AdminResponseException
     */
    public function createOrUpdateForRefund(
        array $order,
        array $refundRecord,
        array $items = [],
        float $refundAmount = 0
    ): array {
        $refundSn = (string)($refundRecord['refund_sn'] ?? '');
        if ($refundSn === '') {
            throw new AdminResponseException('退款单号不能为空');
        }

        $preview = $this->previewRefundProducts($order, $items, $refundAmount, $refundSn);
        $usedOrderGoodsIds = [];
        $savedRows = [];

        foreach ($preview['items'] as $item) {
            $orderGoodsId = (int)($item['order_goods_id'] ?? 0);
            if ($orderGoodsId <= 0) {
                continue;
            }

            $usedOrderGoodsIds[] = $orderGoodsId;
            $payload = array_merge($item, [
                'order_refund_id' => (int)($refundRecord['id'] ?? 0),
                'refund_sn' => $refundSn,
                'status' => self::STATUS_PENDING,
            ]);
            $existing = $this->getModel()
                ->where('refund_sn', $refundSn)
                ->where('order_goods_id', $orderGoodsId)
                ->find();

            if ($existing && (int)($existing['status'] ?? self::STATUS_PENDING) === self::STATUS_SUCCESS) {
                $savedRows[] = $existing->toArray();
                continue;
            }

            if ($existing) {
                $this->update((int)$existing['id'], $payload);
                $fresh = $this->get((int)$existing['id']);
                $savedRows[] = $fresh ? $fresh->toArray() : array_merge($existing->toArray(), $payload);
                continue;
            }

            $savedRows[] = $this->create($payload)->toArray();
        }

        if ($usedOrderGoodsIds !== []) {
            $this->getModel()
                ->where('refund_sn', $refundSn)
                ->where('status', self::STATUS_PENDING)
                ->whereNotIn('order_goods_id', $usedOrderGoodsIds)
                ->update(['status' => self::STATUS_CANCELLED]);
        }

        return $savedRows;
    }

    /**
     * 是否已有退款商品明细。
     */
    public function hasByRefundSn(string $refundSn): bool
    {
        if ($refundSn === '') {
            return false;
        }

        return $this->getModel()->where('refund_sn', $refundSn)->count() > 0;
    }

    /**
     * 获取待退款商品明细。
     */
    public function getPendingByRefundSn(string $refundSn): array
    {
        if ($refundSn === '') {
            return [];
        }

        return $this->getModel()
            ->where('refund_sn', $refundSn)
            ->where('status', self::STATUS_PENDING)
            ->select()
            ->toArray();
    }

    /**
     * 获取有效退款商品明细。
     */
    public function getActiveByRefundSn(string $refundSn): array
    {
        if ($refundSn === '') {
            return [];
        }

        return $this->getModel()
            ->where('refund_sn', $refundSn)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SUCCESS])
            ->select()
            ->toArray();
    }

    /**
     * 标记退款商品明细成功。
     */
    public function markSuccessByRefundSn(string $refundSn): void
    {
        if ($refundSn === '') {
            return;
        }

        $this->getModel()
            ->where('refund_sn', $refundSn)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_SUCCESS]);
    }

    /**
     * 标记退款商品明细取消。
     */
    public function markCancelledByRefundSn(string $refundSn): void
    {
        if ($refundSn === '') {
            return;
        }

        $this->getModel()
            ->where('refund_sn', $refundSn)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * 获取订单有效退款总金额。
     */
    public function sumActiveRefundAmountByOrder(int $orderId): float
    {
        if ($orderId <= 0) {
            return 0.0;
        }

        return $this->normalizeMoney((float)$this->getModel()
            ->where('order_id', $orderId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SUCCESS])
            ->sum('refund_amount'));
    }

    /**
     * 获取订单已成功退款总金额。
     */
    public function sumSuccessRefundAmountByOrder(int $orderId): float
    {
        if ($orderId <= 0) {
            return 0.0;
        }

        return $this->normalizeMoney((float)$this->getModel()
            ->where('order_id', $orderId)
            ->where('status', self::STATUS_SUCCESS)
            ->sum('refund_amount'));
    }

    /**
     * 构建订单商品实付金额映射。
     */
    protected function buildLineAmountMap(array $goodsList, float $payAmount): array
    {
        $baseMap = [];
        $baseTotal = 0.0;
        foreach ($goodsList as $goods) {
            $orderGoodsId = (int)($goods['id'] ?? 0);
            $baseAmount = $this->lineBaseAmount($goods);
            $baseMap[$orderGoodsId] = $baseAmount;
            $baseTotal = $this->moneyAdd($baseTotal, $baseAmount);
        }

        $result = [];
        $allocated = 0.0;
        $index = 0;
        $count = count($baseMap);
        foreach ($baseMap as $orderGoodsId => $baseAmount) {
            $index++;
            if ($index === $count) {
                $result[$orderGoodsId] = $this->moneyMax(0, $this->moneySub($payAmount, $allocated));
                continue;
            }

            $amount = $baseTotal > 0
                ? $this->moneyDiv($this->moneyMul($payAmount, $baseAmount, 4), $baseTotal)
                : $this->moneyDiv($payAmount, max(1, $count));
            $allocated = $this->moneyAdd($allocated, $amount);
            $result[$orderGoodsId] = $amount;
        }

        return $result;
    }

    /**
     * 构建退款商品行。
     *
     * 退款明细是后续结算冲正的依据：每一行都记录用户实退金额、平台券分摊额、抽佣冲正额和商户收入冲正额。
     *
     * @throws AdminResponseException
     */
    protected function buildRefundRows(
        array $order,
        array $goodsList,
        array $lineAmountMap,
        array $requestedItems,
        float $refundAmount,
        string $excludeRefundSn
    ): array {
        $rows = [];
        $lineRemainingMap = [];
        $remainingBaseTotal = 0.0;
        // 先计算每个 order_goods_id 的剩余可退金额，后面整单退款分摊时以它作为基数，避免超退。
        foreach ($goodsList as $goods) {
            $orderGoodsId = (int)($goods['id'] ?? 0);
            $remaining = $this->remainingLineRefundAmount(
                $orderGoodsId,
                (float)($lineAmountMap[$orderGoodsId] ?? 0),
                $excludeRefundSn
            );
            $lineRemainingMap[$orderGoodsId] = $remaining;
            $remainingBaseTotal = $this->moneyAdd($remainingBaseTotal, $remaining);
        }

        $shouldAllocateTotal = $refundAmount > 0 && !$this->hasExplicitRefundAmount($requestedItems);
        $allocated = 0.0;
        $index = 0;
        $count = count($goodsList);
        foreach ($goodsList as $goods) {
            $index++;
            $orderGoodsId = (int)($goods['id'] ?? 0);
            $request = $requestedItems[$orderGoodsId] ?? [];
            $remaining = $lineRemainingMap[$orderGoodsId] ?? 0.0;
            if ($remaining <= 0) {
                continue;
            }

            $lineRefundAmount = $this->resolveLineRefundAmount(
                $goods,
                $request,
                $remaining,
                $refundAmount,
                $remainingBaseTotal,
                $allocated,
                $index === $count,
                $shouldAllocateTotal
            );
            if ($lineRefundAmount <= 0) {
                continue;
            }
            if ($this->moneyCompare($this->moneySub($lineRefundAmount, $remaining), 0.01) > 0) {
                throw new AdminResponseException('退款金额不能大于商品剩余可退款金额');
            }

            $allocated = $this->moneyAdd($allocated, $lineRefundAmount);
            $rows[] = $this->buildRefundRow($order, $goods, $lineRefundAmount, $request);
        }

        return $rows;
    }

    /**
     * 构建单条退款商品明细。
     *
     * refund_amount 是用户实退金额；platform_coupon_amount 是需要同步冲正的平台补贴，
     * 二者相加后才是商户结算和平台抽佣需要回滚的基准。
     */
    protected function buildRefundRow(array $order, array $goods, float $refundAmount, array $request): array
    {
        $orderId = (int)($order['id'] ?? ($order['order_id'] ?? 0));
        $orderSn = (string)($order['order_sn'] ?? '');
        $orderGoodsId = (int)($goods['id'] ?? 0);
        $merchantId = (int)($goods['merchant_id'] ?? ($order['merchant_id'] ?? 0));
        $goodsInfo = is_array($goods['goods_info'] ?? null) ? $goods['goods_info'] : [];
        $refundNum = (int)($request['refund_num'] ?? 0);
        if ($refundNum <= 0) {
            $refundNum = (int)($goods['num'] ?? 0);
        }

        $commissionRate = app()->make(OrderMerchantSettlementService::class)
            ->getCommissionRateByOrderMerchant($orderId, $merchantId);
        $platformCouponAmount = $this->resolveRefundPlatformCouponAmount($goods, $refundAmount, $request);
        // 商户结算曾按“用户实付 + 平台券补贴”入账，退款冲正也必须使用同一口径。
        $settlementRefundAmount = $this->moneyAdd($refundAmount, $platformCouponAmount);
        $commissionAmount = $this->moneyPercent($settlementRefundAmount, $commissionRate);

        return [
            'order_id' => $orderId,
            'order_sn' => $orderSn,
            'order_goods_id' => $orderGoodsId,
            'goods_id' => (int)($goods['goods_id'] ?? 0),
            'merchant_id' => $merchantId,
            'goods_name' => mb_substr((string)($goods['goods_name'] ?? ''), 0, 100),
            'sku_id' => (int)($goodsInfo['sku_id'] ?? 0),
            'refund_num' => max(0, min($refundNum, (int)($goods['num'] ?? 0))),
            'refund_amount' => $refundAmount,
            'platform_coupon_amount' => $platformCouponAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'merchant_income_amount' => $this->moneyMax(0, $this->moneySub($settlementRefundAmount, $commissionAmount)),
        ];
    }

    /**
     * 解析退款商品需要同步冲正的平台券补贴金额。
     *
     * 用户不会收到这部分钱，但平台需要把已承担的营销补贴按退款比例回冲。
     */
    protected function resolveRefundPlatformCouponAmount(array $goods, float $refundAmount, array $request): float
    {
        $platformCouponAmount = $this->normalizeMoney((float)($goods['platform_coupon_amount'] ?? 0));
        if ($platformCouponAmount <= 0) {
            return 0.0;
        }

        $lineUserAmount = $this->lineBaseAmount($goods);
        if ($lineUserAmount > 0) {
            // 优先按该商品行用户可退金额比例回冲平台券，部分退款时不会一次性冲掉整行补贴。
            $ratio = $this->moneyDiv($refundAmount, $lineUserAmount);
            return $this->moneyMin($platformCouponAmount, $this->moneyMul($platformCouponAmount, $ratio, 4));
        }

        $refundNum = (int)($request['refund_num'] ?? 0);
        $goodsNum = max(1, (int)($goods['num'] ?? 1));
        if ($refundNum > 0) {
            // 无法按金额换算时退回到件数比例，适配只提交退款数量的场景。
            $ratio = $this->moneyDiv(min($refundNum, $goodsNum), $goodsNum);
            return $this->moneyMin($platformCouponAmount, $this->moneyMul($platformCouponAmount, $ratio, 4));
        }

        return $platformCouponAmount;
    }

    /**
     * 解析单行退款金额。
     */
    protected function resolveLineRefundAmount(
        array $goods,
        array $request,
        float $remaining,
        float $refundAmount,
        float $remainingBaseTotal,
        float $allocated,
        bool $isLast,
        bool $shouldAllocateTotal
    ): float {
        $explicitAmount = $this->normalizeMoney((float)($request['refund_amount'] ?? 0));
        if ($explicitAmount > 0) {
            return min($explicitAmount, $remaining);
        }

        if ($shouldAllocateTotal) {
            if ($isLast) {
                return $this->moneyMax(0, $this->moneySub($refundAmount, $allocated));
            }

            return $remainingBaseTotal > 0
                ? $this->moneyDiv($this->moneyMul($refundAmount, $remaining, 4), $remainingBaseTotal)
                : 0.0;
        }

        $refundNum = (int)($request['refund_num'] ?? 0);
        $goodsNum = max(1, (int)($goods['num'] ?? 1));
        if ($refundNum > 0) {
            return $this->moneyDiv($this->moneyMul($remaining, min($refundNum, $goodsNum), 4), $goodsNum);
        }

        return $remaining;
    }

    /**
     * 筛选需要退款的商品。
     *
     * @throws AdminResponseException
     */
    protected function filterTargetGoods(array $goodsList, array $requestedItems): array
    {
        if ($requestedItems === []) {
            return $goodsList;
        }

        $goodsMap = [];
        foreach ($goodsList as $goods) {
            $goodsMap[(int)($goods['id'] ?? 0)] = $goods;
        }

        $result = [];
        foreach (array_keys($requestedItems) as $orderGoodsId) {
            if (!isset($goodsMap[$orderGoodsId])) {
                throw new AdminResponseException('退款商品不存在');
            }
            $result[] = $goodsMap[$orderGoodsId];
        }

        return $result;
    }

    /**
     * 格式化退款商品入参。
     */
    protected function normalizeRequestedItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $orderGoodsId = (int)($item['order_goods_id'] ?? ($item['id'] ?? 0));
            if ($orderGoodsId <= 0) {
                continue;
            }

            $result[$orderGoodsId] = [
                'order_goods_id' => $orderGoodsId,
                'refund_num' => max(0, (int)($item['refund_num'] ?? 0)),
                'refund_amount' => $this->normalizeMoney((float)($item['refund_amount'] ?? 0)),
            ];
        }

        return $result;
    }

    /**
     * 是否有显式退款金额。
     */
    protected function hasExplicitRefundAmount(array $requestedItems): bool
    {
        foreach ($requestedItems as $item) {
            if ((float)($item['refund_amount'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 订单剩余可退款金额。
     */
    protected function remainingOrderRefundAmount(int $orderId, float $payAmount, string $excludeRefundSn = ''): float
    {
        $query = $this->getModel()
            ->where('order_id', $orderId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SUCCESS]);
        if ($excludeRefundSn !== '') {
            $query->where('refund_sn', '<>', $excludeRefundSn);
        }

        return $this->moneyMax(0, $this->moneySub($payAmount, (float)$query->sum('refund_amount')));
    }

    /**
     * 商品行剩余可退款金额。
     */
    protected function remainingLineRefundAmount(int $orderGoodsId, float $lineAmount, string $excludeRefundSn = ''): float
    {
        $query = $this->getModel()
            ->where('order_goods_id', $orderGoodsId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SUCCESS]);
        if ($excludeRefundSn !== '') {
            $query->where('refund_sn', '<>', $excludeRefundSn);
        }

        return $this->moneyMax(0, $this->moneySub($lineAmount, (float)$query->sum('refund_amount')));
    }

    /**
     * 商品行基础金额。
     */
    protected function lineBaseAmount(array $goods): float
    {
        $base = $this->moneySub((float)($goods['total_price'] ?? 0), (float)($goods['points_deduct_amount'] ?? 0));
        $base = $this->moneySub($base, (float)($goods['platform_coupon_amount'] ?? 0));
        $base = $this->moneySub($base, (float)($goods['merchant_coupon_amount'] ?? 0));
        $base = $this->moneySub($base, (float)($goods['member_discount_amount'] ?? 0));

        return $this->moneyMax(0, $base);
    }

    /**
     * 规范化金额。
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 汇总数组金额字段。
     */
    protected function sumMoneyColumn(array $items, string $field): float
    {
        $amount = '0';
        foreach ($items as $item) {
            $amount = bcadd($amount, (string)($item[$field] ?? 0), 2);
        }

        return $this->normalizeMoney($amount);
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
     * 高精度金额相乘。
     */
    protected function moneyMul(float|string $left, float|string $right, int $scale = 2): float
    {
        return (float)bcmul((string)$left, (string)$right, $scale);
    }

    /**
     * 高精度金额相除。
     */
    protected function moneyDiv(float|string $dividend, float|string $divisor): float
    {
        if (bccomp((string)$divisor, '0', 10) === 0) {
            return 0.0;
        }

        return (float)bcdiv((string)$dividend, (string)$divisor, 2);
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

    /**
     * 高精度金额最小值。
     */
    protected function moneyMin(float|string $left, float|string $right): float
    {
        return $this->moneyCompare($left, $right) <= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }
}
