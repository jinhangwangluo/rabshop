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

use think\migration\Migrator;

/**
 * 宿主自有的支付期限和库存凭证在插件卸载后仍需保留。
 */
class AddPromotionInventorySafety extends Migrator
{
    /**
     * 补齐可信活动身份、固定支付期限、规格库存版本及订单商品库存凭证字段。
     *
     * @return void 无返回值。
     */
    public function up(): void
    {
        $cart = $this->table('cart');
        if (!$cart->hasColumn('promotion_code')) {
            $cart->addColumn('promotion_code', 'string', ['limit' => 64, 'default' => '', 'comment' => 'Server-validated promotion identity']);
        }
        $cart->update();

        $order = $this->table('order');
        if (!$order->hasColumn('pay_expire_time')) {
            $order->addColumn('pay_expire_time', 'timestamp', ['null' => true, 'comment' => 'Immutable payment deadline; NULL uses host policy']);
        }
        if (!$order->hasIndexByName('idx_order_payment_deadline')) {
            $order->addIndex(['status', 'pay_expire_time', 'id'], ['name' => 'idx_order_payment_deadline']);
        }
        $order->update();

        $sku = $this->table('store_product_sku');
        if (!$sku->hasColumn('stock_version')) {
            $sku->addColumn('stock_version', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => 'Inventory optimistic-lock version']);
        }
        $sku->update();

        $goods = $this->table('order_goods');
        foreach (['stock_reserved_num', 'stock_restored_num'] as $column) {
            if (!$goods->hasColumn($column)) {
                $goods->addColumn($column, 'integer', ['signed' => false, 'default' => 0, 'comment' => 'Permanent order inventory evidence']);
            }
        }
        $goods->update();
    }

    /**
     * 阻止普通回滚删除历史交易凭证，要求另行执行经审核的保留数据方案。
     *
     * @return void 无返回值。
     */
    public function down(): void
    {
        // 普通回滚不得删除库存凭证及已固定的支付期限。
        throw new RuntimeException('Promotion inventory evidence requires an audited data-preserving rollback.');
    }
}
