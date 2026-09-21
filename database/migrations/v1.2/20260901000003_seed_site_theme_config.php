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

use app\common\enum\ConfigKeyEnum;
use think\migration\Migrator;

/**
 * 写入移动端主题换色配置和后台菜单。
 */
class SeedSiteThemeConfig extends Migrator
{
    public function up(): void
    {
        $this->seedConfig();
        $this->seedAdminMenus();
    }

    public function down(): void
    {
        $prefix = $this->getPrefix();

        if ($this->hasTable('config')) {
            $key = addslashes(ConfigKeyEnum::SITE_THEME_CONFIG);
            $this->execute("DELETE FROM `{$prefix}config` WHERE `key_name` = '{$key}'");
        }

        if ($this->hasTable('menus')) {
            $this->execute(
                "DELETE FROM `{$prefix}menus` "
                . "WHERE `platform_type` = 1 "
                . "AND `url` IN ('/diy/theme_config', 'api/admin/diy/theme_config')"
            );
        }
    }

    /**
     * 写入默认主题配置。
     */
    private function seedConfig(): void
    {
        if (!$this->hasTable('config')) {
            return;
        }

        $prefix = $this->getPrefix();
        $now = date('Y-m-d H:i:s');
        $key = addslashes(ConfigKeyEnum::SITE_THEME_CONFIG);
        $value = addslashes(json_encode([
            'theme_key' => 'orange',
            'primary_color' => '#ea580c',
            'secondary_color' => '#fb923c',
            'gradient_color' => 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->execute(
            "INSERT INTO `{$prefix}config` "
            . "(`key_name`, `cate_id`, `value`, `title`, `into`, `sort`, `status`, `create_time`, `update_time`) "
            . "SELECT '{$key}', 0, '{$value}', '移动端主题换色配置', "
            . "'后台主题配置页面维护的移动端主色、辅助色和渐变色', 410, 1, '{$now}', '{$now}' "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config` WHERE `key_name` = '{$key}' LIMIT 1)"
        );
    }

    /**
     * 写入平台端主题换色菜单及接口权限节点。
     */
    private function seedAdminMenus(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        $adminUrl = addslashes('/diy/theme_config');
        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 1 AND `auth_type` = 0 AND `url` = '{$adminUrl}' LIMIT 1"
        );

        if (!empty($menu['id'])) {
            $menuId = (int)$menu['id'];
        } else {
            $parent = $this->fetchRow(
                "SELECT `id` FROM `{$prefix}menus` "
                . "WHERE `platform_type` = 1 AND `auth_type` = 0 "
                . "AND (`url` = '/diy' OR `name` IN ('DIY装修管理', '装修')) "
                . "AND `delete_time` IS NULL "
                . "ORDER BY `url` = '/diy' DESC, `name` = '装修' DESC, `id` ASC LIMIT 1"
            );
            if (empty($parent['id'])) {
                return;
            }

            $now = date('Y-m-d H:i:s');
            $this->table('menus')->insert([[
                'pid' => (int)$parent['id'],
                'icon' => 'el-icon-brush',
                'name' => '主题换色',
                'url' => '/diy/theme_config',
                'methods' => '',
                'sort' => 18,
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

            $menu = $this->fetchRow(
                "SELECT `id` FROM `{$prefix}menus` "
                . "WHERE `platform_type` = 1 AND `auth_type` = 0 AND `url` = '{$adminUrl}' LIMIT 1"
            );
            $menuId = (int)($menu['id'] ?? 0);
        }

        if ($menuId <= 0) {
            return;
        }

        $this->ensureAdminPermissionMenu($menuId, '获取主题配置', 'api/admin/diy/theme_config', 'GET', 2);
        $this->ensureAdminPermissionMenu($menuId, '保存主题配置', 'api/admin/diy/theme_config', 'POST', 1);
    }

    private function ensureAdminPermissionMenu(
        int $parentId,
        string $name,
        string $url,
        string $method,
        int $sort
    ): void {
        $prefix = $this->getPrefix();
        $safeUrl = addslashes($url);
        $safeMethod = addslashes($method);
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 1 AND `auth_type` = 1 "
            . "AND `url` = '{$safeUrl}' AND `methods` = '{$safeMethod}' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $authIden = trim(str_replace(['api/admin', '/', '<', '>'], ['', '-', '', ''], $url), '-');

        $this->table('menus')->insert([[
            'pid' => $parentId,
            'icon' => '',
            'name' => $name,
            'url' => $url,
            'methods' => $method,
            'sort' => $sort,
            'access' => 0,
            'path' => '',
            'auth_type' => 1,
            'auth_iden' => $authIden,
            'status' => 1,
            'is_hidden' => 1,
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
