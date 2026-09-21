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
 * 用户已购权益记录表
 */
class CreateUserPurchaseRecordTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('user_purchase_record', ['comment' => '用户已购权益记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID']);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('order_type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '权益类型：1-内容，3-专题']);
        $table->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '权益资源ID']);
        $table->addColumn('source_order_type', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '来源订单类型']);
        $table->addColumn('source_goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '来源资源ID']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-失效，1-有效']);
        $table->addColumn('extra_info', 'text', ['null' => true, 'comment' => '扩展信息(JSON)']);
        $table->addColumn('grant_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '权益生效时间']);
        $table->addColumn('revoke_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '权益失效时间']);
        $table->addTimestamps();
        $table->addIndex('user_id');
        $table->addIndex('order_id');
        $table->addIndex('merchant_id');
        $table->addIndex('order_type');
        $table->addIndex('goods_id');
        $table->addIndex('status');
        $table->addIndex(['order_id', 'order_type', 'goods_id'], ['name' => 'uk_order_rights', 'unique' => true]);
        $table->create();
    }
}
