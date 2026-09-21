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
 * 商户入驻申请记录表
 */
class CreateMerchantApplyTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('merchant_apply', ['comment' => '商户入驻申请记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('mer_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '商户名称']);
        $table->addColumn('mer_phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '商户电话']);
        $table->addColumn('mer_address', 'string', ['limit' => 255, 'default' => '', 'comment' => '商户地址']);
        $table->addColumn('contact_name', 'string', ['limit' => 30, 'default' => '', 'comment' => '联系人姓名']);
        $table->addColumn('contact_phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '联系人电话']);
        $table->addColumn('business_license', 'string', ['limit' => 255, 'default' => '', 'comment' => '营业执照']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-待审核，1-审核通过，2-审核拒绝']);
        $table->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        $table->addColumn('audit_time', 'datetime', ['null' => true, 'comment' => '审核时间']);
        $table->addColumn('account', 'string', ['limit' => 32, 'default' => '', 'comment' => '管理员账号']);
        $table->addColumn('password', 'string', ['limit' => 100, 'default' => '', 'comment' => '管理员密码']);
        $table->addColumn('uid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '关联用户ID']);
        $table->addColumn('real_name', 'string', ['limit' => 32, 'default' => '', 'comment' => '管理员姓名']);
        $table->addColumn('fail_msg', 'string', ['limit' => 255, 'default' => '', 'comment' => '审核失败原因']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex(['account'], ['name' => 'idx_account']);
        $table->addIndex('mer_name', ['unique' => true]);
        $table->addIndex('status');
        $table->create();
    }
}
