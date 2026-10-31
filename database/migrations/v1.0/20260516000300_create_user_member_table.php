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
 * 创建用户会员表.
 */
class CreateUserMemberTable extends Migrator
{
    public function change()
    {
        $table = $this->table('user_member', [
            'comment' => '用户会员表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('start_time', 'datetime', ['null' => true, 'comment' => '会员开始时间']);
        $table->addColumn('end_time', 'datetime', ['null' => true, 'comment' => '会员结束时间']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0已过期 1有效']);
        $table->addColumn('last_order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '最近一笔会员订单号']);
        $table->addColumn('benefit_snapshot', 'text', ['null' => true, 'comment' => '当前会员权益快照 JSON']);
        $table->addTimestamps();
        $table->addIndex('user_id', ['unique' => true, 'name' => 'uniq_user_id']);
        $table->addIndex(['status', 'end_time'], ['name' => 'status_end_time']);
        $table->create();
    }
}
