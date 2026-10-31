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
 * 补充平台端插件市场菜单。
 */
class SeedPluginMarketMenu extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $this->ensureMenu(0, '插件市场', '/plugin', 'el-icon-box', 530);
    }

    public function down(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        $this->execute(
            "DELETE FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 1 AND `auth_type` = 0 AND `url` = '/plugin'"
        );
    }

    private function ensureMenu(int $pid, string $name, string $url, string $icon, int $sort): void
    {
        $prefix = $this->getPrefix();
        $safeUrl = addslashes($url);
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 1 AND `auth_type` = 0 AND `url` = '{$safeUrl}' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->table('menus')->insert([[
            'pid' => $pid,
            'icon' => $icon,
            'name' => $name,
            'url' => $url,
            'methods' => '',
            'sort' => $sort,
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
