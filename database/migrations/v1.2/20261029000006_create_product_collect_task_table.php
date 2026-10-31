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
 * 商品批量采集任务表与接口权限数据初始化。
 *
 * 建表：Excel 导入采集链接 → 队列逐条采集（复用 collectFromExternal）→
 * 临时任务表记录进度与库商品关联 → 补全分类/品牌后批量上架。
 * 权限行：7 个 batch_collect 接口挂「商品 → 商品列表」菜单（两步锚定，
 * 避开平台端 284 废弃分组下的同名旧菜单），按 url + methods 幂等。
 */
class CreateProductCollectTaskTable extends Migrator
{
    /**
     * 权限行：[名称, 接口地址, 请求方式]。
     */
    private const PERMISSIONS = [
        ['批量采集导入', 'api/admin/product/batch_collect/import', 'POST'],
        ['批量采集任务列表', 'api/admin/product/batch_collect/tasks', 'GET'],
        ['批量采集模板下载', 'api/admin/product/batch_collect/template', 'GET'],
        ['批量采集上架', 'api/admin/product/batch_collect/publish', 'POST'],
        ['批量采集补全', 'api/admin/product/batch_collect/complete', 'POST'],
        ['批量采集重试', 'api/admin/product/batch_collect/tasks/<id>/retry', 'POST'],
        ['批量采集删除', 'api/admin/product/batch_collect/tasks/<id>', 'DELETE'],
    ];

    /**
     * 建表并写入接口权限行。
     */
    public function up(): void
    {
        $this->createTaskTable();
        $this->seedPermissions();
    }

    /**
     * 回滚：删除权限行并删表。
     */
    public function down(): void
    {
        if ($this->hasTable('menus')) {
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

        if ($this->hasTable('product_collect_task')) {
            $this->table('product_collect_task')->drop()->save();
        }
    }

    /**
     * 创建批量采集任务表。
     */
    private function createTaskTable(): void
    {
        if ($this->hasTable('product_collect_task')) {
            return;
        }

        $table = $this->table('product_collect_task', [
            'comment' => '商品批量采集任务表',
            'collation' => 'utf8mb4_general_ci',
        ]);
        $table->addColumn('batch_no', 'string', ['limit' => 32, 'default' => '', 'comment' => '批次号（同一次导入）']);
        $table->addColumn('source_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '第三方商品链接']);
        $table->addColumn('platform', 'string', ['limit' => 20, 'default' => '', 'comment' => '来源平台：taobao/jd']);
        $table->addColumn('title', 'string', ['limit' => 200, 'default' => '', 'comment' => '采集到的商品标题']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '采集状态：0待采集，1采集中，2成功，3失败']);
        $table->addColumn('library_product_ids', 'text', ['null' => true, 'comment' => '关联库商品ID JSON数组']);
        $table->addColumn('library_count', 'integer', ['limit' => 6, 'default' => 0, 'comment' => '关联库商品条数']);
        $table->addColumn('is_complete', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '数据是否齐全可上架：0缺少，1齐全']);
        $table->addColumn('missing_fields', 'string', ['limit' => 255, 'default' => '', 'comment' => '缺失字段说明']);
        $table->addColumn('publish_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '上架状态：0未上架，1上架中，2已上架，3上架失败']);
        $table->addColumn('store_product_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '上架后的店铺商品ID']);
        $table->addColumn('publish_error', 'string', ['limit' => 500, 'default' => '', 'comment' => '上架失败原因']);
        $table->addColumn('error_msg', 'string', ['limit' => 500, 'default' => '', 'comment' => '采集失败原因']);
        $table->addColumn('admin_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '操作管理员ID']);
        $table->addColumn('started_time', 'datetime', ['null' => true, 'comment' => '采集开始时间']);
        $table->addColumn('finished_time', 'datetime', ['null' => true, 'comment' => '采集结束时间']);
        $table->addTimestamps();
        $table->addIndex('batch_no', ['name' => 'idx_batch_no']);
        $table->addIndex(['status', 'create_time'], ['name' => 'idx_status_create_time']);
        $table->addIndex(['admin_id', 'status'], ['name' => 'idx_admin_status']);
        $table->addIndex(['publish_status', 'create_time'], ['name' => 'idx_publish_status_create_time']);
        $table->create();
    }

    /**
     * 写入 batch_collect 接口权限行（挂「商品 → 商品列表」菜单，幂等）。
     */
    private function seedPermissions(): void
    {
        if (!$this->hasTable('menus')) {
            return;
        }

        $prefix = $this->getPrefix();
        $root = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '商品' AND `url` = 'product' AND `pid` = 0 "
            . "AND `platform_type` = 1 AND `auth_type` = 0 "
            . 'AND `delete_time` IS NULL ORDER BY `id` ASC LIMIT 1'
        );
        if (empty($root['id'])) {
            return;
        }

        $menu = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}menus` "
            . "WHERE `name` = '商品列表' AND `url` = 'product/list' AND `pid` = " . (int)$root['id'] . ' '
            . "AND `platform_type` = 1 AND `auth_type` = 0 "
            . 'AND `delete_time` IS NULL ORDER BY `id` ASC LIMIT 1'
        );
        if (empty($menu['id'])) {
            return;
        }

        $menuId = (int)$menu['id'];
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach (self::PERMISSIONS as $sort => [$name, $url, $methods]) {
            $existing = $this->fetchRow(
                "SELECT `id` FROM `{$prefix}menus` "
                . "WHERE `auth_type` = 1 AND `platform_type` = 1 "
                . "AND `url` = '" . addslashes($url) . "' AND `methods` = '" . addslashes($methods) . "' LIMIT 1"
            );
            if (!empty($existing['id'])) {
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
                'auth_iden' => 'product-batch-collect',
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
     * 获取数据表前缀。
     */
    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
