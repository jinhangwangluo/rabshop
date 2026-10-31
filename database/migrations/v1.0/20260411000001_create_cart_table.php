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
 * 购物车表
 */
class CreateCartTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cart', ['comment' => '购物车表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('order_type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '订单类型：1-内容订单，2-商品订单，3-专题订单']);
        $table->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '统一商品ID']);
        $table->addColumn('product_attr_unique', 'string', ['limit' => 64, 'default' => '', 'comment' => '商品属性唯一值']);
        $table->addColumn('cart_num', 'integer', ['limit' => 5, 'default' => 1, 'comment' => '商品数量']);
        $table->addColumn('is_pay', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否已下单：0-否，1-是']);
        $table->addColumn('is_new', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否立即购买：0-否，1-是']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '购物车商品状态：0-失效，1-有效']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['goods_id'], ['name' => 'idx_cart_goods_id']);
        $table->addIndex(['merchant_id'], ['name' => 'idx_cart_merchant_id']);
        $table->addIndex(['order_type'], ['name' => 'idx_cart_order_type']);
        $table->addIndex(['user_id', 'is_pay'], ['name' => 'idx_cart_user_pay']);
        $table->addIndex(['user_id', 'is_new'], ['name' => 'idx_cart_user_new']);
        $table->create();
    }
}
