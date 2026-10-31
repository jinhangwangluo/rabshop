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
 * 商户表
 */
class CreateMerchantTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('merchant', ['comment' => '商户表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('mer_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '商户名称']);
        $table->addColumn('mer_phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '商户电话']);
        $table->addColumn('mer_address', 'string', ['limit' => 255, 'default' => '', 'comment' => '商户地址']);
        $table->addColumn('contact_name', 'string', ['limit' => 30, 'default' => '', 'comment' => '联系人姓名']);
        $table->addColumn('contact_phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '联系人电话']);
        $table->addColumn('logo', 'string', ['limit' => 255, 'default' => '', 'comment' => '商户logo']);
        $table->addColumn('mer_avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '店铺头像']);
        $table->addColumn('mer_notice', 'string', ['limit' => 1000, 'default' => '', 'comment' => '店铺公告']);
        $table->addColumn('business_hours', 'string', ['limit' => 100, 'default' => '', 'comment' => '营业时间JSON']);
        $table->addColumn('business_status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '营业状态：0-休息，1-营业']);
        $table->addColumn('commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0, 'comment' => '平台抽佣比例(%)']);
        $table->addColumn('settlement_cycle', 'integer', ['limit' => 11, 'default' => 7, 'comment' => '结算周期(天)']);
        $table->addColumn('qualification', 'text', ['null' => true, 'comment' => '资质信息JSON']);
        $table->addColumn('audit_status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '审核状态：0-待审核，1-已通过，2-已驳回']);
        $table->addColumn('audit_remark', 'string', ['limit' => 500, 'default' => '', 'comment' => '审核备注']);
        $table->addColumn('audit_time', 'datetime', ['null' => true, 'comment' => '审核时间']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('is_recommend', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否推荐：0-否，1-是']);
        $table->addColumn('total_sales', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '累计销售额']);
        $table->addColumn('total_orders', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '累计订单数']);
        $table->addColumn('follow_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '关注人数']);
        $table->addColumn('description', 'string', ['limit' => 500, 'default' => '', 'comment' => '商户描述']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addColumn('is_member_discount', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '是否参与会员折扣：0关闭 1开启']);
        // 店铺信息相关字段
        $table->addColumn('mer_banner', 'string', ['limit' => 255, 'default' => '', 'comment' => '店铺背景图']);
        $table->addColumn('merchant_home_banner', 'text', ['null' => true, 'default' => null, 'comment' => '商家首页轮播图JSON数组，最多8张']);
        $table->addColumn('merchant_home_recommend_special', 'text', ['null' => true, 'default' => null, 'comment' => '商家首页推荐专题ID JSON数组']);
        $table->addColumn('mer_street_view', 'string', ['limit' => 255, 'default' => '', 'comment' => '店铺街景背景图']);
        $table->addColumn('mer_keyword', 'string', ['limit' => 100, 'default' => '', 'comment' => '店铺关键字']);
        // 地址相关字段
        $table->addColumn('lat', 'decimal', ['precision' => 10, 'scale' => 6, 'default' => 0, 'comment' => '纬度']);
        $table->addColumn('lng', 'decimal', ['precision' => 10, 'scale' => 6, 'default' => 0, 'comment' => '经度']);
        $table->addColumn('mer_class', 'integer', ['limit' => 1, 'default' => 2, 'comment' => '店铺类别：1-自营，2-非自营']);
        // 审核方式字段
        $table->addColumn('product_audit_mode', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '商品审核：1-平台审核，2-免审核']);
        $table->addColumn('live_audit_mode', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '直播间审核：1-平台审核，2-免审核']);
        $table->addColumn('live_product_audit_mode', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '直播商品审核：1-平台审核，2-免审核']);
        $table->addColumn('content_audit_mode', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '内容审核：1-平台审核，2-免审核']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('status');
        $table->create();
    }
}
