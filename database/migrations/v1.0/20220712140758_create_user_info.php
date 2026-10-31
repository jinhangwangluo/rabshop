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
 * 用户详情表
 * Class UserInfo
 */
class CreateUserInfo extends Migrator
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
        $table = $this->table('user_info', ['comment' => '用户', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('uid', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '用户UID']);
        $table->addColumn('unionid', 'string', ['limit' => 30, 'default' => 0, 'comment' => 'unionid']);
        $table->addColumn('openid', 'string', ['limit' => 30, 'default' => 0, 'comment' => 'openid']);
        $table->addColumn('nickname', 'string', ['limit' => 50, 'default' => '', 'comment' => '用户昵称']);
        $table->addColumn('headimgurl', 'string', ['limit' => 256, 'default' => '', 'comment' => '头像']);
        $table->addColumn('sex', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '性别1=男，2=女，0=未知']);
        $table->addColumn('city', 'string', ['limit' => 64, 'default' => '', 'comment' => '所在城市']);
        $table->addColumn('language', 'string', ['limit' => 64, 'default' => '', 'comment' => '使用语言']);
        $table->addColumn('province', 'string', ['limit' => 64, 'default' => '', 'comment' => '所在省份']);
        $table->addColumn('country', 'string', ['limit' => 64, 'default' => '', 'comment' => '所在国家']);
        $table->addColumn('remark', 'string', ['limit' => 256, 'default' => '', 'comment' => '备注']);
        $table->addColumn('groupid', 'integer', ['limit' => 5, 'default' => 0, 'comment' => '所在分组']);
        $table->addColumn('tagid_list', 'string', ['limit' => 256, 'default' => null, 'comment' => '标签ID']);
        $table->addColumn('subscribe', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否订阅0=没有订阅，1=订阅']);
        $table->addColumn('subscribe_time', 'timestamp', ['default' => null, 'comment' => '订阅时间']);
        $table->addColumn('user_type', 'string', ['limit' => 11, 'default' => 'wechat', 'comment' => '用户类型']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('uid');
        $table->addIndex('openid');
        $table->addIndex(['uid', 'unionid']);
        $table->create();
    }
}
