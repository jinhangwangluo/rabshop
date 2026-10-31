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
 * 商品分类关联表（product_category_relation）
 *
 * 店铺商品与商户自定义分类的多分类关联：一个店铺商品可挂多个商户分类（category type=10）。
 * 该关系仅用于商户后台内部区分和筛选；移动端展示统一使用 store_product.category_id 平台分类。
 * store_product_id + category_id 唯一，同一商品不能重复挂同一分类。
 */
class CreateProductCategoryRelationTable extends Migrator
{
    /**
     * 创建商品分类关联表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('product_category_relation', ['comment' => '商品分类关联表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('store_product_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '店铺商品ID']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户自定义分类ID（category type=10）']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['store_product_id', 'category_id'], ['name' => 'uniq_product_category', 'unique' => true]);
        $table->addIndex('store_product_id', ['name' => 'idx_store_product_id']);
        $table->addIndex('category_id', ['name' => 'idx_category_id']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->create();
    }
}
