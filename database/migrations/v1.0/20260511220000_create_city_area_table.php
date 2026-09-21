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
 * 创建并导入省市区地址表。
 */
class CreateCityAreaTable extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('city_area')) {
            $table = $this->table('city_area', [
                'id' => false,
                'primary_key' => ['id'],
                'comment' => '省市区地址表',
                'collation' => 'utf8mb4_general_ci',
            ]);
            $table->addColumn('id', 'integer', ['limit' => 11, 'signed' => false, 'null' => false, 'comment' => '行政区划编码']);
            $table->addColumn('parent_id', 'integer', ['limit' => 11, 'signed' => false, 'default' => 0, 'comment' => '父级行政区划编码']);
            $table->addColumn('path', 'string', ['limit' => 255, 'default' => '', 'comment' => '层级路径']);
            $table->addColumn('type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '类型：1省 2市 3区县']);
            $table->addColumn('level', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '层级：1省 2市 3区县']);
            $table->addColumn('name', 'string', ['limit' => 64, 'default' => '', 'comment' => '地区名称']);
            $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
            $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0禁用 1启用']);
            $table->addTimestamps();
            $table->addIndex('parent_id');
            $table->addIndex('level');
            $table->addIndex('status');
            $table->create();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('city_area')) {
            $this->table('city_area')->drop()->save();
        }
    }
}
