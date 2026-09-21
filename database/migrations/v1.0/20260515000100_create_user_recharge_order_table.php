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
 * 创建用户充值单表.
 */
class CreateUserRechargeOrderTable extends Migrator
{
    /**
     * 定义用户充值单表结构.
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('user_recharge_order', [
            'comment' => '用户充值单表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '充值单号']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('recharge_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '组合数据充值项ID，0为自定义充值']);
        $table->addColumn('order_type', 'string', ['limit' => 16, 'default' => 'recharge', 'comment' => '订单类型：recharge=充值 member=会员 live_coin=直播金币']);
        $table->addColumn('package_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '会员套餐组合数据ID']);
        $table->addColumn('package_key', 'string', ['limit' => 32, 'default' => '', 'comment' => '会员套餐标识 key']);
        $table->addColumn('duration_days', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '会员有效天数']);
        $table->addColumn('member_start_time', 'datetime', ['null' => true, 'comment' => '会员生效开始时间']);
        $table->addColumn('member_end_time', 'datetime', ['null' => true, 'comment' => '会员生效结束时间']);
        $table->addColumn('benefit_snapshot', 'text', ['null' => true, 'comment' => '购买时会员权益快照 JSON']);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '充值到账金额']);
        $table->addColumn('pay_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '实际支付金额']);
        $table->addColumn('pay_type', 'string', ['limit' => 20, 'default' => '', 'comment' => '支付方式']);
        $table->addColumn('trade_type', 'string', ['limit' => 32, 'default' => '', 'comment' => '支付交易类型']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0待支付，1已支付，2失败']);
        $table->addColumn('transaction_id', 'string', ['limit' => 80, 'default' => '', 'comment' => '第三方交易号']);
        $table->addColumn('pay_time', 'datetime', ['null' => true, 'comment' => '支付时间']);
        $table->addColumn('request_payload', 'text', ['null' => true, 'comment' => '支付请求参数']);
        $table->addColumn('response_payload', 'text', ['null' => true, 'comment' => '支付响应参数']);
        $table->addColumn('notify_payload', 'text', ['null' => true, 'comment' => '支付回调参数']);
        $table->addTimestamps();
        $table->addIndex('order_sn', ['unique' => true, 'name' => 'uniq_order_sn']);
        $table->addIndex(['user_id', 'status'], ['name' => 'user_status']);
        $table->addIndex('recharge_id', ['name' => 'recharge_id']);
        $table->addIndex('transaction_id', ['name' => 'transaction_id']);
        $table->create();
    }
}
