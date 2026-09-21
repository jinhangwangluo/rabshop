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
 * 话术表
 */
class CreateCsScriptTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cs_script', ['comment' => '话术表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分类ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID 0=平台']);
        $table->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'comment' => '话术标题']);
        $table->addColumn('content', 'text', ['null' => true, 'comment' => '话术内容']);
        $table->addColumn('shortcut', 'string', ['limit' => 64, 'default' => '', 'comment' => '快捷键']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用,1-启用']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('use_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '使用次数']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('category_id');
        $table->addIndex('merchant_id');
        $table->addIndex('status');
        $table->addIndex('sort');
        $table->create();
    }
}
