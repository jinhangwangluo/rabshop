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
 * 管理员身份
 * Class AdminRole
 */
class CreateAdminRole extends Migrator
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
        $table = $this->table('admin_role', ['comment' => '身份管理表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('role_name', 'string', ['limit' => 32, 'default' => '', 'comment' => '身份管理名称']);
        $table->addColumn('rule', 'text', ['comment' => '身份管理权限(menus_id)']);
        $table->addColumn('store_manage_ids', 'text', ['comment' => '门店权限(store_manage的id)']);
        $table->addColumn('pid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '上级身份id']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态']);
        $table->addColumn('platform_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '平台类型：1-平台端，2-商户端']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID（商户角色所属商户）']);
        $table->addIndex(['platform_type', 'status'], ['name' => 'idx_platform_status']);
        $table->addIndex(['merchant_id'], ['name' => 'idx_merchant_id']);
        $table->addTimestamps();
        $table->create();
    }
}
