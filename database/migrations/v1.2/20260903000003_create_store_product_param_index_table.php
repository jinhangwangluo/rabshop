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
 * 店铺商品参数检索索引表。
 */
class CreateStoreProductParamIndexTable extends Migrator
{
    public function change()
    {
        $table = $this->table('store_product_param_index', [
            'comment' => '店铺商品参数检索索引表',
            'collation' => 'utf8mb4_general_ci',
        ]);
        $table->addColumn('store_product_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '店铺商品ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '归属商户ID，0=平台']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品分类ID']);
        $table->addColumn('attribute_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '参数模板ID']);
        $table->addColumn('param_key', 'string', ['limit' => 64, 'default' => '', 'comment' => '参数标识']);
        $table->addColumn('param_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '参数名称']);
        $table->addColumn('param_value', 'string', ['limit' => 100, 'default' => '', 'comment' => '参数值']);
        $table->addColumn('value_key', 'string', ['limit' => 100, 'default' => '', 'comment' => '参数值检索键']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['merchant_id', 'param_key', 'value_key'], ['name' => 'idx_merchant_param_value']);
        $table->addIndex(['merchant_id', 'category_id', 'param_key', 'value_key'], ['name' => 'idx_merchant_category_param_value']);
        $table->addIndex(['store_product_id', 'attribute_id', 'param_key', 'value_key'], ['name' => 'uniq_product_param_value', 'unique' => true]);
        $table->addIndex('store_product_id', ['name' => 'idx_store_product_id']);
        $table->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $table->addIndex('category_id', ['name' => 'idx_category_id']);
        $table->create();
    }
}
