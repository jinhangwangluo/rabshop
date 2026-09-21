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
 * 优惠券表
 */
class CreateStoreCouponTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('store_coupon', ['comment' => '优惠券表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('is_time_limited', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否限时领取：0-否 1-是']);
        $table->addColumn('receive_start_time', 'datetime', ['null' => true, 'comment' => '领取开始时间']);
        $table->addColumn('receive_end_time', 'datetime', ['null' => true, 'comment' => '领取结束时间']);
        $table->addColumn('is_quantity_limited', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否限量：0-否 1-是']);
        $table->addColumn('total_quantity', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '发放总量']);
        $table->addColumn('remain_quantity', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '剩余数量']);
        $table->addColumn('distribute_type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '发放类型：0-领取 1-消费满赠 2-新人 3-买赠 4-首单赠送']);
        $table->addColumn('min_consume_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '最低消费金额(满赠)']);
        $table->addColumn('name', 'string', ['limit' => 64, 'default' => '', 'comment' => '优惠券名称']);
        $table->addColumn('discount_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '优惠金额/面值']);
        $table->addColumn('min_order_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '最低订单金额可用']);
        $table->addColumn('expire_type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '有效期类型：0-有效天数 1-固定时间段']);
        $table->addColumn('valid_days', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '有效天数']);
        $table->addColumn('valid_start_time', 'datetime', ['null' => true, 'comment' => '使用开始时间']);
        $table->addColumn('valid_end_time', 'datetime', ['null' => true, 'comment' => '使用结束时间']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 1, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-关闭 1-开启 -1-失效']);
        $table->addColumn('scope_type', 'integer', ['limit' => 2, 'default' => 0, 'comment' => '适用范围：0-店铺券 1-商品券 10-平台通用券 11-平台品类券']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('merchant_id');
        $table->addIndex('scope_type');
        $table->addIndex('status');
        $table->create();
    }
}
