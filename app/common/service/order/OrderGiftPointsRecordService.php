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
use app\common\model\order\OrderGiftPointsRecord;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialContentService;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserMemberService;

/**
 * 内容/专题订单赠送积分生命周期服务。
 */
class OrderGiftPointsRecordService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return OrderGiftPointsRecord::class;
    }

    /**
     * 下单时创建赠送积分快照。
     *
     * @param array $order 订单主表快照。
     * @param array $orderGoodsList 订单商品快照列表。
     * @return array 最新赠送积分记录。
     */
    public function recordForOrder(array $order, array $orderGoodsList): array
    {
        $orderId = (int)($order['id'] ?? 0);
        $orderSn = (string)($order['order_sn'] ?? '');
        $orderType = (int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS);
        if ($orderId <= 0 || $orderSn === '' || !$this->isSupportedOrderType($orderType)) {
            return [];
        }

        $exists = $this->get(['order_id' => $orderId]);
        if ($exists) {
            return $exists->toArray();
        }

        $snapshot = $this->buildGiftPointsSnapshot($orderType, $orderGoodsList);
        $basePoints = (int)($snapshot['base_points'] ?? 0);
        $multiplier = $this->resolveMultiplier((int)($order['user_id'] ?? 0));
        $finalPoints = (int)floor($basePoints * $multiplier);

        $record = $this->create([
            'order_id' => $orderId,
            'order_sn' => $orderSn,
            'user_id' => (int)($order['user_id'] ?? 0),
            'order_type' => $orderType,
            'base_points' => $basePoints,
            'multiplier' => $multiplier,
            'final_points' => $finalPoints,
            'detail_json' => [
                'items' => $snapshot['items'] ?? [],
                'summary' => [
                    'base_points' => $basePoints,
                    'multiplier' => $multiplier,
                    'final_points' => $finalPoints,
                ],
            ],
            'status' => OrderGiftPointsRecord::STATUS_RECORDED,
        ]);

        return $record->toArray();
    }

    /**
     * 支付成功后冻结赠送积分。
     */
    public function freezeByOrder(array $order): void
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $record = $this->getModel()
            ->where('order_id', $orderId)
            ->lock(true)
            ->find();
        if (!$record) {
            $orderGoodsList = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
            $this->recordForOrder($order, $orderGoodsList);
            $record = $this->getModel()
                ->where('order_id', $orderId)
                ->lock(true)
                ->find();
        }
        if (!$record) {
            return;
        }

        $status = (int)($record['status'] ?? OrderGiftPointsRecord::STATUS_RECORDED);
        if (in_array($status, [OrderGiftPointsRecord::STATUS_FROZEN, OrderGiftPointsRecord::STATUS_RELEASED], true)) {
            return;
        }
        if ($status === OrderGiftPointsRecord::STATUS_REFUNDED) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $record->save([
            'status' => OrderGiftPointsRecord::STATUS_FROZEN,
            'freeze_at' => $now,
            'release_at' => date('Y-m-d H:i:s', strtotime('+' . $this->getFreezeDays() . ' days', strtotime($now))),
        ]);
    }

    /**
     * 释放到期的冻结赠送积分。
     *
     * @param int $limit 每轮处理数量。
     * @return array{total:int,released:int,failed:int}
     */
    public function releaseDue(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $records = $this->getModel()
            ->where('status', OrderGiftPointsRecord::STATUS_FROZEN)
            ->whereNotNull('release_at')
            ->where('release_at', '<=', date('Y-m-d H:i:s'))
            ->order('release_at asc, id asc')
            ->limit($limit)
            ->select()
            ->toArray();

        $result = ['total' => count($records), 'released' => 0, 'failed' => 0];
        foreach ($records as $record) {
            try {
                $this->releaseOrder((int)($record['order_id'] ?? 0));
                $result['released']++;
            } catch (\Throwable $throwable) {
                $result['failed']++;
                trace('order gift points release failed: ' . json_encode([
                    'order_id' => (int)($record['order_id'] ?? 0),
                    'message' => $throwable->getMessage(),
                ], JSON_UNESCAPED_UNICODE), 'error');
            }
        }

        return $result;
    }

    /**
     * 释放指定订单赠送积分并完成订单。
     *
     * @throws AdminResponseException
     */
    public function releaseOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->transaction(function () use ($orderId): void {
            $record = $this->getModel()
                ->where('order_id', $orderId)
                ->lock(true)
                ->find();
            if (!$record) {
                return;
            }

            $recordData = $record->toArray();
            $status = (int)($recordData['status'] ?? OrderGiftPointsRecord::STATUS_RECORDED);
            if ($status === OrderGiftPointsRecord::STATUS_RELEASED) {
                return;
            }
            if ($status !== OrderGiftPointsRecord::STATUS_FROZEN) {
                return;
            }

            $order = app()->make(OrderService::class)->getModel()
                ->where('id', $orderId)
                ->lock(true)
                ->find();
            if (!$order) {
                return;
            }

            $orderData = $order->toArray();
            if ((int)($orderData['status'] ?? Order::STATUS_PENDING) !== Order::STATUS_WAIT_REVIEW) {
                return;
            }
            if ((int)($orderData['refund_status'] ?? 0) === 1) {
                return;
            }

            $now = date('Y-m-d H:i:s');
            $finalPoints = (int)($recordData['final_points'] ?? 0);
            if ($finalPoints > 0) {
                app()->make(UserAssetService::class)->changeAsset(
                    (int)($recordData['user_id'] ?? 0),
                    UserAssetService::TYPE_POINTS,
                    (float)$finalPoints,
                    'in',
                    '订单赠送积分释放',
                    [
                        'source_type' => 'order_gift_points',
                        'order_no' => (string)($recordData['order_sn'] ?? ''),
                        'source_desc' => '内容购买赠送积分释放',
                    ]
                );
            }

            $record->save([
                'status' => OrderGiftPointsRecord::STATUS_RELEASED,
                'release_time' => $now,
            ]);

            $order->save([
                'status' => Order::STATUS_COMPLETED,
                'receive_time' => ($orderData['receive_time'] ?? null) ?: $now,
                'finish_time' => $now,
            ]);

            $freshData = array_merge($orderData, [
                'status' => Order::STATUS_COMPLETED,
                'receive_time' => ($orderData['receive_time'] ?? null) ?: $now,
                'finish_time' => $now,
            ]);
            app()->make(OrderMerchantSettlementService::class)->markOrderCompleted($freshData);
        });
    }

    /**
     * 退款成功时作废或冲减赠送积分。
     *
     * @throws AdminResponseException
     */
    public function rollbackByOrder(array $order): void
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $record = $this->getModel()
            ->where('order_id', $orderId)
            ->lock(true)
            ->find();
        if (!$record) {
            return;
        }

        $recordData = $record->toArray();
        $status = (int)($recordData['status'] ?? OrderGiftPointsRecord::STATUS_RECORDED);
        if ($status === OrderGiftPointsRecord::STATUS_REFUNDED) {
            return;
        }

        if ($status === OrderGiftPointsRecord::STATUS_RELEASED && (int)($recordData['final_points'] ?? 0) > 0) {
            app()->make(UserAssetService::class)->changeAsset(
                (int)($recordData['user_id'] ?? 0),
                UserAssetService::TYPE_POINTS,
                (float)((int)($recordData['final_points'] ?? 0)),
                'out',
                '订单退款扣回赠送积分',
                [
                    'source_type' => 'order_gift_points_refund',
                    'order_no' => (string)($recordData['order_sn'] ?? ''),
                    'source_desc' => '内容购买退款扣回赠送积分',
                ]
            );
        }

        $record->save([
            'status' => OrderGiftPointsRecord::STATUS_REFUNDED,
            'refund_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取当前用户可用的赠送积分倍率。
     */
    public function resolveMultiplier(int $userId): float
    {
        if ($userId <= 0) {
            return 1.0;
        }

        $memberService = app()->make(UserMemberService::class);
        if (!$memberService->isMemberEnabled() || !$memberService->isActiveMember($userId)) {
            return 1.0;
        }

        return max(1.0, $memberService->getPointsMultiplier());
    }

    /**
     * 判断订单类型是否需要赠送积分生命周期。
     */
    protected function isSupportedOrderType(int $orderType): bool
    {
        return in_array($orderType, [Order::ORDER_TYPE_CONTENT, Order::ORDER_TYPE_SPECIAL], true);
    }

    /**
     * 构建内容或专题赠送积分快照。
     */
    protected function buildGiftPointsSnapshot(int $orderType, array $orderGoodsList): array
    {
        if ($orderType === Order::ORDER_TYPE_CONTENT) {
            return $this->buildContentSnapshot($orderGoodsList);
        }

        if ($orderType === Order::ORDER_TYPE_SPECIAL) {
            return $this->buildSpecialSnapshot($orderGoodsList);
        }

        return ['base_points' => 0, 'items' => []];
    }

    /**
     * 构建单篇内容赠送积分快照。
     */
    protected function buildContentSnapshot(array $orderGoodsList): array
    {
        $basePoints = 0;
        $items = [];
        foreach ($orderGoodsList as $orderGoods) {
            $goodsInfo = is_array($orderGoods['goods_info'] ?? null) ? $orderGoods['goods_info'] : [];
            $product = is_array($goodsInfo['product'] ?? null) ? $goodsInfo['product'] : [];
            $giftPoints = max(0, (int)($product['gift_points'] ?? $goodsInfo['gift_points'] ?? 0));
            $price = max(0.0, (float)($product['price'] ?? $goodsInfo['price'] ?? $orderGoods['price'] ?? 0));
            if ($price <= 0 || $giftPoints <= 0) {
                continue;
            }

            $num = max(1, (int)($orderGoods['num'] ?? $goodsInfo['cart_num'] ?? 1));
            $linePoints = $giftPoints * $num;
            $basePoints += $linePoints;
            $items[] = [
                'type' => 'content',
                'article_id' => (int)($orderGoods['goods_id'] ?? $goodsInfo['goods_id'] ?? 0),
                'title' => (string)($orderGoods['goods_name'] ?? $goodsInfo['goods_name'] ?? ''),
                'price' => $price,
                'gift_points' => $giftPoints,
                'num' => $num,
                'line_points' => $linePoints,
            ];
        }

        return ['base_points' => $basePoints, 'items' => $items];
    }

    /**
     * 构建专题订单赠送积分快照。
     */
    protected function buildSpecialSnapshot(array $orderGoodsList): array
    {
        $specialIds = [];
        foreach ($orderGoodsList as $orderGoods) {
            $specialId = (int)($orderGoods['goods_id'] ?? 0);
            if ($specialId > 0) {
                $specialIds[] = $specialId;
            }
        }

        $articleIds = app()->make(SpecialContentService::class)->getArticleIdsBySpecialIds($specialIds);
        $articleMap = app()->make(ArticleService::class)->getMapByIds($articleIds);
        $basePoints = 0;
        $items = [];
        foreach ($articleMap as $article) {
            $price = max(0.0, (float)($article['price'] ?? 0));
            $giftPoints = max(0, (int)($article['gift_points'] ?? 0));
            if ($price <= 0 || $giftPoints <= 0) {
                continue;
            }

            $basePoints += $giftPoints;
            $items[] = [
                'type' => 'special_article',
                'article_id' => (int)($article['id'] ?? 0),
                'title' => (string)($article['title'] ?? ''),
                'price' => $price,
                'gift_points' => $giftPoints,
            ];
        }

        return ['base_points' => $basePoints, 'items' => $items];
    }

    /**
     * 冻结天数配置。
     */
    protected function getFreezeDays(): int
    {
        return max(0, (int)db_config(OrderConfigKeyEnum::GIFT_POINTS_FREEZE_DAYS, 7));
    }
}
