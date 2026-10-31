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
 * 订单商品表
 */
class CreateOrderGoodsTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('order_goods', ['comment' => '订单商品表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID']);
        $table->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('goods_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '商品名称']);
        $table->addColumn('order_type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '订单类型：1-内容订单，2-商品订单，3-专题订单']);
        $table->addColumn('goods_type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '商品类型：1-实物商品，2-虚拟商品，3-内容商品']);
        $table->addColumn('goods_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商品金额']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商品单价']);
        $table->addColumn('num', 'integer', ['limit' => 11, 'default' => 1, 'comment' => '商品数量']);
        $table->addColumn('total_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商品总价']);
        $table->addColumn('first_commission', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '一级佣金']);
        $table->addColumn('second_commission', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '二级佣金']);
        $table->addColumn('points_deduct_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '积分抵扣金额']);
        $table->addColumn('platform_coupon_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '平台优惠券优惠金额']);
        $table->addColumn('merchant_coupon_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商户优惠券优惠金额']);
        $table->addColumn('member_discount_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '会员折扣金额']);
        $table->addColumn('member_discount_rate', 'decimal', ['precision' => 4, 'scale' => 2, 'default' => 1.00, 'comment' => '会员折扣率']);
        $table->addColumn('goods_info', 'text', ['null' => true, 'comment' => '商品详细数据(JSON)']);
        $table->addColumn('first_referrer_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '一级推广人ID快照']);
        $table->addColumn('second_referrer_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '二级推广人ID快照']);
        $table->addColumn('commission_base_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商品行返佣计算基数']);
        $table->addColumn('first_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '一级返佣比例快照，百分比']);
        $table->addColumn('second_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '二级返佣比例快照，百分比']);
        $table->addColumn('commission_level', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '返佣等级快照：0无，1一级，2二级']);
        $table->addColumn('commission_mode', 'string', ['limit' => 20, 'default' => '', 'comment' => '返佣模式快照：all人人分销，specified指定分销']);
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('order_id');
        $table->addIndex('goods_id');
        $table->addIndex('merchant_id');
        $table->addIndex('order_type');
        $table->addIndex('goods_type');
        $table->addIndex('first_referrer_id');
        $table->addIndex('second_referrer_id');
        $table->addIndex('commission_level');
        $table->addIndex('commission_mode');
        $table->create();
    }
}
