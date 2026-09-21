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
 * 订单支付记录表
 */
class CreateOrderPayRecordTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('order_pay_record', ['comment' => '订单支付记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID']);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('pay_type', 'string', ['limit' => 20, 'default' => '', 'comment' => '支付方式']);
        $table->addColumn('trade_type', 'string', ['limit' => 30, 'default' => '', 'comment' => '交易场景：oa_jsapi/miniapp_jsapi/yue']);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '支付金额']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-待支付，1-已支付，2-失败']);
        $table->addColumn('openid', 'string', ['limit' => 64, 'default' => '', 'comment' => '支付openid']);
        $table->addColumn('prepay_id', 'string', ['limit' => 100, 'default' => '', 'comment' => '微信预支付ID']);
        $table->addColumn('transaction_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '微信支付单号']);
        $table->addColumn('request_payload', 'text', ['null' => true, 'comment' => '请求数据(JSON)']);
        $table->addColumn('response_payload', 'text', ['null' => true, 'comment' => '响应数据(JSON)']);
        $table->addColumn('notify_payload', 'text', ['null' => true, 'comment' => '回调数据(JSON)']);
        $table->addColumn('error_message', 'string', ['limit' => 255, 'default' => '', 'comment' => '失败原因']);
        $table->addColumn('pay_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '支付成功时间']);
        $table->addColumn('notify_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '支付回调时间']);
        $table->addTimestamps();
        $table->addIndex('order_id');
        $table->addIndex('order_sn', ['unique' => true]);
        $table->addIndex('user_id');
        $table->addIndex('pay_type');
        $table->addIndex('status');
        $table->addIndex('transaction_id');
        $table->create();
    }
}
