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
 * 商品采集开关接口权限数据初始化。
 *
 * 上架页的采集开关改走独立接口 api/admin/product/collector_config 下发，
 * 不再经通用配置接口读取；本迁移为该接口补平台端权限行，挂载到
 * 「商品 → 商品列表」菜单（顶级按 名称=商品 + url=product + pid=0 锚定，
 * 避开平台端 284/289 废弃分组下的同名旧菜单）。按 url + methods 幂等。
 * 与根目录 missing_interface_permissions.sql 中本段数据一致。
 */
class SeedCollectorConfigPermission extends Migrator
{
    /**
     * 权限行：[名称, 接口地址, 请求方式]。
     */
    private const PERMISSIONS = [
        ['查询采集配置', 'api/admin/product/collector_config', 'GET'],
    ];

    /**
     * 解析「商品 → 商品列表」菜单并幂等写入接口权限行。
     */
    public function up(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $rootId = $this->findProductRootMenuId();
        if ($rootId <= 0) {
            return;
        }

        $menuId = $this->findChildMenuId($rootId);
        if ($menuId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach (self::PERMISSIONS as $sort => [$name, $url, $methods]) {
            if ($this->permissionExists($url, $methods)) {
                continue;
            }

            $rows[] = [
                'pid' => $menuId,
                'icon' => '',
                'name' => $name,
                'url' => $url,
                'methods' => $methods,
                'sort' => $sort,
                'access' => 0,
                'path' => '',
                'auth_type' => 1,
                'auth_iden' => 'product-collector-config',
                'status' => 1,
                'is_hidden' => 1,
                'platform_type' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }

        if ($rows !== []) {
            $this->table('menus')->insert($rows)->saveData();
        }
    }

    /**
     * 回滚：删除本迁移写入的接口权限行。
     */
    public function down(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        $tuples = [];
        foreach (self::PERMISSIONS as [, $url, $methods]) {
            $tuples[] = "('" . addslashes($url) . "', '" . addslashes($methods) . "')";
        }
        $this->execute(
            "DELETE FROM `{$prefix}menus` "
            . "WHERE `auth_type` = 1 AND `platform_type` = 1 "
            . 'AND (`url`, `methods`) IN (' . implode(', ', $tuples) . ')'
        );
    }

    /**
     * 按名称 + url + pid=0 解析平台端「商品」顶级菜单 id（找不到返回 0）。
     */
    private function findProductRootMenuId(): int
    {
        $prefix = $this->getPrefix();
        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '商品' AND `url` = 'product' AND `pid` = 0 "
            . "AND `platform_type` = 1 AND `auth_type` = 0 "
            . 'AND `delete_time` IS NULL ORDER BY `id` ASC LIMIT 1'
        );

        return (int)($menu['id'] ?? 0);
    }

    /**
     * 按名称 + url 解析顶级下的「商品列表」子菜单 id（找不到返回 0）。
     */
    private function findChildMenuId(int $rootId): int
    {
        $prefix = $this->getPrefix();
        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '商品列表' AND `url` = 'product/list' AND `pid` = {$rootId} "
            . "AND `platform_type` = 1 AND `auth_type` = 0 "
            . 'AND `delete_time` IS NULL ORDER BY `id` ASC LIMIT 1'
        );

        return (int)($menu['id'] ?? 0);
    }

    /**
     * 判断接口权限行是否已存在（url + methods 唯一界定一条权限）。
     */
    private function permissionExists(string $url, string $methods): bool
    {
        $prefix = $this->getPrefix();
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `auth_type` = 1 AND `platform_type` = 1 "
            . "AND `url` = '" . addslashes($url) . "' AND `methods` = '" . addslashes($methods) . "' LIMIT 1"
        );

        return !empty($existing['id']);
    }

    /**
     * 获取数据表前缀。
     */
    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
