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
 * 店铺商品规格配置表
 *
 * 单独保存商品规格模式与自定义规格结构，避免主表过胖。
 */
class CreateStoreProductSpecConfigTable extends Migrator
{
    public function change()
    {
        $table = $this->table('store_product_spec_config', ['comment' => '店铺商品规格配置表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('store_product_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '店铺商品ID']);
        $table->addColumn('spec_mode', 'string', ['limit' => 20, 'default' => 'library', 'comment' => '规格模式：library-商品库，custom-自定义']);
        $table->addColumn('custom_spec_type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '自定义规格类型：0-单规格，1-多规格']);
        $table->addColumn('library_product_ids', 'json', ['null' => true, 'comment' => '商品库选择ID列表(JSON数组)']);
        $table->addColumn('custom_specs', 'json', ['null' => true, 'comment' => '自定义规格卡片(JSON数组)']);
        $table->addColumn('custom_skus', 'json', ['null' => true, 'comment' => '自定义SKU行(JSON数组)']);
        $table->addTimestamps();
        $table->addIndex('store_product_id', ['name' => 'uniq_store_product_id', 'unique' => true]);
        $table->addIndex('spec_mode', ['name' => 'idx_spec_mode']);
        $table->create();
    }
}
