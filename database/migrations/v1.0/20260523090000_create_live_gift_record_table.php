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
 * 直播间礼物赠送流水表
 */
class CreateLiveGiftRecordTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('live_gift_record', ['comment' => '直播间礼物赠送流水表', 'collation' => 'utf8mb4_general_ci']);

        $table->addColumn('room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '赠送用户ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID快照']);
        $table->addColumn('live_anchor_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '主播ID快照']);
        $table->addColumn('gift_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '礼物ID']);
        $table->addColumn('gift_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '礼物名称快照']);
        $table->addColumn('gift_icon', 'string', ['limit' => 500, 'default' => '', 'comment' => '礼物图标快照']);
        $table->addColumn('gift_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '礼物单价快照']);
        $table->addColumn('quantity', 'integer', ['limit' => 11, 'default' => 1, 'comment' => '赠送数量']);
        $table->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '扣减金币总额']);
        $table->addColumn('exchange_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '主播可兑换金额快照']);
        $table->addColumn('score', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '单个礼物积分快照']);
        $table->addColumn('platform_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '平台抽成比例快照(%)']);
        $table->addColumn('merchant_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '商户抽成比例快照(%)']);
        $table->addColumn('platform_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '平台抽成金额']);
        $table->addColumn('merchant_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商户抽成金额']);
        $table->addColumn('anchor_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '主播所得金额']);
        $table->addColumn('send_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '赠送时间']);
        $table->addTimestamps();

        $table->addIndex('room_id');
        $table->addIndex('user_id');
        $table->addIndex('merchant_id');
        $table->addIndex('live_anchor_id');
        $table->addIndex('gift_id');
        $table->addIndex(['room_id', 'send_time'], ['name' => 'idx_room_send_time']);
        $table->create();
    }
}
