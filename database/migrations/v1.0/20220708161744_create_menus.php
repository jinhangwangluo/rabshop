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
 * 菜单表
 * Class Menus
 */
class CreateMenus extends Migrator
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
        $table = $this->table('menus', ['comment' => '菜单表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('pid', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '父级id']);
        $table->addColumn('icon', 'string', ['limit' => 50, 'default' => '', 'comment' => '图标']);
        $table->addColumn('name', 'string', ['limit' => 32, 'default' => '', 'comment' => '按钮名']);
        $table->addColumn('url', 'string', ['limit' => 100, 'default' => '', 'comment' => 'api接口地址/路由地址']);
        $table->addColumn('methods', 'string', ['limit' => 255, 'default' => '', 'comment' => '提交方式POST GET PUT DELETE']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('access', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '子管理员是否可用']);
        $table->addColumn('path', 'string', ['limit' => 255, 'default' => '', 'comment' => '路径']);
        $table->addColumn('auth_type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否为菜单 0菜单 1功能']);
        $table->addColumn('auth_iden', 'string', ['limit' => 50, 'default' => '', 'comment' => '权限标识']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态 1开启 0关闭']);
        $table->addColumn('is_hidden', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '隐藏菜单 1是 0否']);
        $table->addColumn('platform_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '平台类型：1-平台端，2-商户端']);
        $table->addSoftDelete();
        $table->addTimestamps();
        $table->addIndex('pid');
        $table->addIndex('url');
        $table->addIndex('auth_type');
        $table->create();

        // $rows = [
        //     [
        //         'id'          => 1,
        //         'pid'         => 0,
        //         'status'      => 1,
        //         'icon'        => 'el-icon-s-tools',
        //         'name'        => '设置',
        //         'url'         => '/auth',
        //         'create_time' => '2024-11-25 18:12:21',
        //     ],
        //     [
        //         'id'          => 2,
        //         'pid'         => 1,
        //         'status'      => 1,
        //         'icon'        => 'el-icon-s-tools',
        //         'name'        => '系统配置',
        //         'url'         => '/auth/config',
        //         'create_time' => '2024-11-25 18:12:21',
        //     ],
        //     [
        //         'id'          => 3,
        //         'pid'         => 1,
        //         'status'      => 1,
        //         'icon'        => 'el-icon-s-tools',
        //         'name'        => '菜单管理',
        //         'url'         => '/auth/menu',
        //         'create_time' => '2024-11-25 18:12:21',
        //     ],
        //     [
        //         'id'          => 4,
        //         'pid'         => 1,
        //         'status'      => 1,
        //         'icon'        => 'el-icon-s-tools',
        //         'name'        => '角色管理',
        //         'url'         => '/auth/roles',
        //         'create_time' => '2024-11-25 18:12:21',
        //     ],
        //     [
        //         'id'          => 5,
        //         'pid'         => 1,
        //         'status'      => 1,
        //         'icon'        => 'el-icon-s-tools',
        //         'name'        => '权限配置',
        //         'url'         => '/auth/admin',
        //         'create_time' => '2024-11-25 18:12:21',
        //     ],
        //     [
        //         'id'          => 6,
        //         'pid'         => 1,
        //         'status'      => 1,
        //         'icon'        => 'el-icon-s-tools',
        //         'name'        => '网站日志',
        //         'url'         => '/auth/logs',
        //         'create_time' => '2024-11-25 18:12:21',
        //     ],
        // ];

        // $this->table('menus')->insert($rows)->saveData();
        // $this->seedMerchantCsAiModelMenu();
        // $this->seedAdminSettingFileMenu();
    }

    public function down()
    {
        $this->table('menus')->drop();
    }

    /**
     * 写入商户端客服 AI 模型配置菜单。
     */
    private function seedMerchantCsAiModelMenu(): void
    {
        $prefix = $this->getPrefix();
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` WHERE `platform_type` = 2 AND `url` = '/cs/ai-model' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $parent = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` WHERE `platform_type` = 2 AND `url` = '/cs' LIMIT 1"
        );
        if (empty($parent['id'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->table('menus')->insert([[
            'pid' => (int)$parent['id'],
            'icon' => '',
            'name' => 'AI模型配置',
            'url' => '/cs/ai-model',
            'methods' => '',
            'sort' => -1,
            'access' => 0,
            'path' => '',
            'auth_type' => 0,
            'auth_iden' => '',
            'status' => 1,
            'is_hidden' => 0,
            'platform_type' => 2,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();
    }

    /**
     * 写入平台端附件管理菜单。
     */
    private function seedAdminSettingFileMenu(): void
    {
        $prefix = $this->getPrefix();
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` WHERE `platform_type` = 1 AND `url` = '/setting/file' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $parent = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 1 AND `auth_type` = 0 "
            . "AND (`url` = '/setting' OR `name` IN ('系统设置', '设置')) "
            . "AND `delete_time` IS NULL "
            . "ORDER BY `url` = '/setting' DESC, `name` = '系统设置' DESC, `id` ASC LIMIT 1"
        );
        if (empty($parent['id'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->table('menus')->insert([[
            'pid' => (int)$parent['id'],
            'icon' => 'el-icon-folder-opened',
            'name' => '附件管理',
            'url' => '/setting/file',
            'methods' => '',
            'sort' => 0,
            'access' => 0,
            'path' => '',
            'auth_type' => 0,
            'auth_iden' => '',
            'status' => 1,
            'is_hidden' => 0,
            'platform_type' => 1,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();
    }

    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
