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
 * 创建关于我们内容表。
 */
class CreateAboutUsTable extends Migrator
{
    public function up(): void
    {
        if ($this->hasTable('about_us')) {
            $this->seedNavigation();
            return;
        }

        $table = $this->table('about_us', [
            'comment' => '关于我们内容表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('content', 'text', [
            'null' => true,
            'comment' => '关于我们富文本内容',
        ]);
        $table->addTimestamps();
        $table->create();
        $this->seedNavigation();
    }

    public function down(): void
    {
        $this->removeNavigation();
        if ($this->hasTable('about_us')) {
            $this->table('about_us')->drop();
        }
    }

    private function seedNavigation(): void
    {
        $this->ensureAdminMenus();
        $this->ensureDiyPageLink();
        $this->appendDefaultServiceMenu();
    }

    /**
     * 补充平台端关于我们菜单及其接口权限节点。
     */
    private function ensureAdminMenus(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        $adminUrl = addslashes('/setting/about');
        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 1 AND `url` = '{$adminUrl}' LIMIT 1"
        );

        if (!empty($menu['id'])) {
            $menuId = (int)$menu['id'];
        } else {
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
                'icon' => 'el-icon-document',
                'name' => '关于我们',
                'url' => '/setting/about',
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

            $menu = $this->fetchRow(
                "SELECT `id` FROM `{$prefix}menus` "
                . "WHERE `platform_type` = 1 AND `url` = '{$adminUrl}' LIMIT 1"
            );
            $menuId = (int)($menu['id'] ?? 0);
        }

        if ($menuId <= 0) {
            return;
        }

        $this->ensureAdminPermissionMenu(
            $menuId,
            '获取关于我们配置',
            'api/admin/setting/about/schema',
            'GET',
            2
        );
        $this->ensureAdminPermissionMenu(
            $menuId,
            '保存关于我们内容',
            'api/admin/setting/about/save_content',
            'POST',
            1
        );
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
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 1 AND `auth_type` = 1 AND `url` = '{$safeUrl}' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $authIden = trim(str_replace(
            ['api/admin', '/', '<', '>'],
            ['', '-', '', ''],
            $url
        ), '-');

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

    /**
     * 补充 DIY 用户中心链接库入口。
     */
    private function ensureDiyPageLink(): void
    {
        if (!$this->hasTable('diy_page_link') || !$this->hasTable('category')) {
            return;
        }

        $prefix = $this->getPrefix();
        $aboutUrl = addslashes('/pages/users/about/index');
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}diy_page_link` "
            . "WHERE `link_url` = '{$aboutUrl}' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $categoryName = addslashes('用户中心');
        $category = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}category` "
            . "WHERE `type` = 2 AND `owner_id` = 0 AND `pid` = 0 "
            . "AND `name` = '{$categoryName}' LIMIT 1"
        );
        if (empty($category['id'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->table('diy_page_link')->insert([[
            'cate_id' => (int)$category['id'],
            'link_name' => '关于我们',
            'link_url' => '/pages/users/about/index',
            'link_type' => 'page',
            'platform_type' => 'common',
            'mini_appid' => '',
            'mini_path' => '',
            'icon' => '',
            'param_desc' => '关于我们页面，无需登录即可访问。',
            'example' => '/pages/users/about/index',
            'remark' => '来源：关于我们功能迁移',
            'sort' => 500,
            'status' => 1,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();
    }

    /**
     * 向已有默认个人中心服务菜单追加关于我们。
     */
    private function appendDefaultServiceMenu(): void
    {
        if (!$this->hasTable('diy_page') || !$this->hasTable('diy_component')) {
            return;
        }

        $prefix = $this->getPrefix();
        $pages = $this->fetchAll(
            "SELECT `id` FROM `{$prefix}diy_page` "
            . "WHERE `page_type` = 'user_center' AND `is_default` = 1 "
            . "AND `delete_time` IS NULL"
        );

        foreach ($pages as $page) {
            $pageId = (int)($page['id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            $component = $this->fetchRow(
                "SELECT `id`, `config` FROM `{$prefix}diy_component` "
                . "WHERE `diy_page_id` = {$pageId} "
                . "AND `component_type` = 'user_service_menu' "
                . "AND `delete_time` IS NULL "
                . "ORDER BY `sort` ASC, `id` ASC LIMIT 1"
            );
            if (empty($component['id'])) {
                continue;
            }

            $config = json_decode((string)($component['config'] ?? ''), true);
            if (!is_array($config)) {
                $config = [];
            }
            $items = is_array($config['items'] ?? null) ? $config['items'] : [];

            $exists = false;
            foreach ($items as $item) {
                if (is_array($item) && (string)($item['link_url'] ?? $item['url'] ?? '') === '/pages/users/about/index') {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                continue;
            }

            $items[] = [
                'name' => '关于我们',
                'image' => '',
                'link_type' => 'page',
                'link_url' => '/pages/users/about/index',
                'link_id' => 0,
                'link_name' => '关于我们',
                'platform_type' => 'common',
            ];
            $config['items'] = array_values($items);

            $json = addslashes((string)json_encode(
                $config,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
            $now = addslashes(date('Y-m-d H:i:s'));
            $componentId = (int)$component['id'];
            $this->execute(
                "UPDATE `{$prefix}diy_component` SET `config` = '{$json}', `update_time` = '{$now}' "
                . "WHERE `id` = {$componentId}"
            );
        }
    }

    private function removeNavigation(): void
    {
        $prefix = $this->getPrefix();

        if ($this->hasTable('menus')) {
            $this->execute(
                "DELETE FROM `{$prefix}menus` "
                . "WHERE `platform_type` = 1 "
                . "AND `url` IN ('/setting/about', 'api/admin/setting/about/schema', 'api/admin/setting/about/save_content')"
            );
        }

        if ($this->hasTable('diy_page_link')) {
            $this->execute(
                "DELETE FROM `{$prefix}diy_page_link` WHERE `link_url` = '/pages/users/about/index'"
            );
        }

        if ($this->hasTable('diy_page') && $this->hasTable('diy_component')) {
            $this->removeDefaultServiceMenu();
        }
    }

    private function removeDefaultServiceMenu(): void
    {
        $prefix = $this->getPrefix();
        $pages = $this->fetchAll(
            "SELECT `id` FROM `{$prefix}diy_page` "
            . "WHERE `page_type` = 'user_center' AND `is_default` = 1 "
            . "AND `delete_time` IS NULL"
        );

        foreach ($pages as $page) {
            $pageId = (int)($page['id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            $components = $this->fetchAll(
                "SELECT `id`, `config` FROM `{$prefix}diy_component` "
                . "WHERE `diy_page_id` = {$pageId} "
                . "AND `component_type` = 'user_service_menu' "
                . "AND `delete_time` IS NULL"
            );
            foreach ($components as $component) {
                $config = json_decode((string)($component['config'] ?? ''), true);
                if (!is_array($config) || !is_array($config['items'] ?? null)) {
                    continue;
                }

                $config['items'] = array_values(array_filter($config['items'], static function ($item): bool {
                    return !is_array($item)
                        || (string)($item['link_url'] ?? $item['url'] ?? '') !== '/pages/users/about/index';
                }));
                $json = addslashes((string)json_encode(
                    $config,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));
                $componentId = (int)($component['id'] ?? 0);
                $this->execute(
                    "UPDATE `{$prefix}diy_component` SET `config` = '{$json}' WHERE `id` = {$componentId}"
                );
            }
        }
    }

    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
