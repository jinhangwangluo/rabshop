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

use app\common\exception\AdminResponseException;
use app\common\model\order\OrderGoods;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\store\StoreProductService;
use app\common\service\store\StoreProductSkuService;

/**
 * 宿主负责的实物库存变动在促销插件卸载后仍可处理。
 */
class OrderInventoryService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 指定当前服务管理的持久化模型。
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return OrderGoods::class;
    }

    /**
     * 在调用方的订单事务中预留实物商品规格库存，仅预留一次。
     *
     * @param int $orderGoodsId 当前订单商品明细的内部编号。
     * @param int $productId 库存所属普通实物商品编号。
     * @param int $skuId 库存所属商品规格的内部编号。
     * @param int $quantity 需要预留或操作的实物件数。
     * @return void 无返回值；在订单事务内仅预留一次真实规格库存。
     */
    public function reserve(int $orderGoodsId, int $productId, int $skuId, int $quantity): void
    {
        if ($orderGoodsId <= 0 || $productId <= 0 || $skuId <= 0 || $quantity <= 0) {
            throw new AdminResponseException('库存预留参数无效');
        }
        $goods = $this->getModel()->where('id', $orderGoodsId)->lock(true)->find();
        if (!$goods || (int) $goods['goods_id'] !== $productId || (int) $goods['num'] !== $quantity) {
            throw new AdminResponseException('订单商品库存身份不一致');
        }
        if ((int) ($goods['stock_reserved_num'] ?? 0) > 0) {
            throw new AdminResponseException('订单商品已预留库存');
        }
        $snapshot = $this->snapshot($goods->toArray());
        if ((int) ($snapshot['sku_id'] ?? 0) !== $skuId) {
            throw new AdminResponseException('订单商品SKU身份不一致');
        }
        $this->lockProduct($productId);
        $skuService = app()->make(StoreProductSkuService::class);
        $sku = $skuService->getModel()->where('id', $skuId)->where('store_product_id', $productId)->where('status', 1)->lock(true)->find();
        if (!$sku || (string) $sku['uniqid'] !== (string) ($snapshot['product_attr_unique'] ?? '')) {
            throw new AdminResponseException('订单商品SKU不可销售');
        }
        // 行锁串行化身份校验，条件扣减仍作为最后一道库存下限保护。
        $affected = $skuService->getModel()->where('id', $skuId)->where('stock', '>=', $quantity)
            ->dec('stock', $quantity)->inc('sales', $quantity)->inc('stock_version', 1)->update();
        if ($affected !== 1) {
            throw new AdminResponseException('商品库存不足');
        }
        $goods->save(['stock_reserved_num' => $quantity]);
        $this->syncProduct($productId, $quantity);
    }

    /**
     * 取消订单后恢复预留库存，调用方须持有订单锁。
     *
     * @param int $orderId 当前业务订单的内部编号。
     * @return void 无返回值；按永久凭证恢复取消订单尚未回补的预留库存。
     */
    public function restoreCancelled(int $orderId): void
    {
        $goods = $this->getModel()->where('order_id', $orderId)->where('stock_reserved_num', '>', 0)->order('id asc')->lock(true)->select();
        foreach ($goods as $line) {
            $this->restoreLine($line, (int) $line['stock_reserved_num']);
        }
    }

    /**
     * 按实际累计退货件数回补库存，不超过审核成功的退款件数。
     *
     * @param int $orderId 已加锁的业务订单编号
     * @param array $lines 各订单商品明细的累计接收退货件数
     * @param array $approvedQuantities 以订单商品明细编号为键的已审核退款件数
     * @return void 无返回值；仅回补审核成功退款上限内尚未恢复的实际退货差额。
     */
    public function restoreReturned(int $orderId, array $lines, array $approvedQuantities): void
    {
        // 先验证全部明细的数量格式和唯一性，避免坏参数在部分库存回补后才被发现。
        $seen = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !isset($line['order_goods_id'], $line['returned_quantity'])
                || (!is_int($line['order_goods_id']) && !is_string($line['order_goods_id']))
                || (!is_int($line['returned_quantity']) && !is_string($line['returned_quantity']))
                || !preg_match('/^[1-9]\d{0,9}$/D', (string) $line['order_goods_id'])
                || !preg_match('/^\d{1,9}$/D', (string) $line['returned_quantity'])) {
                throw new AdminResponseException('实际退货数量参数无效');
            }
            $goodsId = (int) $line['order_goods_id'];
            if (isset($seen[$goodsId])) {
                throw new AdminResponseException('退货商品不能重复提交');
            }
            $seen[$goodsId] = true;
        }
        usort($lines, static fn (array $a, array $b): int => (int) ($a['order_goods_id'] ?? 0) <=> (int) ($b['order_goods_id'] ?? 0));
        foreach ($lines as $line) {
            $goods = $this->getModel()->where('id', (int) ($line['order_goods_id'] ?? 0))->where('order_id', $orderId)->lock(true)->find();
            if (!$goods) {
                throw new AdminResponseException('退货商品不属于当前订单');
            }
            $returned = (int) ($line['returned_quantity'] ?? 0);
            if ($returned > (int) ($goods['stock_reserved_num'] ?? 0)
                || $returned > (int) ($approvedQuantities[(int) $goods['id']] ?? 0)) {
                throw new AdminResponseException('退货数量超出库存预留或已审核退款数量');
            }
            $this->restoreLine($goods, $returned);
        }
    }

    /**
     * 仅回补尚未恢复的差额，由永久保留的订单商品明细锁保护。
     *
     * @param \think\Model $goods 已经加锁的永久订单商品模型及累计库存凭证。
     * @param int $cumulativeQuantity 该订单商品累计允许恢复的实物件数。
     * @return void 无返回值；按永久订单商品锁保护的累计差额恢复真实规格库存。
     */
    protected function restoreLine(\think\Model $goods, int $cumulativeQuantity): void
    {
        // 用永久累计凭证计算差额，重复通知和较旧的退货记录都不会重复补库存。
        $quantity = $cumulativeQuantity - (int) ($goods['stock_restored_num'] ?? 0);
        if ($quantity <= 0) {
            return;
        }
        $productId = (int) $goods['goods_id'];
        $this->lockProduct($productId);
        $snapshot = $this->snapshot($goods->toArray());
        $skuService = app()->make(StoreProductSkuService::class);
        $sku = $skuService->getModel()->withTrashed()->where('store_product_id', $productId)
            ->where('id', (int) ($snapshot['sku_id'] ?? 0))
            ->where('uniqid', (string) ($snapshot['product_attr_unique'] ?? ''))->lock(true)->find();
        if (!$sku) {
            throw new AdminResponseException('原订单SKU不存在，库存回补需人工处理');
        }
        $skuService->getModel()->withTrashed()->where('id', (int) $sku['id'])
            ->inc('stock', $quantity)->dec('sales', min($quantity, max(0, (int) $sku['sales'])))->inc('stock_version', 1)->update();
        $goods->save(['stock_restored_num' => $cumulativeQuantity]);
        $this->syncProduct($productId, -$quantity);
    }

    /**
     * 保持商品统计与真实商品规格库存一致。
     *
     * @param int $productId 库存所属普通实物商品编号。
     * @param int $salesChange 商品实际销量变化量，扣库存为正、回补为负。
     * @return void 无返回值；同步商品库存快照并更新实际销量。
     */
    protected function syncProduct(int $productId, int $salesChange): void
    {
        $service = app()->make(StoreProductService::class);
        $service->syncProductSnapshot($productId);
        $query = $service->getModel()->withTrashed()->where('id', $productId);
        if ($salesChange > 0) {
            $query->inc('sales', $salesChange)->update();
        } elseif ($salesChange < 0) {
            $query->where('sales', '>=', -$salesChange)->dec('sales', -$salesChange)->update();
        }
    }

    /**
     * 实物库存变动始终先锁商品再锁商品规格，包括普通销售和退货回补。
     *
     * @param int $productId 库存所属普通实物商品编号。
     * @return void 无返回值；锁定原商品以保持商品与规格统一的加锁顺序。
     */
    protected function lockProduct(int $productId): void
    {
        $product = app()->make(StoreProductService::class)->getModel()->withTrashed()->where('id', $productId)->lock(true)->find();
        if (!$product) {
            throw new AdminResponseException('原订单商品不存在，库存处理需人工核对');
        }
    }

    /**
     * 从宿主订单快照读取不可变的商品规格身份。
     *
     * @param array $goods 需要处理的永久订单商品明细。
     * @return array 从永久订单商品快照解析的不可变规格身份。
     */
    protected function snapshot(array $goods): array
    {
        $snapshot = $goods['goods_info'] ?? [];
        return is_array($snapshot) ? $snapshot : (json_decode((string) $snapshot, true) ?: []);
    }
}
