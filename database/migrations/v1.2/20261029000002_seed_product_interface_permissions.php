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
 * 商品模块菜单树与接口权限数据初始化（与根目录 product_menus_interface_permissions.sql 同源）。
 *
 * 执行顺序：先按需补全商品顶级菜单与子页面菜单（幂等，缺什么补什么，已有则跳过），
 * 再按「名称=商品 + url + pid=0」锚定顶级菜单、「名称 + pid=顶级id」锚定子菜单挂载权限行，
 * 避开平台端 284/289 废弃分组与商户端 329 重复分组；权限行按 url + methods 幂等去重。
 * 全新安装时本迁移先于 upgrade.sql 执行（会完整建出商品菜单树，权限行在其菜单尚未入库前
 * 会因顶级菜单刚建而正常挂载，不受 upgrade.sql 影响）。
 */
class SeedProductInterfacePermissions extends Migrator
{
    /**
     * 本次执行中由迁移新建的菜单 id（down 回滚时仅删除新建部分，不碰已有菜单）。
     */
    private static array $createdMenuIds = [];

    /**
     * 商品菜单树：[平台类型 1平台端/2商户端, 顶级菜单url, 顶级sort, 子菜单列表]。
     * 子菜单：[名称, url, sort, 是否隐藏 1是/0否]。
     */
    private const MENUS = [
        [1, 'product', 920, [
            ['添加商品库商品', 'product/library/create', 0, 1],
            ['商品库', 'product/library', 0, 0],
            ['商品列表', 'product/list', 0, 0],
            ['商品品牌', 'product/brand', 0, 0],
            ['商品分类', 'product/category', 0, 0],
            ['商品服务', 'product/service', 0, 0],
            ['商品标签', 'product/tag', 0, 0],
            ['规格管理', 'product/spec', 0, 0],
            ['商品分组', 'product/group', 0, 0],
            ['商品属性', 'product/attribute', 0, 0],
            ['运费模板', 'product/freight', 0, 0],
        ]],
        [2, '/product', 900, [
            ['商品列表', '/product/list', 90, 0],
            ['商品库', '/product/library', 80, 0],
            ['商品标签', '/product/tag', 70, 0],
            ['商品品牌', '/product/brand', 65, 0],
            ['商品分类', '/product/category', 60, 0],
            ['商品服务', '/product/service', 50, 0],
            ['商品分组', '/product/group', 40, 0],
            ['商品属性', '/product/attribute', 30, 0],
            ['商品规格模板', '/product/spec', 20, 0],
            ['运费模板', '/product/freight', 10, 0],
            ['添加商品库商品', '/product/library/create', 0, 1],
            ['上架商品', '/product/list/create', 0, 1],
        ]],
    ];

    /**
     * 权限分组：[平台类型, 上级菜单名称, 权限行列表]。
     * 权限行：[名称, 接口路径(不含 api/admin|merchant/product 前缀), 请求方式]，排序取数组下标。
     */
    private const GROUPS = [
        [1, '商品库', [
            ['商品库列表', '/library', 'GET'],
            ['商品库保存', '/library', 'POST'],
            ['商品库编辑页', '/library/<id>/edit', 'GET'],
            ['商品库详情', '/library/<id>', 'GET'],
            ['商品库修改', '/library/<id>', 'PUT'],
            ['商品库删除', '/library/<id>', 'DELETE'],
            ['商品库状态', '/library/status/<id>', 'GET'],
            ['商品库分享', '/library/share/<id>', 'POST'],
            ['商品库选项', '/library/options', 'GET'],
        ]],
        [1, '商品列表', [
            ['商品分页', '', 'GET'],
            ['商品保存', '', 'POST'],
            ['商品编辑页', '/<id>/edit', 'GET'],
            ['商品详情', '/<id>', 'GET'],
            ['商品修改', '/<id>', 'PUT'],
            ['商品删除', '/<id>', 'DELETE'],
            ['商品状态', '/status/<id>', 'GET'],
            ['商品采集', '/collect', 'POST'],
            ['商品组合', '/assemble', 'POST'],
            ['商品选项', '/options', 'GET'],
            ['商品SKU列表', '/<id>/sku_list', 'GET'],
            ['商品SKU保存', '/<id>/sku', 'POST'],
            ['商品SKU删除', '/<id>/sku/delete/<skuId>', 'POST'],
            ['商品SKU状态', '/<id>/sku/status/<skuId>', 'POST'],
            ['商品SKU排序', '/<id>/sku/sort/<skuId>', 'POST'],
            ['商品虚拟销量', '/<id>/virtual_stats', 'POST'],
            ['商品审核', '/<id>/audit', 'POST'],
        ]],
        [1, '商品分类', [
            ['商品分类树', '/category/tree', 'GET'],
            ['商品分类列表', '/category/list', 'GET'],
            ['商品分类状态', '/category/status/<id>', 'GET'],
            ['商品分类分页', '/category', 'GET'],
            ['商品分类保存', '/category', 'POST'],
            ['商品分类编辑页', '/category/<id>/edit', 'GET'],
            ['商品分类详情', '/category/<id>', 'GET'],
            ['商品分类修改', '/category/<id>', 'PUT'],
            ['商品分类删除', '/category/<id>', 'DELETE'],
        ]],
        [1, '商品品牌', [
            ['商品品牌选项', '/brand/options', 'GET'],
            ['商品品牌状态', '/brand/status/<id>', 'GET'],
            ['商品品牌分页', '/brand', 'GET'],
            ['商品品牌保存', '/brand', 'POST'],
            ['商品品牌编辑页', '/brand/<id>/edit', 'GET'],
            ['商品品牌详情', '/brand/<id>', 'GET'],
            ['商品品牌修改', '/brand/<id>', 'PUT'],
            ['商品品牌删除', '/brand/<id>', 'DELETE'],
        ]],
        [1, '规格管理', [
            ['商品规格选项', '/spec/options', 'GET'],
            ['商品规格状态', '/spec/status/<id>', 'GET'],
            ['商品规格分页', '/spec', 'GET'],
            ['商品规格保存', '/spec', 'POST'],
            ['商品规格编辑页', '/spec/<id>/edit', 'GET'],
            ['商品规格详情', '/spec/<id>', 'GET'],
            ['商品规格修改', '/spec/<id>', 'PUT'],
            ['商品规格删除', '/spec/<id>', 'DELETE'],
        ]],
        [1, '商品服务', [
            ['商品服务选项', '/service/options', 'GET'],
            ['商品服务状态', '/service/status/<id>', 'GET'],
            ['商品服务分页', '/service', 'GET'],
            ['商品服务保存', '/service', 'POST'],
            ['商品服务编辑页', '/service/<id>/edit', 'GET'],
            ['商品服务详情', '/service/<id>', 'GET'],
            ['商品服务修改', '/service/<id>', 'PUT'],
            ['商品服务删除', '/service/<id>', 'DELETE'],
        ]],
        [1, '商品分组', [
            ['商品分组选项', '/group/options', 'GET'],
            ['商品分组状态', '/group/status/<id>', 'GET'],
            ['商品分组分页', '/group', 'GET'],
            ['商品分组保存', '/group', 'POST'],
            ['商品分组编辑页', '/group/<id>/edit', 'GET'],
            ['商品分组详情', '/group/<id>', 'GET'],
            ['商品分组修改', '/group/<id>', 'PUT'],
            ['商品分组删除', '/group/<id>', 'DELETE'],
        ]],
        [1, '商品属性', [
            ['商品属性选项', '/attribute/options', 'GET'],
            ['商品属性模板', '/attribute/template/<categoryId>', 'GET'],
            ['商品属性状态', '/attribute/status/<id>', 'GET'],
            ['商品属性分页', '/attribute', 'GET'],
            ['商品属性保存', '/attribute', 'POST'],
            ['商品属性编辑页', '/attribute/<id>/edit', 'GET'],
            ['商品属性详情', '/attribute/<id>', 'GET'],
            ['商品属性修改', '/attribute/<id>', 'PUT'],
            ['商品属性删除', '/attribute/<id>', 'DELETE'],
        ]],
        [1, '运费模板', [
            ['运费模板选项', '/freight/options', 'GET'],
            ['运费模板状态', '/freight/status/<id>', 'GET'],
            ['运费模板默认设置', '/freight/default/<id>', 'POST'],
            ['运费模板发货时间选项', '/freight/delivery_time_options', 'GET'],
            ['运费模板地区树', '/freight/city_tree', 'GET'],
            ['运费模板规则', '/freight/rules/<id>', 'GET'],
            ['运费模板分页', '/freight', 'GET'],
            ['运费模板保存', '/freight', 'POST'],
            ['运费模板编辑页', '/freight/<id>/edit', 'GET'],
            ['运费模板详情', '/freight/<id>', 'GET'],
            ['运费模板修改', '/freight/<id>', 'PUT'],
            ['运费模板删除', '/freight/<id>', 'DELETE'],
        ]],
        [2, '商品分类', [
            ['商品分类平台树', '/category/platform_tree', 'GET'],
            ['商品分类平台列表', '/category/platform_list', 'GET'],
            ['商品分类树', '/category/tree', 'GET'],
            ['商品分类列表', '/category/list', 'GET'],
            ['商品分类状态', '/category/status/<id>', 'GET'],
            ['商品分类分页', '/category', 'GET'],
            ['商品分类保存', '/category', 'POST'],
            ['商品分类详情', '/category/<id>', 'GET'],
            ['商品分类修改', '/category/<id>', 'PUT'],
            ['商品分类删除', '/category/<id>', 'DELETE'],
        ]],
        [2, '商品标签', [
            ['商品标签分组树', '/tag_category/tree', 'GET'],
            ['商品标签分组快捷新增', '/tag_category/quick_add', 'POST'],
            ['商品标签分组列表', '/tag_category', 'GET'],
            ['商品标签分组保存', '/tag_category', 'POST'],
            ['商品标签分组详情', '/tag_category/<id>/show', 'GET'],
            ['商品标签分组读取', '/tag_category/<id>', 'GET'],
            ['商品标签分组修改', '/tag_category/<id>', 'PUT'],
            ['商品标签分组删除', '/tag_category/<id>', 'DELETE'],
            ['商品标签选项', '/tag/options', 'GET'],
            ['商品标签快捷新增', '/tag/quick_add', 'POST'],
            ['商品标签AI导入', '/tag/ai_import', 'POST'],
            ['商品标签关联', '/tag/relation', 'GET'],
            ['商品标签关联保存', '/tag/relation', 'PUT'],
            ['商品标签分页', '/tag', 'GET'],
            ['商品标签保存', '/tag', 'POST'],
            ['商品标签详情', '/tag/<id>/show', 'GET'],
            ['商品标签读取', '/tag/<id>', 'GET'],
            ['商品标签修改', '/tag/<id>', 'PUT'],
            ['商品标签删除', '/tag/<id>', 'DELETE'],
        ]],
        [2, '商品库', [
            ['商品库状态', '/library/status/<id>', 'GET'],
            ['商品库分享', '/library/share/<id>', 'POST'],
            ['商品库选项', '/library/options', 'GET'],
            ['商品库分页', '/library', 'GET'],
            ['商品库保存', '/library', 'POST'],
            ['商品库详情', '/library/<id>', 'GET'],
            ['商品库修改', '/library/<id>', 'PUT'],
            ['商品库删除', '/library/<id>', 'DELETE'],
        ]],
        [2, '商品品牌', [
            ['商品品牌选项', '/brand/options', 'GET'],
            ['商品品牌状态', '/brand/status/<id>', 'GET'],
            ['商品品牌分页', '/brand', 'GET'],
            ['商品品牌保存', '/brand', 'POST'],
            ['商品品牌详情', '/brand/<id>', 'GET'],
            ['商品品牌修改', '/brand/<id>', 'PUT'],
            ['商品品牌删除', '/brand/<id>', 'DELETE'],
        ]],
        [2, '商品规格模板', [
            ['商品规格选项', '/spec/options', 'GET'],
            ['商品规格状态', '/spec/status/<id>', 'GET'],
            ['商品规格分页', '/spec', 'GET'],
            ['商品规格保存', '/spec', 'POST'],
            ['商品规格详情', '/spec/<id>', 'GET'],
            ['商品规格修改', '/spec/<id>', 'PUT'],
            ['商品规格删除', '/spec/<id>', 'DELETE'],
        ]],
        [2, '商品服务', [
            ['商品服务选项', '/service/options', 'GET'],
            ['商品服务状态', '/service/status/<id>', 'GET'],
            ['商品服务分页', '/service', 'GET'],
            ['商品服务保存', '/service', 'POST'],
            ['商品服务详情', '/service/<id>', 'GET'],
            ['商品服务修改', '/service/<id>', 'PUT'],
            ['商品服务删除', '/service/<id>', 'DELETE'],
        ]],
        [2, '商品分组', [
            ['商品分组选项', '/group/options', 'GET'],
            ['商品分组状态', '/group/status/<id>', 'GET'],
            ['商品分组分页', '/group', 'GET'],
            ['商品分组保存', '/group', 'POST'],
            ['商品分组详情', '/group/<id>', 'GET'],
            ['商品分组修改', '/group/<id>', 'PUT'],
            ['商品分组删除', '/group/<id>', 'DELETE'],
        ]],
        [2, '商品属性', [
            ['商品属性选项', '/attribute/options', 'GET'],
            ['商品属性模板', '/attribute/template/<categoryId>', 'GET'],
            ['商品属性状态', '/attribute/status/<id>', 'GET'],
            ['商品属性分页', '/attribute', 'GET'],
            ['商品属性保存', '/attribute', 'POST'],
            ['商品属性详情', '/attribute/<id>', 'GET'],
            ['商品属性修改', '/attribute/<id>', 'PUT'],
            ['商品属性删除', '/attribute/<id>', 'DELETE'],
        ]],
        [2, '运费模板', [
            ['运费模板选项', '/freight/options', 'GET'],
            ['运费模板状态', '/freight/status/<id>', 'GET'],
            ['运费模板默认设置', '/freight/default/<id>', 'POST'],
            ['运费模板发货时间选项', '/freight/delivery_time_options', 'GET'],
            ['运费模板地区树', '/freight/city_tree', 'GET'],
            ['运费模板规则', '/freight/rules/<id>', 'GET'],
            ['运费模板分页', '/freight', 'GET'],
            ['运费模板保存', '/freight', 'POST'],
            ['运费模板详情', '/freight/<id>', 'GET'],
            ['运费模板修改', '/freight/<id>', 'PUT'],
            ['运费模板删除', '/freight/<id>', 'DELETE'],
        ]],
        [2, '商品列表', [
            ['商品状态', '/status/<id>', 'GET'],
            ['商品组合', '/assemble', 'POST'],
            ['商品选项', '/options', 'GET'],
            ['商品SKU列表', '/<id>/sku_list', 'GET'],
            ['商品SKU保存', '/<id>/sku', 'POST'],
            ['商品SKU删除', '/<id>/sku/delete/<skuId>', 'POST'],
            ['商品SKU状态', '/<id>/sku/status/<skuId>', 'POST'],
            ['商品SKU排序', '/<id>/sku/sort/<skuId>', 'POST'],
            ['商品虚拟销量', '/<id>/virtual_stats', 'POST'],
            ['商品分页', '', 'GET'],
            ['商品保存', '', 'POST'],
            ['商品详情', '/<id>', 'GET'],
            ['商品修改', '/<id>', 'PUT'],
            ['商品删除', '/<id>', 'DELETE'],
        ]],
    ];

    /**
     * 补全商品菜单树并挂载接口权限数据。
     */
    public function up(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::MENUS as [$platformType, $rootUrl, $rootSort, $children]) {
            $rootId = $this->ensureRootMenu($platformType, $rootUrl, $rootSort, $now);
            if ($rootId <= 0) {
                continue;
            }

            foreach ($children as [$name, $url, $sort, $isHidden]) {
                $this->ensureChildMenu($platformType, $rootId, $name, $url, $sort, $isHidden, $now);
            }

            $base = $platformType === 1 ? 'api/admin/product' : 'api/merchant/product';
            foreach ($this->groupsOf($platformType) as [$menuName, $permissions]) {
                $menuId = $this->findChildMenuId($platformType, $menuName, $rootId);
                if ($menuId <= 0) {
                    continue;
                }

                $rows = [];
                foreach ($permissions as $sort => [$name, $path, $methods]) {
                    $url = $base . $path;
                    if ($this->permissionExists($platformType, $url, $methods)) {
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
                        'auth_iden' => $this->buildAuthIden($path),
                        'status' => 1,
                        'is_hidden' => 1,
                        'platform_type' => $platformType,
                        'create_time' => $now,
                        'update_time' => $now,
                    ];
                }

                if ($rows !== []) {
                    $this->table('menus')->insert($rows)->saveData();
                }
            }
        }
    }

    /**
     * 回滚：删除本次挂载的商品接口权限行与迁移新建的菜单节点（不碰既有菜单）。
     */
    public function down(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        foreach (self::MENUS as [$platformType, $rootUrl, , ]) {
            $base = $platformType === 1 ? 'api/admin/product' : 'api/merchant/product';
            $tuples = [];
            foreach ($this->groupsOf($platformType) as [, $permissions]) {
                foreach ($permissions as [, $path, $methods]) {
                    $tuples[] = "('" . addslashes($base . $path) . "', '" . addslashes($methods) . "')";
                }
            }
            $this->execute(
                "DELETE FROM `{$prefix}menus` "
                . "WHERE `auth_type` = 1 AND `platform_type` = {$platformType} "
                . 'AND (`url`, `methods`) IN (' . implode(', ', $tuples) . ')'
            );
        }

        if (self::$createdMenuIds !== []) {
            $ids = implode(',', array_map('intval', self::$createdMenuIds));
            $this->execute("DELETE FROM `{$prefix}menus` WHERE `id` IN ({$ids})");
        }
    }

    /**
     * 获取指定平台类型的权限分组数据。
     */
    private function groupsOf(int $platformType): array
    {
        $groups = [];
        foreach (self::GROUPS as [$platform, $menuName, $permissions]) {
            if ($platform === $platformType) {
                $groups[] = [$menuName, $permissions];
            }
        }

        return $groups;
    }

    /**
     * 按需补全商品顶级菜单（名称=商品、url 精确匹配、pid=0），已存在则返回其 id。
     */
    private function ensureRootMenu(int $platformType, string $rootUrl, int $sort, string $now): int
    {
        $prefix = $this->getPrefix();
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '商品' AND `url` = '" . addslashes($rootUrl) . "' "
            . "AND `platform_type` = {$platformType} AND `pid` = 0 AND `auth_type` = 0 "
            . 'AND `delete_time` IS NULL ORDER BY `id` ASC LIMIT 1'
        );
        if (!empty($existing['id'])) {
            return (int)$existing['id'];
        }

        $this->table('menus')->insert([[
            'pid' => 0,
            'icon' => 'el-icon-goods',
            'name' => '商品',
            'url' => $rootUrl,
            'methods' => '',
            'sort' => $sort,
            'access' => 0,
            'path' => '',
            'auth_type' => 0,
            'auth_iden' => '',
            'status' => 1,
            'is_hidden' => 0,
            'platform_type' => $platformType,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();

        return $this->findRootMenuId($platformType, $rootUrl);
    }

    /**
     * 按需补全子页面菜单（按「名称 + pid=顶级id」判断，已存在则跳过）。
     */
    private function ensureChildMenu(int $platformType, int $rootId, string $name, string $url, int $sort, int $isHidden, string $now): void
    {
        $prefix = $this->getPrefix();
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '" . addslashes($name) . "' AND `pid` = {$rootId} "
            . "AND `platform_type` = {$platformType} AND `auth_type` = 0 AND `delete_time` IS NULL LIMIT 1"
        );
        if (!empty($existing['id'])) {
            return;
        }

        $this->table('menus')->insert([[
            'pid' => $rootId,
            'icon' => '',
            'name' => $name,
            'url' => $url,
            'methods' => '',
            'sort' => $sort,
            'access' => 0,
            'path' => '',
            'auth_type' => 0,
            'auth_iden' => '',
            'status' => 1,
            'is_hidden' => $isHidden,
            'platform_type' => $platformType,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();

        $created = $this->findChildMenuId($platformType, $name, $rootId);
        if ($created > 0) {
            self::$createdMenuIds[] = $created;
        }
    }

    /**
     * 按名称 + url + pid=0 查找商品顶级菜单 id。
     */
    private function findRootMenuId(int $platformType, string $rootUrl): int
    {
        $prefix = $this->getPrefix();
        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '商品' AND `url` = '" . addslashes($rootUrl) . "' "
            . "AND `platform_type` = {$platformType} AND `pid` = 0 AND `auth_type` = 0 "
            . 'AND `delete_time` IS NULL ORDER BY `id` ASC LIMIT 1'
        );

        return (int)($menu['id'] ?? 0);
    }

    /**
     * 按「名称 + pid=顶级id」查找子菜单 id，同名歧义分组已被顶级锚定排除。
     */
    private function findChildMenuId(int $platformType, string $menuName, int $rootId): int
    {
        $prefix = $this->getPrefix();
        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '" . addslashes($menuName) . "' AND `pid` = {$rootId} "
            . "AND `platform_type` = {$platformType} AND `auth_type` = 0 "
            . 'AND `delete_time` IS NULL ORDER BY `id` ASC LIMIT 1'
        );

        return (int)($menu['id'] ?? 0);
    }

    /**
     * 判断接口权限行是否已存在（url + methods 唯一界定一条权限）。
     */
    private function permissionExists(int $platformType, string $url, string $methods): bool
    {
        $prefix = $this->getPrefix();
        $existing = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `auth_type` = 1 AND `platform_type` = {$platformType} "
            . "AND `url` = '" . addslashes($url) . "' AND `methods` = '" . addslashes($methods) . "' LIMIT 1"
        );

        return !empty($existing['id']);
    }

    /**
     * 由接口路径生成权限标识：/library/status/<id> -> product-library-status-id。
     */
    private function buildAuthIden(string $path): string
    {
        return trim('product' . str_replace(['/', '<', '>'], ['-', '', ''], $path), '-');
    }

    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
