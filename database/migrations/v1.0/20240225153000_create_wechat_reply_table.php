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
 * 微信回复表
 * Class CreateWechatReplyTable
 */
class CreateWechatReplyTable extends Migrator
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
        $table = $this->table('wechat_reply', ['comment' => '微信回复表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('key', 'string', ['limit' => 100, 'default' => '', 'comment' => '关键词']);
        $table->addColumn('type', 'string', ['limit' => 20, 'default' => 'text', 'comment' => '回复类型: text-文本, image-图片, news-图文, voice-语音, video-视频']);
        $table->addColumn('data', 'text', ['comment' => '回复数据内容']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态 1启用 0禁用']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序']);
        $table->addSoftDelete();
        $table->addTimestamps();
        $table->addIndex('key');
        $table->addIndex('status');
        $table->addIndex('type');
        $table->create();
    }

    public function down()
    {
        $this->table('wechat_reply')->drop();
    }
}
