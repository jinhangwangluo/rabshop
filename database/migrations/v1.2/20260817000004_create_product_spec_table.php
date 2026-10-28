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
 * 商品规格表（product_spec）
 *
 * 规格模板表，spec_template 保存完整的规格维度和规格值 JSON。
 * merchant_id=0 为平台公共规格；商户也可自建自己的规格。
 */
class CreateProductSpecTable extends Migrator
{
    /**
     * 创建商品规格表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('product_spec', ['comment' => '商品规格表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '归属商户ID，0=平台公共']);
        $table->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '规格模板名称']);
        $table->addColumn('spec_template', 'json', ['null' => true, 'comment' => '规格模板内容(JSON数组，包含规格维度和值)']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $table->addIndex('status', ['name' => 'idx_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->create();
    }
}
