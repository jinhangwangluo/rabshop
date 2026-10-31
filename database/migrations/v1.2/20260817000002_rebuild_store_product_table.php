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
 * 商品表重建（store_product）
 *
 * 旧 store_product 为 v1.0 遗留极简结构（15 字段，全项目零引用，执行前已确认行数=0），
 * 本次重建为店铺运营副本结构：
 * - merchant_id = 运营商户（0=平台运营），来源为商品库 product_library
 * - 单规格店铺商品（spec_type=0）直接引用库商品；多规格（spec_type=1）由多个单规格库商品组合而来
 * - 三价/库存/销量与 SKU 行对齐：单规格与 SKU 一致，多规格为 SKU 汇总；库存扣减以 SKU 为准
 * - sales/view_count 为订单链路累计的真实统计；virtual_sales/virtual_view_count 为可配置的展示基数
 * - store_manage_id 为前置仓门店预留字段，本期恒为 0
 */
class RebuildStoreProductTable extends Migrator
{
    /**
     * 重建商品表：旧表存在则先删除再创建（破坏性操作，仅限确认旧表无数据后执行）
     *
     * @return void
     */
    public function up()
    {
        // drop() 仅排队 DropTable 动作，需 save() 立即执行（不同 Table 实例的动作队列相互独立）
        if ($this->hasTable('store_product')) {
            $this->table('store_product')->drop()->save();
        }

        $table = $this->table('store_product', ['comment' => '商品表（店铺运营副本）', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '运营商户ID，0=平台运营']);
        $table->addColumn('library_product_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '来源库商品ID（单规格=引用；多规格=0，SKU 行各自带来源）']);
        $table->addColumn('spec_type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '规格类型：0-单规格，1-多规格（组合结果）']);
        $table->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '店铺商品名称（可改）']);
        $table->addColumn('keyword', 'string', ['limit' => 255, 'default' => '', 'comment' => '关键字']);
        $table->addColumn('product_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '关联编码（单规格=库商品编码；多规格为空）']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '平台商品分类ID（移动端展示与筛选依据）']);
        $table->addColumn('brand_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '品牌ID（继承可改）']);
        $table->addColumn('group_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分组ID（继承可改）']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '店铺售价快照（SKU 最低价，可覆盖库价）']);
        $table->addColumn('ot_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '店铺划线价快照']);
        $table->addColumn('store_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '店铺成本价快照']);
        $table->addColumn('stock', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '库存汇总（单规格与SKU一致，多规格为正常SKU之和；扣减以SKU为准）']);
        $table->addColumn('sales', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '实际销量（订单链路累计，不使用虚拟销量替代）']);
        $table->addColumn('virtual_sales', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '虚拟销量展示基数（仅前台展示，不参与库存/结算/佣金/统计）']);
        $table->addColumn('view_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '实际浏览量（详情访问累计）']);
        $table->addColumn('virtual_view_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '虚拟浏览量展示基数（仅前台展示，不替代实际浏览量）']);
        $table->addColumn('unit', 'string', ['limit' => 20, 'default' => '', 'comment' => '商品单位']);
        $table->addColumn('goods_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '商品类型：1-实物，2-虚拟，3-内容']);
        $table->addColumn('cover', 'string', ['limit' => 255, 'default' => '', 'comment' => '封面图']);
        $table->addColumn('pics', 'json', ['null' => true, 'comment' => '轮播图(JSON数组)']);
        $table->addColumn('video', 'string', ['limit' => 255, 'default' => '', 'comment' => '视频']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '商品简介']);
        $table->addColumn('detail', 'text', ['null' => true, 'comment' => '商品详情富文本']);
        $table->addColumn('is_free_shipping', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '商品级包邮：0-按运费模板计算，1-直接包邮']);
        $table->addColumn('attr_values', 'json', ['null' => true, 'comment' => '参数属性(JSON数组)']);
        $table->addColumn('service_ids', 'string', ['limit' => 255, 'default' => '', 'comment' => '服务ID逗号串']);
        $table->addColumn('freight_template_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '运费模板ID（is_free_shipping=0 时使用）']);
        $table->addColumn('gift_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '赠送积分（继承可覆盖）']);
        $table->addColumn('first_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0, 'comment' => '一级佣金比例(%)（继承可覆盖）']);
        $table->addColumn('second_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0, 'comment' => '二级佣金比例(%)（继承可覆盖）']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-下架，1-上架']);
        $table->addColumn('audit_status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '审核状态：0-待审，1-通过，2-驳回']);
        $table->addColumn('on_sale_time', 'datetime', ['null' => true, 'comment' => '上架时间']);
        $table->addColumn('off_sale_time', 'datetime', ['null' => true, 'comment' => '下架时间']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']);
        $table->addColumn('store_manage_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '门店ID（前置仓预留，恒0）']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $table->addIndex('library_product_id', ['name' => 'idx_library_product_id']);
        $table->addIndex('category_id', ['name' => 'idx_category_id']);
        $table->addIndex('product_code', ['name' => 'idx_product_code']);
        $table->addIndex('brand_id', ['name' => 'idx_brand_id']);
        $table->addIndex('group_id', ['name' => 'idx_group_id']);
        $table->addIndex('status', ['name' => 'idx_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->addIndex('sales', ['name' => 'idx_sales']);
        $table->addIndex('view_count', ['name' => 'idx_view_count']);
        $table->addIndex('store_manage_id', ['name' => 'idx_store_manage_id']);
        $table->create();
    }

    /**
     * 回滚：删除商品表
     *
     * @return void
     */
    public function down()
    {
        $this->table('store_product')->drop()->save();
    }
}
