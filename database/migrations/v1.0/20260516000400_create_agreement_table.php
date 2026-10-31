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
 * 创建协议表.
 */
class CreateAgreementTable extends Migrator
{
    public function change()
    {
        $table = $this->table('agreement', [
            'comment' => '协议管理表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('type', 'string', ['limit' => 32, 'default' => '', 'comment' => '协议类型：member=会员协议']);
        $table->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '协议标题']);
        $table->addColumn('content', 'text', ['null' => true, 'comment' => '协议内容']);
        $table->addColumn('version', 'string', ['limit' => 20, 'default' => '', 'comment' => '协议版本号']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0禁用 1启用']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addTimestamps();
        $table->addIndex('type', ['name' => 'idx_type']);
        $table->create();
    }
}
