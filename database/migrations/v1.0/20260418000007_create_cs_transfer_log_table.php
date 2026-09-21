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
 * 转接日志表
 */
class CreateCsTransferLogTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cs_transfer_log', ['comment' => '转接日志表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('session_id', 'string', ['limit' => 32, 'default' => '', 'comment' => '会话ID']);
        $table->addColumn('from_staff_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '原客服ID']);
        $table->addColumn('to_staff_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '目标客服ID']);
        $table->addColumn('remark', 'string', ['limit' => 500, 'default' => '', 'comment' => '转接备注']);
        $table->addColumn('transfer_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '转接时间']);
        $table->addTimestamps();
        $table->addIndex('session_id');
        $table->addIndex('from_staff_id');
        $table->addIndex('to_staff_id');
        $table->addIndex('transfer_time');
        $table->create();
    }
}
