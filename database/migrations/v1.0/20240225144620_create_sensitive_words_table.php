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
 * 敏感词表
 * Class CreateSensitiveWordsTable
 */
class CreateSensitiveWordsTable extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('sensitive_words', ['comment' => '敏感词表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('word', 'string', ['limit' => 100, 'default' => '', 'comment' => '敏感词'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('admin_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '管理员ID'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态 1启用 0禁用'])
            ->addIndex('word', ['unique' => true, 'name' => 'word'])
            ->addIndex('status', ['name' => 'status'])
            ->addTimestamps()
            ->addSoftDelete()
            ->create();
    }

    public function down()
    {
        $this->table('sensitive_words')->drop();
    }
}
