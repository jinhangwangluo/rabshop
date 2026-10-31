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
 * 插件/快递公司/订单发货接口权限补齐（与根目录 missing_interface_permissions.sql 同源）。
 *
 * 覆盖 apiurl.json 路由筛查中既无权限行、也不被 20261029000002 覆盖的 28 条平台端接口：
 * 插件模块 16（安全公钥/开发者安装生命周期/加密网关/打包服务）、快递公司 9、订单发货 3。
 * 挂载菜单按「名称 + url + platform_type=1 + auth_type=0」解析；菜单缺失时跳过对应分组，
 * 不产生孤儿权限行（插件菜单由 20260905000001_seed_plugin_market_menu 保证存在）。
 * 权限行按 url + methods 幂等，可重复执行。
 */
class SeedPluginExpressOrderPermissions extends Migrator
{
    /**
     * 权限分组：[上级菜单名称, 上级菜单url（同名歧义时用于精确定位）, [名称, 接口地址, 请求方式]]。
     */
    private const GROUPS = [
        ['插件', '/plugin', [
            ['插件安全公钥', 'api/admin/plugins/security/public-key', 'GET'],
            ['开发者插件列表', 'api/admin/plugins/developer', 'GET'],
            ['开发者插件安装', 'api/admin/plugins/developer/<code>/install', 'POST'],
            ['开发者插件卸载', 'api/admin/plugins/developer/<code>/uninstall', 'POST'],
            ['开发者插件重装', 'api/admin/plugins/developer/<code>/reinstall', 'POST'],
            ['开发者插件下载', 'api/admin/plugins/developer/<code>/download', 'GET'],
            ['插件加密网关', 'api/admin/plugins/gateway', 'POST'],
            ['打包目标列表', 'api/admin/plugins/build-service/targets', 'GET'],
            ['打包资源上传', 'api/admin/plugins/build-service/uploads', 'POST'],
            ['打包小程序凭据', 'api/admin/plugins/build-service/mp-credentials', 'POST'],
            ['打包任务列表', 'api/admin/plugins/build-service/builds', 'GET'],
            ['打包任务创建', 'api/admin/plugins/build-service/builds', 'POST'],
            ['打包任务日志', 'api/admin/plugins/build-service/builds/<id>/log', 'GET'],
            ['打包产物下载', 'api/admin/plugins/build-service/builds/<id>/artifact', 'GET'],
            ['打包任务取消', 'api/admin/plugins/build-service/builds/<id>/cancel', 'POST'],
            ['打包任务详情', 'api/admin/plugins/build-service/builds/<id>', 'GET'],
        ]],
        ['快递公司', 'setting/express', [
            ['查询快递公司', 'api/admin/setting/express_company', 'GET'],
            ['新增快递公司', 'api/admin/setting/express_company', 'POST'],
            ['详情快递公司', 'api/admin/setting/express_company/<id>', 'GET'],
            ['修改快递公司', 'api/admin/setting/express_company/<id>', 'PUT'],
            ['删除快递公司', 'api/admin/setting/express_company/<id>', 'DELETE'],
            ['快递公司状态', 'api/admin/setting/express_company/<id>/show', 'GET'],
            ['快递公司选项', 'api/admin/setting/express_company/options', 'GET'],
            ['查询物流配置', 'api/admin/setting/express_company/query_config', 'GET'],
            ['保存物流配置', 'api/admin/setting/express_company/query_config', 'POST'],
        ]],
        ['订单列表', '/order/list', [
            ['查询订单物流', 'api/admin/order/<id>/express', 'GET'],
            ['订单发货', 'api/admin/order/<id>/delivery', 'POST'],
            ['订单退货收货', 'api/admin/order/<id>/receive_return', 'POST'],
        ]],
    ];

    /**
     * 按分组解析挂载菜单并幂等写入接口权限行。
     */
    public function up(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::GROUPS as [$menuName, $menuUrl, $permissions]) {
            $menuId = $this->findMenuId($menuName, $menuUrl);
            if ($menuId <= 0) {
                continue;
            }

            $rows = [];
            foreach ($permissions as $sort => [$name, $url, $methods]) {
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
                    'auth_iden' => $this->buildAuthIden($url),
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
    }

    /**
     * 回滚：删除本迁移写入的接口权限行（url + methods 界定，不影响同 url 其他数据）。
     */
    public function down(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        $tuples = [];
        foreach (self::GROUPS as [, , $permissions]) {
            foreach ($permissions as [, $url, $methods]) {
                $tuples[] = "('" . addslashes($url) . "', '" . addslashes($methods) . "')";
            }
        }
        $this->execute(
            "DELETE FROM `{$prefix}menus` "
            . "WHERE `auth_type` = 1 AND `platform_type` = 1 "
            . 'AND (`url`, `methods`) IN (' . implode(', ', $tuples) . ')'
        );
    }

    /**
     * 按名称 + url 解析平台端挂载菜单 id（找不到返回 0，跳过该分组）。
     */
    private function findMenuId(string $menuName, string $menuUrl): int
    {
        $prefix = $this->getPrefix();
        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '" . addslashes($menuName) . "' AND `url` = '" . addslashes($menuUrl) . "' "
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
     * 由接口地址生成权限标识：api/admin/order/<id>/express -> order-id-express。
     */
    private function buildAuthIden(string $url): string
    {
        $path = str_replace('api/admin/', '', $url);

        return trim(str_replace(['/', '<', '>'], ['-', '', ''], $path), '-');
    }

    /**
     * 获取数据表前缀。
     */
    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
