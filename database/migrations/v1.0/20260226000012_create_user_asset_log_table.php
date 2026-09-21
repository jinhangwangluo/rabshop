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
 * 用户资产记录表
 */
class CreateUserAssetLogTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('user_asset_log', ['comment' => '用户资产记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('type', 'string', ['limit' => 20, 'default' => '', 'comment' => '资产类型：balance-余额，points-积分，coins-金币，exp-经验值，commission-佣金']);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '变动金额']);
        $table->addColumn('change_type', 'string', ['limit' => 10, 'default' => '', 'comment' => '变动类型：in-收入，out-支出']);
        $table->addColumn('before', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '变动前金额']);
        $table->addColumn('after', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '变动后金额']);
        $table->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        // 来源类型：order_commission-下级下单佣金，manual-手动调整，recharge-充值，consume-消费等
        $table->addColumn('source_type', 'string', ['limit' => 100, 'default' => '', 'comment' => '来源类型：order_commission/manual/recharge/consume等', 'after' => 'remark']);
        // 关联订单号（佣金来源订单号，如下级下单）
        $table->addColumn('order_no', 'string', ['limit' => 50, 'default' => '', 'comment' => '关联订单号', 'after' => 'source_type']);
        // 来源用户ID（如产生佣金的下级用户UID）
        $table->addColumn('source_user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '来源用户ID（如下级用户UID）', 'after' => 'order_no']);
        // 来源描述（冗余说明，如"下级xxx下单获得佣金"）
        $table->addColumn('source_desc', 'string', ['limit' => 255, 'default' => '', 'comment' => '来源描述', 'after' => 'source_user_id']);

        $table->addIndex('source_type');
        $table->addIndex('order_no');
        $table->addIndex('source_user_id');
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('user_id');
        $table->addIndex('type');
        $table->addIndex('change_type');
        $table->create();
    }
}
