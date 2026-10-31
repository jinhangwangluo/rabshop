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
 * 微信回复关键词表
 * Class CreateWechatReplyKeyTable
 */
class CreateWechatReplyKeyTable extends Migrator
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
        $table = $this->table('wechat_reply_key', ['comment' => '微信回复关键词表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('reply_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '回复ID']);
        $table->addColumn('keyword', 'string', ['limit' => 100, 'default' => '', 'comment' => '关键词']);
        $table->addColumn('match_type', 'string', ['limit' => 20, 'default' => 'equal', 'comment' => '匹配类型: equal-完全匹配, contain-包含匹配, regexp-正则匹配']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序']);
        $table->addSoftDelete();
        $table->addTimestamps();
        $table->addIndex('reply_id');
        $table->addIndex('keyword');
        $table->addIndex('match_type');
        $table->create();
    }

    public function down()
    {
        $this->table('wechat_reply_key')->drop();
    }
}
