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
 * 订单表
 */
class CreateOrderTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('order', ['comment' => '订单表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('pid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '父订单ID，0表示无父级']);
        $table->addColumn('order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '订单号']);
        $table->addColumn('main_order_sn', 'string', ['limit' => 32, 'default' => '', 'comment' => '主支付订单号']);
        $table->addColumn('order_role', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '订单角色：0-普通订单，1-主支付单，2-商户子单']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('original_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商品原价总额']);
        $table->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '订单总金额']);
        $table->addColumn('discount_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '优惠金额']);
        $table->addColumn('use_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '使用积分数量']);
        $table->addColumn('points_deduct_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '积分抵扣金额']);
        $table->addColumn('member_discount_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '会员折扣金额']);
        $table->addColumn('member_discount_rate', 'decimal', ['precision' => 4, 'scale' => 2, 'default' => 1.00, 'comment' => '会员折扣率（0.80=8折）']);
        $table->addColumn('postage', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '邮费']);
        $table->addColumn('pay_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '实付金额']);
        $table->addColumn('order_type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '订单类型：1-内容订单，2-商品订单，3-专题订单']);
        $table->addColumn('merchant_coupon_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户优惠券ID']);
        $table->addColumn('merchant_coupon_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商户优惠券优惠金额']);
        $table->addColumn('platform_coupon_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '平台优惠券ID']);
        $table->addColumn('platform_coupon_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '平台优惠券优惠金额']);
        $table->addColumn('seckill_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '秒杀活动ID']);
        $table->addColumn('group_buy_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '拼团活动ID']);
        $table->addColumn('referrer_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '上级推广人ID']);
        $table->addColumn('second_referrer_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '二级推广人ID快照']);
        $table->addColumn('first_commission_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '一级推广金额']);
        $table->addColumn('second_commission_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '二级推广金额']);
        $table->addColumn('commission_base_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '返佣计算基数']);
        $table->addColumn('first_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '一级返佣比例快照，百分比']);
        $table->addColumn('second_commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '二级返佣比例快照，百分比']);
        $table->addColumn('commission_level', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '返佣等级快照：0无，1一级，2二级']);
        $table->addColumn('commission_mode', 'string', ['limit' => 20, 'default' => '', 'comment' => '返佣模式快照：all人人分销，specified指定分销']);
        $table->addColumn('platform_commission_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '平台抽佣汇总金额']);
        $table->addColumn('merchant_income_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '商户入账汇总金额']);
        $table->addColumn('address_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '收货地址ID']);
        $table->addColumn('real_name', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货人姓名']);
        $table->addColumn('user_phone', 'string', ['limit' => 20, 'default' => '', 'comment' => '收货人手机号']);
        $table->addColumn('province', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货省']);
        $table->addColumn('city', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货市']);
        $table->addColumn('district', 'string', ['limit' => 64, 'default' => '', 'comment' => '收货区']);
        $table->addColumn('detail', 'string', ['limit' => 255, 'default' => '', 'comment' => '详细地址']);
        $table->addColumn('user_address', 'string', ['limit' => 255, 'default' => '', 'comment' => '完整收货地址']);
        $table->addColumn('pay_type', 'string', ['limit' => 20, 'default' => '', 'comment' => '支付方式：yue=余额支付、wechat=微信支付、alipay=支付宝']);
        $table->addColumn('delivery_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '物流公司名称']);
        $table->addColumn('delivery_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '快递单号']);
        $table->addColumn('delivery_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '物流公司编码']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-待支付，1-已支付，2-已完成，3-已取消，4-已退款，5-退款中，6-待评价']);
        $table->addColumn('refund_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '退款状态：0-未退款，1-退款中，2-已退款，3-部分退款']);
        $table->addColumn('refund_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '退款金额']);
        $table->addColumn('refund_reason', 'string', ['limit' => 255, 'default' => '', 'comment' => '退款原因']);
        $table->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注']);
        $table->addColumn('pay_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '支付时间']);
        $table->addColumn('delivery_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '发货时间']);
        $table->addColumn('finish_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '完成时间']);
        $table->addColumn('refund_application_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '退款申请时间']);
        $table->addColumn('refund_reason_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '退款处理时间']);
        $table->addColumn('receive_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '确认收货时间',]);
        $table->addColumn('commission_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '佣金状态：0-待处理，1-冻结中，2-已发放',]);
        $table->addColumn('commission_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '佣金发放时间']);
        $table->addIndex('commission_status');
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('pid');
        $table->addIndex('order_sn', ['unique' => true]);
        $table->addIndex('main_order_sn');
        $table->addIndex('order_role');
        $table->addIndex('user_id');
        $table->addIndex('merchant_id');
        $table->addIndex('address_id');
        $table->addIndex('status');
        $table->addIndex('refund_status');
        $table->addIndex('pay_type');
        $table->addIndex('order_type');
        $table->addIndex('referrer_id');
        $table->addIndex('second_referrer_id');
        $table->addIndex('commission_level');
        $table->addIndex('commission_mode');
        $table->addIndex(['status', 'create_time'], ['name' => 'idx_status_create_time']);
        $table->create();
    }
}
