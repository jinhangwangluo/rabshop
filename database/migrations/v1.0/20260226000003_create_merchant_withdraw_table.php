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
 * 商户提现申请表
 */
class CreateMerchantWithdrawTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('merchant_withdraw', ['comment' => '商户提现申请表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '提现金额']);
        $table->addColumn('withdraw_type', 'string', ['limit' => 20, 'default' => 'bank', 'comment' => '提现方式：bank-银行卡，wechat-微信，alipay-支付宝']);
        $table->addColumn('bank_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '银行名称']);
        $table->addColumn('bank_account', 'string', ['limit' => 50, 'default' => '', 'comment' => '银行账号']);
        $table->addColumn('account_name', 'string', ['limit' => 30, 'default' => '', 'comment' => '账户姓名']);
        $table->addColumn('payment_qrcode', 'string', ['limit' => 255, 'default' => '', 'comment' => '微信/支付宝收款码']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-待审核，1-审核通过，2-审核拒绝']);
        $table->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        $table->addColumn('audit_time', 'datetime', ['null' => true, 'comment' => '审核时间']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('merchant_id');
        $table->addIndex('withdraw_type');
        $table->addIndex('status');
        $table->create();
    }
}
