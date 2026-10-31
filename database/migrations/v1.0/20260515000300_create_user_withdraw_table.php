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
 * 创建用户提现记录表.
 */
class CreateUserWithdrawTable extends Migrator
{
    public function change()
    {
        $table = $this->table('user_withdraw', ['comment' => '用户提现记录']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'comment' => '用户ID']);
        $table->addColumn('source', 'string', ['limit' => 20, 'default' => 'commission', 'comment' => '提现来源: commission/balance']);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'comment' => '提现金额']);
        $table->addColumn('fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'comment' => '手续费']);
        $table->addColumn('actual_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'comment' => '实际到账金额']);
        $table->addColumn('bank_name', 'string', ['limit' => 80, 'default' => '', 'comment' => '开户银行']);
        $table->addColumn('bank_card_no', 'string', ['limit' => 32, 'default' => '', 'comment' => '银行卡号']);
        $table->addColumn('bank_holder_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '持卡人姓名']);
        $table->addColumn('status', 'boolean', ['default' => 0, 'comment' => '状态: 0=待审核, 1=已通过, 2=已拒绝']);
        $table->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '审核备注']);
        $table->addColumn('audit_time', 'datetime', ['null' => true, 'comment' => '审核时间']);
        $table->addColumn('created_at', 'datetime', ['comment' => '创建时间']);
        $table->addColumn('updated_at', 'datetime', ['comment' => '更新时间']);
        $table->addIndex('user_id');
        $table->addIndex('status');
        $table->addIndex('source');
        $table->create();
    }
}
