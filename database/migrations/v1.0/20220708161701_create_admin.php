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
 * 管理员表
 * Class Admin
 */
class CreateAdmin extends Migrator
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
        $table = $this->table('admin', ['comment' => '后台管理员表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('account', 'string', ['limit' => 32, 'default' => '', 'comment' => '管理员账号']);
        $table->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '管理员头像']);
        $table->addColumn('password', 'string', ['limit' => 100, 'default' => '', 'comment' => '管理员密码']);
        $table->addColumn('real_name', 'string', ['limit' => 16, 'default' => '', 'comment' => '管理员姓名']);
        $table->addColumn('roles', 'string', ['limit' => 128, 'default' => '', 'comment' => '管理员权限']);
        $table->addColumn('level', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '等级']);
        $table->addColumn('last_ip', 'string', ['limit' => 16, 'default' => '', 'comment' => '管理员最后一次登录ip']);
        $table->addColumn('last_time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => '', 'timezone' => false, 'comment' => '管理员最后一次登录时间']);
        $table->addColumn('login_count', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '登录次数']);
        $table->addColumn('pid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '管理员上级id']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '管理员状态 1有效0无效']);
        $table->addColumn('platform_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '平台类型：1-平台端，2-商户端',]);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID（商户管理员所属商户）']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '绑定用户ID']);
        $table->addColumn('message_notify_enabled', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '消息提醒：0=关闭，1=开启']);
        $table->addColumn('message_notify_phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '消息提醒手机号']);
        $table->addSoftDelete();
        $table->addTimestamps();
        $table->addIndex(['account', 'status']);
        $table->addIndex(['platform_type', 'status'], ['name' => 'idx_platform_status']);
        $table->addIndex(['merchant_id'], ['name' => 'idx_merchant_id']);
        $table->addIndex('user_id', ['name' => 'idx_user_id']);
        $table->addIndex('account');
        $table->addIndex(['platform_type', 'status', 'message_notify_enabled'], ['name' => 'idx_platform_status_notify']);
        $table->create();

        // 插入默认管理员数据
        $rows = [
            [
                'account'     => 'admin',
                'real_name'   => '超级管理员',
                'password'    => '$2y$10$5HRQ8fcbH8COG86gEHkt0OPLZPCKnNE5F.lAhjeR4t.I/XAG2NK9G',//123456
                'status'      => 1,
                'create_time' => date('Y-m-d H:i:s'),
            ]
        ];
        $this->table('admin')->insert($rows)->saveData();
    }

    public function down()
    {
        $this->table('admin')->drop();
    }
}
