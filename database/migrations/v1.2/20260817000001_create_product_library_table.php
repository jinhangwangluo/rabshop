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
 * 商品库表（product_library）
 *
 * 商品库 = 单规格商品池，平台/商户共建：
 * - owner_merchant_id = 0 为平台自建商品（默认共享），>0 为商户自建（默认私有，可设共享）
 * - 每个库商品恒为单规格，product_code 即该库商品的 uniqid（SKU 唯一值来源）
 * - spec_value_id 为组合归类用的规格值绑定（可空），上架组合时按此分组生成店铺商品规格体系
 * - store_manage_id 为前置仓门店预留字段，本期恒为 0
 */
class CreateProductLibraryTable extends Migrator
{
    /**
     * 创建商品库表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('product_library', ['comment' => '商品库表（单规格商品池，平台/商户共建）', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('owner_merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '归属商户ID，0=平台自建']);
        $table->addColumn('is_share', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否共享：0-私有，1-共享（平台商品默认1，商户自建默认0）']);
        $table->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '库商品名称']);
        $table->addColumn('keyword', 'string', ['limit' => 255, 'default' => '', 'comment' => '关键字']);
        $table->addColumn('product_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '库编码 get_sn(SP)，即库商品 uniqid；前置仓多门店关联ID']);
        $table->addColumn('bar_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '条码']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '池分类ID（category type=10，池管理/筛选）']);
        $table->addColumn('brand_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '品牌ID']);
        $table->addColumn('group_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分组ID']);
        $table->addColumn('spec_value_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '绑定规格值ID（组合归类用，可空）']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '库基准售价']);
        $table->addColumn('ot_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '库基准划线价']);
        $table->addColumn('store_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '库基准成本价']);
        $table->addColumn('stock', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '库基准库存']);
        $table->addColumn('sales', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '库基准销量']);
        $table->addColumn('unit', 'string', ['limit' => 20, 'default' => '', 'comment' => '单位']);
        $table->addColumn('goods_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '商品类型：1-实物，2-虚拟，3-内容（对齐 OrderGoods 常量）']);
        $table->addColumn('cover', 'string', ['limit' => 255, 'default' => '', 'comment' => '封面图']);
        $table->addColumn('pics', 'json', ['null' => true, 'comment' => '轮播图(JSON数组)']);
        $table->addColumn('video', 'string', ['limit' => 255, 'default' => '', 'comment' => '视频']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '商品简介']);
        $table->addColumn('detail', 'text', ['null' => true, 'comment' => '商品详情富文本']);
        $table->addColumn('attr_values', 'json', ['null' => true, 'comment' => '参数属性(JSON数组)']);
        $table->addColumn('service_ids', 'string', ['limit' => 255, 'default' => '', 'comment' => '服务ID逗号串']);
        $table->addColumn('freight_template_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '默认运费模板ID']);
        $table->addColumn('gift_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '赠送积分']);
        $table->addColumn('first_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0, 'comment' => '一级佣金比例(%)']);
        $table->addColumn('second_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0, 'comment' => '二级佣金比例(%)']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addColumn('audit_status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '审核状态：0-待审，1-通过，2-驳回']);
        $table->addColumn('on_sale_time', 'datetime', ['null' => true, 'comment' => '上架时间']);
        $table->addColumn('off_sale_time', 'datetime', ['null' => true, 'comment' => '下架时间']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']);
        $table->addColumn('store_manage_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '门店ID（前置仓预留，恒0）']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('owner_merchant_id', ['name' => 'idx_owner_merchant_id']);
        $table->addIndex('is_share', ['name' => 'idx_is_share']);
        $table->addIndex('category_id', ['name' => 'idx_category_id']);
        $table->addIndex('product_code', ['name' => 'idx_product_code']);
        $table->addIndex('brand_id', ['name' => 'idx_brand_id']);
        $table->addIndex('group_id', ['name' => 'idx_group_id']);
        $table->addIndex('spec_value_id', ['name' => 'idx_spec_value_id']);
        $table->addIndex('status', ['name' => 'idx_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->addIndex('store_manage_id', ['name' => 'idx_store_manage_id']);
        $table->create();
    }
}
