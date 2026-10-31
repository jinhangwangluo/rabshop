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

namespace app\common\validate;

use think\Validate;

/**
 * 订单验证器
 */
class OrderValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'order_sn' => 'require|length:10,32|unique:order',
        'user_id' => 'require|number',
        'merchant_id' => 'require|number',
        'total_amount' => 'require|float|gt:0',
        'pay_amount' => 'require|float|gt:0',
        'pay_type' => 'require|length:1,20',
        'status' => 'in:0,1,2,3,4,5',
        'remark' => 'length:0,255',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'order_sn.require' => '订单号不能为空',
        'order_sn.length' => '订单号长度为10-32个字符',
        'order_sn.unique' => '订单号已存在',
        'user_id.require' => '用户ID不能为空',
        'user_id.number' => '用户ID格式错误',
        'merchant_id.require' => '商户ID不能为空',
        'merchant_id.number' => '商户ID格式错误',
        'total_amount.require' => '总金额不能为空',
        'total_amount.float' => '总金额格式错误',
        'total_amount.gt' => '总金额必须大于0',
        'pay_amount.require' => '支付金额不能为空',
        'pay_amount.float' => '支付金额格式错误',
        'pay_amount.gt' => '支付金额必须大于0',
        'pay_type.require' => '支付方式不能为空',
        'pay_type.length' => '支付方式长度为1-20个字符',
        'status.in' => '状态值错误',
        'remark.length' => '备注长度不能超过255个字符',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['order_sn', 'user_id', 'merchant_id', 'total_amount', 'pay_amount', 'pay_type', 'status', 'remark'],
        'edit' => ['user_id', 'merchant_id', 'total_amount', 'pay_amount', 'pay_type', 'status', 'remark'],
    ];
}
