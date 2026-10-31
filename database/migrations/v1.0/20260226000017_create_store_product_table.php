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
 * 商品/内容表
 */
class CreateStoreProductTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('store_product', ['comment' => '商品/内容表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分类ID']);
        $table->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '商品名称']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '商品描述']);
        $table->addColumn('cover', 'string', ['limit' => 255, 'default' => '', 'comment' => '商品封面']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '商品价格']);
        $table->addColumn('original_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '原价']);
        $table->addColumn('stock', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '库存']);
        $table->addColumn('sales', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '销量']);
        $table->addColumn('is_platform', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否平台商品：0-否，1-是']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('merchant_id');
        $table->addIndex('category_id');
        $table->addIndex('status');
        $table->addIndex('is_platform');
        $table->create();
    }
}
