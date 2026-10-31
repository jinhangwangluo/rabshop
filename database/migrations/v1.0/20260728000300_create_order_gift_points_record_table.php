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
 * 订单赠送积分记录表。
 */
class CreateOrderGiftPointsRecordTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('order_gift_points_record', [
            'comment' => '订单赠送积分记录表',
            'collation' => 'utf8mb4_general_ci',
        ]);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID']);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('order_type', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '订单类型：1-内容，3-专题']);
        $table->addColumn('base_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '基础赠送积分']);
        $table->addColumn('multiplier', 'decimal', ['precision' => 8, 'scale' => 2, 'default' => '1.00', 'comment' => '会员积分倍数']);
        $table->addColumn('final_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '最终赠送积分']);
        $table->addColumn('detail_json', 'text', ['null' => true, 'default' => null, 'comment' => '赠送积分明细快照']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-已记录，1-冻结中，2-已释放，3-已退款作废']);
        $table->addColumn('freeze_at', 'timestamp', ['null' => true, 'default' => null, 'comment' => '冻结开始时间']);
        $table->addColumn('release_at', 'timestamp', ['null' => true, 'default' => null, 'comment' => '计划释放时间']);
        $table->addColumn('release_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '实际释放时间']);
        $table->addColumn('refund_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '退款作废时间']);
        $table->addTimestamps();
        $table->addIndex('order_id', ['unique' => true]);
        $table->addIndex('order_sn');
        $table->addIndex('user_id');
        $table->addIndex('order_type');
        $table->addIndex('status');
        $table->addIndex('release_at');
        $table->create();
    }
}
