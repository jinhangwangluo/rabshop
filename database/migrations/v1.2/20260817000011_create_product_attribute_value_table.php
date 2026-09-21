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
 * 商品属性值表（product_attribute_value）
 *
 * 属性预设值（如 产地=云南/新疆），input_type=2 时使用预设值供选择。
 */
class CreateProductAttributeValueTable extends Migrator
{
    /**
     * 创建商品属性值表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('product_attribute_value', ['comment' => '商品属性值表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('attribute_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '属性ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '归属商户ID，0=平台']);
        $table->addColumn('value', 'string', ['limit' => 100, 'default' => '', 'comment' => '属性值']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('attribute_id', ['name' => 'idx_attribute_id']);
        $table->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $table->addIndex('status', ['name' => 'idx_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->create();
    }
}
