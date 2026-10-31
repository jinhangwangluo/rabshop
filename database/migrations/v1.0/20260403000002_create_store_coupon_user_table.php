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
 * 用户优惠券表
 */
class CreateStoreCouponUserTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('store_coupon_user', ['comment' => '用户优惠券表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('coupon_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '优惠券ID']);
        $table->addColumn('issue_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '发放记录ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('user_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('name', 'string', ['limit' => 64, 'default' => '', 'comment' => '优惠券名称']);
        $table->addColumn('discount_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '优惠金额/面值']);
        $table->addColumn('min_order_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '最低订单金额可用']);
        $table->addColumn('receive_type', 'string', ['limit' => 16, 'default' => 'receive', 'comment' => '获取方式：receive-领取 send-后台发送 give-满赠 new-新人 buy-买赠']);
        $table->addColumn('valid_start_time', 'datetime', ['null' => true, 'comment' => '有效开始时间']);
        $table->addColumn('valid_end_time', 'datetime', ['null' => true, 'comment' => '有效结束时间']);
        $table->addColumn('use_time', 'datetime', ['null' => true, 'comment' => '使用时间']);
        $table->addColumn('order_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '使用的订单ID']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-未使用 1-已使用 2-已过期']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('coupon_id');
        $table->addIndex('issue_id');
        $table->addIndex('merchant_id');
        $table->addIndex('user_id');
        $table->addIndex('status');
        $table->addIndex(['receive_type', 'issue_id']);
        $table->create();
    }
}
