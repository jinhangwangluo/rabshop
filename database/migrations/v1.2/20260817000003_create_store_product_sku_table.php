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
 * 商品SKU表（store_product_sku）★ SKU 五项规则核心
 *
 * 满足用户 5 项要求：
 * 1. 可删除单个 SKU（软删，delete_time 记录，唯一值不释放）
 * 2. 不能更改 SKU 唯一值（uniqid 存在时修改不可改，代码层校验）
 * 3. 可隐藏 SKU（status=0）
 * 4. 可排序 SKU（sort 字段，查询统一 order('sort desc, id asc')）
 * 5. 前台可根据 SKU 展示规格（spec_value_ids 为升序逗号串，前台规格数据源）
 *
 * uniqid = 来源库商品 product_code（仅普通索引，唯一性代码层保证，
 * 因为软删后不释放 uniqid，无法用数据库唯一索引表达）。
 * store_manage_id 为前置仓门店预留字段，本期恒为 0。
 */
class CreateStoreProductSkuTable extends Migrator
{
    /**
     * 创建商品SKU表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('store_product_sku', ['comment' => '商品SKU表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('store_product_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '店铺商品ID']);
        $table->addColumn('library_product_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '来源库商品ID（SKU 复制来源）']);
        $table->addColumn('store_manage_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '门店ID（前置仓预留，恒0）']);
        $table->addColumn('uniqid', 'string', ['limit' => 64, 'default' => '', 'comment' => 'SKU唯一值=来源库商品 product_code（不可改，软删不释放）']);
        $table->addColumn('spec_value_ids', 'string', ['limit' => 255, 'default' => '', 'comment' => '规格值ID升序逗号串（组合标识+前台规格数据源）']);
        $table->addColumn('sku', 'string', ['limit' => 255, 'default' => '', 'comment' => '展示文本（颜色*容量；自由SKU=库商品名）']);
        $table->addColumn('pics', 'string', ['limit' => 255, 'default' => '', 'comment' => 'SKU图片']);
        $table->addColumn('bar_code', 'string', ['limit' => 64, 'default' => '', 'comment' => 'SKU编码（warehouse 兼容）']);
        $table->addColumn('bar_number', 'string', ['limit' => 64, 'default' => '', 'comment' => 'SKU条码（warehouse 兼容）']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => 'SKU售价（复制可覆盖）']);
        $table->addColumn('ot_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => 'SKU划线价']);
        $table->addColumn('store_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => 'SKU成本价']);
        $table->addColumn('stock', 'integer', ['limit' => 11, 'default' => 0, 'comment' => 'SKU库存（扣减以此为准）']);
        $table->addColumn('sales', 'integer', ['limit' => 11, 'default' => 0, 'comment' => 'SKU销量']);
        $table->addColumn('unit', 'string', ['limit' => 20, 'default' => '', 'comment' => '单位']);
        $table->addColumn('weight', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '重量(克，运费计算用)']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-隐藏，1-正常']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => 'SKU排序（越大越靠前）']);
        $table->addColumn('is_default', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否默认规格：0-否，1-是']);
        $table->addColumn('guard', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '警戒库存阈值（warehouse 兼容）']);
        $table->addColumn('guard_status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否触发警戒：0-否，1-是（warehouse 兼容）']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('store_product_id', ['name' => 'idx_store_product_id']);
        $table->addIndex('library_product_id', ['name' => 'idx_library_product_id']);
        $table->addIndex('uniqid', ['name' => 'idx_uniqid']);
        $table->addIndex('status', ['name' => 'idx_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->addIndex('store_manage_id', ['name' => 'idx_store_manage_id']);
        $table->create();
    }
}
