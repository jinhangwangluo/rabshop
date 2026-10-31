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
 * DIY页面表
 */
class CreateDiyPageTable extends Migrator
{
    public function change()
    {
        $table = $this->table('diy_page', [
            'comment' => 'DIY页面表',
            'collation' => 'utf8mb4_general_ci'
        ]);
        $table->addColumn('page_name', 'string', [
            'limit' => 50,
            'comment' => '页面名称'
        ]);
        $table->addColumn('page_type', 'string', [
            'limit' => 20,
            'default' => 'home',
            'comment' => '页面类型：home-首页'
        ]);
        $table->addColumn('page_title', 'string', [
            'limit' => 100,
            'default' => '',
            'comment' => '页面标题'
        ]);
        $table->addColumn('global_config', 'json', [
            'null' => true,
            'comment' => '全局配置(JSON)：背景、颜色等'
        ]);
        $table->addColumn('is_default', 'boolean', [
            'limit' => 1,
            'default' => 0,
            'comment' => '是否默认启用：0-否 1-是'
        ]);
        $table->addColumn('status', 'boolean', [
            'limit' => 1,
            'default' => 1,
            'comment' => '状态：0-禁用 1-启用'
        ]);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('page_type');
        $table->addIndex('is_default');
        $table->addIndex('status');
        $table->create();
    }
}
