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
 * 管理员日志表
 */
class CreateAdminLogTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('admin_log', [
            'comment' => '管理员日志',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC'
        ]);
        $table->addColumn('admin_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '管理员id']);
        $table->addColumn('admin_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '管理员名称']);
        $table->addColumn('path', 'string', ['limit' => 255, 'null' => true, 'comment' => '访问路由']);
        $table->addColumn('page', 'string', ['limit' => 255, 'null' => true, 'comment' => '访问地址']);
        $table->addColumn('method', 'string', ['limit' => 32, 'default' => '', 'comment' => '请求方式']);
        $table->addColumn('ip', 'string', ['limit' => 50, 'default' => '', 'comment' => 'ip']);
        $table->addColumn('create_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间']);
        $table->addIndex('id');
        $table->addIndex('admin_id');
        $table->addIndex('create_time');
        $table->create();
    }
}
