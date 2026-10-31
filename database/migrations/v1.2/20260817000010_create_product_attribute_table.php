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
 * 商品属性表（product_attribute）
 *
 * 商品参数属性（如：产地、保质期），不参与 SKU 组合，仅作为商品参数展示。
 * attr_type：1-文本，2-单选，3-多选；input_type：1-手动输入，2-预设值选择。
 * 绑定关系存于店铺商品的 attr_values JSON 字段。
 */
class CreateProductAttributeTable extends Migrator
{
    /**
     * 创建商品属性表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('product_attribute', ['comment' => '商品属性表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '归属商户ID，0=平台']);
        $table->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '属性名称（产地/保质期）']);
        $table->addColumn('attr_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '属性类型：1-文本，2-单选，3-多选']);
        $table->addColumn('input_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '录入方式：1-手动，2-预设']);
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
