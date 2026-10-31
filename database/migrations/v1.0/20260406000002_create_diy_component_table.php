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
 * DIY组件表
 */
class CreateDiyComponentTable extends Migrator
{
    public function change()
    {
        $table = $this->table('diy_component', ['comment' => 'DIY组件表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('diy_page_id', 'integer', ['limit' => 10, 'comment' => '所属页面ID']);
        $table->addColumn('component_type', 'string', ['limit' => 30, 'comment' => '组件类型']);
        $table->addColumn('component_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '组件名称']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序（越小越靠前）']);
        $table->addColumn('config', 'json', ['null' => true, 'comment' => '组件配置(JSON)']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用 1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('diy_page_id');
        $table->addIndex('component_type');
        $table->addIndex('sort');
        $table->create();
    }
}
