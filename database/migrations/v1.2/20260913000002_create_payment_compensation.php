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
 * 迟到付款退款补偿由宿主持久化管理，不依赖活动存续。
 */
class CreatePaymentCompensation extends Migrator
{
    /**
     * 创建迟到付款退款补偿表及交易、退款请求和重试索引，已建表时跳过。
     *
     * @return void 无返回值。
     */
    public function up(): void
    {
        if ($this->hasTable('order_payment_compensation')) {
            return;
        }
        $this->table('order_payment_compensation', ['engine' => 'InnoDB'])
            ->addColumn('order_id', 'integer', ['signed' => false])
            ->addColumn('order_sn', 'string', ['limit' => 64])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('pay_type', 'string', ['limit' => 32])
            ->addColumn('trade_type', 'string', ['limit' => 32, 'default' => ''])
            ->addColumn('transaction_id', 'string', ['limit' => 128])
            ->addColumn('refund_sn', 'string', ['limit' => 64])
            ->addColumn('amount_fen', 'biginteger', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'pending'])
            ->addColumn('attempts', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('next_attempt_time', 'timestamp')
            ->addColumn('error_code', 'string', ['limit' => 64, 'default' => ''])
            ->addColumn('create_time', 'timestamp')
            ->addColumn('update_time', 'timestamp')
            ->addIndex(['pay_type', 'transaction_id'], ['unique' => true, 'name' => 'uk_payment_compensation_receipt'])
            ->addIndex(['refund_sn'], ['unique' => true, 'name' => 'uk_payment_compensation_refund'])
            ->addIndex(['status', 'next_attempt_time', 'id'], ['name' => 'idx_payment_compensation_retry'])
            ->addIndex(['order_id', 'status'], ['name' => 'idx_payment_compensation_order'])
            ->create();
    }

    /**
     * 阻止普通回滚删除历史交易凭证，要求另行执行经审核的保留数据方案。
     *
     * @return void 无返回值。
     */
    public function down(): void
    {
        throw new RuntimeException('Payment compensation evidence requires an audited data-preserving rollback.');
    }
}
