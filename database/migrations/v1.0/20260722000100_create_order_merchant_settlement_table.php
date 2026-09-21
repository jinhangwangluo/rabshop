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
 * 创建订单商户结算明细表。
 */
class CreateOrderMerchantSettlementTable extends Migrator
{
    public function change(): void
    {
        $this->createSettlementTable();
    }

    private function createSettlementTable(): void
    {
        $table = $this->table('order_merchant_settlement', [
            'comment' => '订单商户结算明细表',
            'collation' => 'utf8mb4_general_ci',
        ]);
        $table->addColumn('order_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '订单ID']);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('pay_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '结算订单实付金额']);
        $table->addColumn('commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '平台抽佣比例(%)']);
        $table->addColumn('commission_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '平台抽佣金额']);
        $table->addColumn('merchant_income_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商户入账金额']);
        $table->addColumn('refunded_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '累计退款金额']);
        $table->addColumn('reversed_commission_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '已冲正平台抽佣金额']);
        $table->addColumn('reversed_merchant_income_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '已冲正商户入账金额']);
        $table->addColumn('status', 'integer', [
            'limit' => 1,
            'default' => 0,
            'comment' => '状态：0-待入账，1-冻结中，2-已释放，3-部分退款，4-已退款冲正',
        ]);
        $table->addColumn('settle_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '入账时间']);
        $table->addColumn('refund_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '退款冲正时间']);
        $table->addColumn('freeze_start_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '商户资金冻结起算时间']);
        $table->addColumn('release_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '商户资金释放时间']);
        $table->addTimestamps();
        $table->addIndex('order_id');
        $table->addIndex('order_sn');
        $table->addIndex('merchant_id');
        $table->addIndex('status');
        $table->addIndex('release_time');
        $table->addIndex(['order_id', 'merchant_id'], ['unique' => true, 'name' => 'uniq_order_merchant']);
        $table->create();
    }
}
