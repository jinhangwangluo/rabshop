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
 * 优惠券关联表（商品券/品类券关联商品/品类）
 */
class CreateStoreCouponRelationTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('store_coupon_relation', ['comment' => '优惠券关联表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('coupon_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '优惠券ID']);
        $table->addColumn('relation_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '关联类型：1-内容 2-专题 3-商品(预留) 4-品类内容 5-品类专题 6-品类商品(预留)']);
        $table->addColumn('relation_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '关联ID（商品ID/品类ID）']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('coupon_id');
        $table->addIndex(['relation_type', 'relation_id']);
        $table->create();
    }
}
