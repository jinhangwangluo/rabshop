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
use app\common\model\order\OrderGoods;
use app\common\service\BaseService;

/**
 * 订单商品服务
 */
class OrderGoodsService extends BaseService
{
    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return OrderGoods::class;
    }

    /**
     * 批量保存订单商品
     */
    public function saveBatch(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->getModel()->saveAll($rows);
    }

    /**
     * 获取订单商品列表
     */
    public function getByOrderId(int $orderId): array
    {
        return $this->getModel()
            ->where('order_id', $orderId)
            ->select()
            ->toArray();
    }

    /**
     * 汇总订单商品佣金，只返回两项聚合金额。
     *
     * @param int $orderId 订单 ID。
     * @return array{first: float, second: float}
     */
    public function sumCommissionAmounts(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['first' => 0.0, 'second' => 0.0];
        }

        $row = $this->getModel()
            ->where('order_id', $orderId)
            ->fieldRaw('COALESCE(SUM(first_commission), 0) AS first_amount, COALESCE(SUM(second_commission), 0) AS second_amount')
            ->find();

        return [
            'first' => (float)($row['first_amount'] ?? 0),
            'second' => (float)($row['second_amount'] ?? 0),
        ];
    }

    /**
     * 获取专题购买数量映射
     */
    public function getSpecialBuyCountMapByOrderId(int $orderId): array
    {
        $specialGoodsList = $this->getModel()
            ->where('order_id', $orderId)
            ->where('order_type', Order::ORDER_TYPE_SPECIAL)
            ->field(['goods_id', 'num'])
            ->select()
            ->toArray();

        if ($specialGoodsList === []) {
            return [];
        }

        $buyCountMap = [];
        foreach ($specialGoodsList as $item) {
            $specialId = (int) ($item['goods_id'] ?? 0);
            $buyNum = max(1, (int) ($item['num'] ?? 1));
            if ($specialId <= 0) {
                continue;
            }

            $buyCountMap[$specialId] = ($buyCountMap[$specialId] ?? 0) + $buyNum;
        }

        return $buyCountMap;
    }
}
