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
 * 订单评价表
 */
class CreateOrderReviewTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('order_review', ['comment' => '订单评价表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID']);
        $table->addColumn('order_goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单商品ID']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('star', 'integer', ['limit' => 1, 'default' => 5, 'comment' => '星级：1-5']);
        $table->addColumn('description_star', 'integer', ['limit' => 1, 'default' => 5, 'comment' => '描述相符评分：1-5']);
        $table->addColumn('logistics_star', 'integer', ['limit' => 1, 'default' => 5, 'comment' => '物流服务评分：1-5']);
        $table->addColumn('service_star', 'integer', ['limit' => 1, 'default' => 5, 'comment' => '服务态度评分：1-5']);
        $table->addColumn('content', 'text', ['null' => true, 'default' => null, 'comment' => '评语内容']);
        $table->addColumn('images', 'text', ['null' => true, 'default' => null, 'comment' => '评价图片，JSON数组']);
        $table->addColumn('is_anonymous', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '是否匿名评价：0-否，1-是']);
        $table->addColumn('admin_reply', 'text', ['null' => true, 'default' => null, 'comment' => '管理员回复内容']);
        $table->addColumn('reply_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '管理员回复时间']);
        $table->addIndex('order_goods_id');
        $table->addIndex(['order_id', 'order_goods_id'], ['unique' => true, 'name' => 'uniq_order_goods_id']);
        $table->addIndex('user_id');
        $table->addIndex('merchant_id');
        $table->addIndex('star');
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->create();
    }
}
