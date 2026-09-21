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
 * 财务记录表
 */
class CreateFinanceRecordTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('finance_record', ['comment' => '财务记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('pid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '父流水ID，0表示主流水或无父级']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('type', 'string', ['limit' => 20, 'default' => '', 'comment' => '类型：order-订单，withdraw-提现，recharge-充值']);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '金额']);
        $table->addColumn('change_type', 'string', ['limit' => 10, 'default' => '', 'comment' => '变动类型：in-收入，out-支出']);
        $table->addColumn('fund_status', 'string', ['limit' => 20, 'default' => 'available', 'comment' => '资金状态：frozen-冻结中，available-可提现，reversed-已冲正']);
        $table->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号']);
        $table->addColumn('freeze_start_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '冻结起算时间']);
        $table->addColumn('release_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '冻结释放时间']);
        $table->addColumn('live_room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID']);
        $table->addColumn('live_gift_record_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播礼物流水ID']);
        $table->addColumn('live_anchor_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '主播ID']);
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('pid');
        $table->addIndex('user_id');
        $table->addIndex('merchant_id');
        $table->addIndex('type');
        $table->addIndex('change_type');
        $table->addIndex('fund_status');
        $table->addIndex('release_time');
        $table->addIndex('live_room_id');
        $table->addIndex('live_gift_record_id');
        $table->addIndex('live_anchor_id');
        $table->create();
    }
}
