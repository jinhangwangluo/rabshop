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
 * 优惠券发放记录表
 */
class CreateStoreCouponIssueTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('store_coupon_issue', ['comment' => '优惠券发放记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('coupon_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '优惠券ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('batch_no', 'string', ['limit' => 32, 'default' => '', 'comment' => '批次号']);
        $table->addColumn('issue_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '发放类型：1-后台发送 2-满赠 3-新人 4-买赠 5-首单赠送']);
        $table->addColumn('total_quantity', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '发放数量']);
        $table->addColumn('issued_quantity', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '已发放数量']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-待发放 1-发放中 2-已发放完成 3-已取消']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('coupon_id');
        $table->addIndex('merchant_id');
        $table->addIndex('batch_no');
        $table->create();
    }
}
