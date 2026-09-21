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
 * 商品参数模板分类绑定关系表。
 */
class CreateProductAttributeCategoryRelationTable extends Migrator
{
    public function change()
    {
        $table = $this->table('product_attribute_category_relation', [
            'comment' => '商品参数模板分类绑定表',
            'collation' => 'utf8mb4_general_ci',
        ]);
        $table->addColumn('template_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '参数模板ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '归属商户ID，0=平台']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品分类ID']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['merchant_id', 'category_id'], ['name' => 'uniq_merchant_category', 'unique' => true]);
        $table->addIndex('template_id', ['name' => 'idx_template_id']);
        $table->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $table->addIndex('category_id', ['name' => 'idx_category_id']);
        $table->create();
    }
}
