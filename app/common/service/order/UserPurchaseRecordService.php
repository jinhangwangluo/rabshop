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

use app\common\model\order\Order;
use app\common\model\order\UserPurchaseRecord;
use app\common\service\BaseService;
use app\common\service\content\SpecialContentService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 用户已购权益记录服务
 *
 * @package app\common\service\order
 */
class UserPurchaseRecordService extends BaseService
{
    /**
     * 指定当前服务管理的持久化模型。
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return UserPurchaseRecord::class;
    }

    /**
     * 获取已购映射
     *
     * @param int $userId 当前业务用户编号。
     * @param array $resourceIdsByType 按订单资源类型分组的资源编号列表。
     * @return array 按订单资源类型与编号建立的有效已购权益映射。
     */
    public function getPurchasedMap(int $userId, array $resourceIdsByType): array
    {
        if ($userId <= 0) {
            return [];
        }

        $map = [];
        foreach ($resourceIdsByType as $orderType => $goodsIds) {
            $goodsIds = $this->normalizeIds($goodsIds);
            if ($goodsIds === []) {
                continue;
            }

            $records = $this->getModel()
                ->where('user_id', $userId)
                ->where('status', 1)
                ->where('order_type', (int) $orderType)
                ->whereIn('goods_id', $goodsIds)
                ->column('goods_id');

            foreach ($records as $goodsId) {
                $map[$orderType . '_' . $goodsId] = true;
            }
        }

        return $map;
    }

    /**
     * 获取用户已购专题ID列表。
     *
     * @param int $userId 用户ID。
     * @param int $limit 限制条数。
     * @return array 当前用户有效已购专题的去重编号列表。
     */
    public function getUserPurchasedSpecialIds(int $userId, int $limit = 20): array
    {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $specialIds = $this->getModel()
            ->where('user_id', $userId)
            ->where('status', 1)
            ->where('order_type', Order::ORDER_TYPE_SPECIAL)
            ->order('grant_time', 'desc')
            ->limit($limit)
            ->column('goods_id');

        return $this->normalizeIds($specialIds);
    }

    /**
     * 判断用户是否拥有指定权益。
     *
     * @param int $userId 当前业务用户编号。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @return bool 当前用户是否拥有指定资源的有效购买权益。
     */
    public function hasPurchased(int $userId, int $orderType, int $goodsId): bool
    {
        if ($userId <= 0 || $orderType <= 0 || $goodsId <= 0) {
            return false;
        }

        return (int)$this->getModel()
            ->where('user_id', $userId)
            ->where('order_type', $orderType)
            ->where('goods_id', $goodsId)
            ->where('status', 1)
            ->value('id') > 0;
    }

    /**
     * 获取内容真实学习人数映射。
     *
     * @param array $articleIds 内容 ID 列表。
     * @return array<int, int> 键为内容标识，值为真实学习人数。
     */
    public function getContentLearnCountMap(array $articleIds): array
    {
        $articleIds = $this->normalizeIds($articleIds);
        if ($articleIds === []) {
            return [];
        }

        $rows = $this->getModel()
            ->where('status', 1)
            ->where('order_type', Order::ORDER_TYPE_CONTENT)
            ->whereIn('goods_id', $articleIds)
            ->field('goods_id, COUNT(*) AS total')
            ->group('goods_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $goodsId = (int)($row['goods_id'] ?? 0);
            if ($goodsId > 0) {
                $map[$goodsId] = (int)($row['total'] ?? 0);
            }
        }

        return $map;
    }

    /**
     * 订单权益记录。
     *
     * @param int|array $order 订单 ID 或已加载的订单快照。
     * @param array|null $orderGoodsList 已加载的订单商品；传入后不再重复查询。
     * @return void 无返回值；根据成交商品快照登记已支付资源权益。
     */
    public function recordOrderRights(int|array $order, ?array $orderGoodsList = null): void
    {
        if (is_array($order)) {
            $orderData = $order;
            $orderId = (int)($orderData['id'] ?? 0);
        } else {
            $orderId = $order;
            $orderModel = app()->make(OrderService::class)->get(
                $orderId,
                ['id', 'user_id', 'order_sn', 'merchant_id']
            );
            if (!$orderModel) {
                return;
            }
            $orderData = $orderModel->toArray();
        }
        if ($orderId <= 0) {
            return;
        }
        $orderGoodsList ??= app()->make(OrderGoodsService::class)->getByOrderId($orderId);
        if ($orderGoodsList === []) {
            return;
        }

        $userId = (int) ($orderData['user_id'] ?? 0);
        $orderSn = (string) ($orderData['order_sn'] ?? '');
        $merchantId = (int) ($orderData['merchant_id'] ?? 0);

        foreach ($orderGoodsList as $orderGoods) {
            $orderType = (int) ($orderGoods['order_type'] ?? Order::ORDER_TYPE_GOODS);
            $goodsId = (int) ($orderGoods['goods_id'] ?? 0);

            if ($userId <= 0 || $goodsId <= 0) {
                continue;
            }

            if ($orderType === Order::ORDER_TYPE_CONTENT) {
                $this->createRightIfNotExists([
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'order_sn' => $orderSn,
                    'merchant_id' => $merchantId,
                    'order_type' => Order::ORDER_TYPE_CONTENT,
                    'goods_id' => $goodsId,
                    'source_order_type' => Order::ORDER_TYPE_CONTENT,
                    'source_goods_id' => $goodsId,
                    'status' => 1,
                    'grant_time' => date('Y-m-d H:i:s'),
                    'extra_info' => [
                        'grant_type' => 'content_purchase',
                    ],
                ]);
                continue;
            }

            if ($orderType !== Order::ORDER_TYPE_SPECIAL) {
                continue;
            }

            $this->createRightIfNotExists([
                'user_id' => $userId,
                'order_id' => $orderId,
                'order_sn' => $orderSn,
                'merchant_id' => $merchantId,
                'order_type' => Order::ORDER_TYPE_SPECIAL,
                'goods_id' => $goodsId,
                'source_order_type' => Order::ORDER_TYPE_SPECIAL,
                'source_goods_id' => $goodsId,
                'status' => 1,
                'grant_time' => date('Y-m-d H:i:s'),
                'extra_info' => [
                    'grant_type' => 'special_purchase',
                ],
            ]);

            $articleIds = app()->make(SpecialContentService::class)->getArticleIdsBySpecialIds([$goodsId]);
            foreach ($articleIds as $articleId) {
                $this->createRightIfNotExists([
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'order_sn' => $orderSn,
                    'merchant_id' => $merchantId,
                    'order_type' => Order::ORDER_TYPE_CONTENT,
                    'goods_id' => (int) $articleId,
                    'source_order_type' => Order::ORDER_TYPE_SPECIAL,
                    'source_goods_id' => $goodsId,
                    'status' => 1,
                    'grant_time' => date('Y-m-d H:i:s'),
                    'extra_info' => [
                        'grant_type' => 'special_content_cover',
                        'special_id' => $goodsId,
                    ],
                ]);
            }
        }
    }

    /**
     * 订单权益失效
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；撤销指定退款订单产生的购买权益。
     */
    public function revokeOrderRights(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->getModel()
            ->where('order_id', $orderId)
            ->where('status', 1)
            ->update([
                'status' => 0,
                'revoke_time' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 创建权益记录
     *
     * @param array $data 待保存的业务字段。
     * @return void 无返回值；仅在相同订单资源权益不存在时创建记录。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function createRightIfNotExists(array $data): void
    {
        $existsId = (int)$this->getModel()
            ->where('order_id', (int) ($data['order_id'] ?? 0))
            ->where('order_type', (int) ($data['order_type'] ?? 0))
            ->where('goods_id', (int) ($data['goods_id'] ?? 0))
            ->value('id');

        if ($existsId > 0) {
            return;
        }

        $this->create($data);
    }

    /**
     * 标准化ID列表
     *
     * @param array $ids 待清理去重的资源编号列表。
     * @return array 转换为正整数并去重的编号列表。
     */
    protected function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }
}
