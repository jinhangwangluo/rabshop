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
 * 商品品牌表（product_brand）
 *
 * 品牌维度，平台/商户均可维护，商户只能看到自己的品牌（merchant_id=0 平台公共可复用）。
 */
class CreateProductBrandTable extends Migrator
{
    /**
     * 创建商品品牌表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('product_brand', ['comment' => '商品品牌表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '归属商户ID，0=平台']);
        $table->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '品牌名称']);
        $table->addColumn('logo', 'string', ['limit' => 255, 'default' => '', 'comment' => '品牌Logo']);
        $table->addColumn('letter', 'string', ['limit' => 10, 'default' => '', 'comment' => '首字母']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '品牌描述']);
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
