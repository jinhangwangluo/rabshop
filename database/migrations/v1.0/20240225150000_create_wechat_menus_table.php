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
 * 微信菜单表
 * Class CreateWechatMenusTable
 */
class CreateWechatMenusTable extends Migrator
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
        $table = $this->table('wechat_menus', ['comment' => '微信菜单表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('pid', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '父级id']);
        $table->addColumn('name', 'string', ['limit' => 40, 'default' => '', 'comment' => '菜单名称']);
        $table->addColumn('type', 'string', ['limit' => 20, 'default' => 'click', 'comment' => '菜单类型: click-点击推事件, view-跳转URL, miniprogram-跳转小程序']);
        $table->addColumn('key', 'string', ['limit' => 128, 'default' => '', 'comment' => '菜单KEY值，用于消息接口推送']);
        $table->addColumn('url', 'string', ['limit' => 255, 'default' => '', 'comment' => '网页链接']);
        $table->addColumn('appid', 'string', ['limit' => 32, 'default' => '', 'comment' => '小程序的appid']);
        $table->addColumn('pagepath', 'string', ['limit' => 255, 'default' => '', 'comment' => '小程序的页面路径']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态 1启用 0禁用']);
        $table->addSoftDelete();
        $table->addTimestamps();
        $table->addIndex('pid');
        $table->addIndex('status');
        $table->create();
    }

    public function down()
    {
        $this->table('wechat_menus')->drop();
    }
}
