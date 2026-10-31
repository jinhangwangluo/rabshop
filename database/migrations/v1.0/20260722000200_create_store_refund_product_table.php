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

use think\migration\Migrator;

/**
 * 退款商品明细表。
 */
class CreateStoreRefundProductTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('store_refund_product', [
            'comment' => '退款商品明细表',
            'collation' => 'utf8mb4_general_ci',
        ]);
        $table->addColumn('order_refund_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '退款记录ID']);
        $table->addColumn('refund_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '退款单号']);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID']);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号']);
        $table->addColumn('order_goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单商品ID']);
        $table->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('goods_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '商品名称']);
        $table->addColumn('sku_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => 'SKU ID']);
        $table->addColumn('refund_num', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '退款数量']);
        $table->addColumn('refund_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '退款金额']);
        $table->addColumn('platform_coupon_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '退款商品分摊的平台券补贴金额']);
        $table->addColumn('commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '平台抽佣比例(%)']);
        $table->addColumn('commission_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '平台抽佣金额']);
        $table->addColumn('merchant_income_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商户入账退款冲正金额']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-待退款，1-退款成功，2-已取消']);
        $table->addTimestamps();
        $table->addIndex('order_refund_id');
        $table->addIndex('refund_sn');
        $table->addIndex('order_id');
        $table->addIndex('order_sn');
        $table->addIndex('order_goods_id');
        $table->addIndex('merchant_id');
        $table->addIndex('status');
        $table->addIndex(['refund_sn', 'order_goods_id'], ['unique' => true, 'name' => 'uniq_refund_product']);
        $table->create();
    }
}
