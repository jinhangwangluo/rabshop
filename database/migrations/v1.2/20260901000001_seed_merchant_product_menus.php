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
 * 补充商户端商品模块菜单与接口权限节点。
 */
class SeedMerchantProductMenus extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $productMenuId = $this->ensureMenu(0, '商品', '/product', 'el-icon-goods', 900);
        if ($productMenuId <= 0) {
            return;
        }

        $children = [
            ['商品列表', '/product/list', 'el-icon-goods', 90],
            ['商品库', '/product/library', 'el-icon-box', 80],
            ['商品标签', '/product/tag', 'el-icon-price-tag', 70],
            ['商品分类', '/product/category', 'el-icon-menu', 60],
            ['商品服务', '/product/service', 'el-icon-service', 50],
            ['商品分组', '/product/group', 'el-icon-collection-tag', 40],
            ['商品属性', '/product/attribute', 'el-icon-document', 30],
            ['商品规格模板', '/product/spec', 'el-icon-s-grid', 20],
            ['运费模板', '/product/freight', 'el-icon-truck', 10],
        ];
        foreach ($children as [$name, $url, $icon, $sort]) {
            $this->ensureMenu($productMenuId, $name, $url, $icon, $sort);
        }

        $permissions = [
            ['商品列表接口', 'api/merchant/product', 'GET', 90],
            ['新增商品', 'api/merchant/product', 'POST', 89],
            ['编辑商品', 'api/merchant/product/<id>', 'PUT', 88],
            ['删除商品', 'api/merchant/product/<id>', 'DELETE', 87],
            ['商品上下架', 'api/merchant/product/status/<id>', 'GET', 86],
            ['商品库接口', 'api/merchant/product/library', 'GET', 80],
            ['商品分类接口', 'api/merchant/product/category', 'GET', 70],
            ['商品标签接口', 'api/merchant/product/tag', 'GET', 60],
            ['商品标签关系保存', 'api/merchant/product/tag/relation', 'PUT', 59],
            ['商品服务接口', 'api/merchant/product/service', 'GET', 50],
            ['商品分组接口', 'api/merchant/product/group', 'GET', 40],
            ['商品属性接口', 'api/merchant/product/attribute', 'GET', 30],
            ['商品规格接口', 'api/merchant/product/spec', 'GET', 20],
            ['运费模板接口', 'api/merchant/product/freight', 'GET', 10],
        ];
        foreach ($permissions as [$name, $url, $method, $sort]) {
            $this->ensurePermission($productMenuId, $name, $url, $method, $sort);
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        $this->execute(
            "DELETE FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 2 AND (`url` = '/product' OR `url` LIKE '/product/%' OR `url` LIKE 'api/merchant/product%')"
        );
    }

    private function ensureMenu(int $pid, string $name, string $url, string $icon, int $sort): int
    {
        $prefix = $this->getPrefix();
        $safeUrl = addslashes($url);
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 2 AND `auth_type` = 0 AND `url` = '{$safeUrl}' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return (int)$existing['id'];
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
            'platform_type' => 2,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();

        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 2 AND `auth_type` = 0 AND `url` = '{$safeUrl}' LIMIT 1"
        );
        return (int)($menu['id'] ?? 0);
    }

    private function ensurePermission(int $pid, string $name, string $url, string $method, int $sort): void
    {
        $prefix = $this->getPrefix();
        $safeUrl = addslashes($url);
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `platform_type` = 2 AND `auth_type` = 1 AND `url` = '{$safeUrl}' AND `methods` = '{$method}' LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $authIden = trim(str_replace(['api/merchant', '/', '<', '>'], ['', '-', '', ''], $url), '-');
        $this->table('menus')->insert([[
            'pid' => $pid,
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
            'platform_type' => 2,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();
    }

    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
