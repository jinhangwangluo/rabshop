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

namespace app\validate\merchant;

use think\Validate;

/**
 * 商户验证类
 */
class MerchantValidate extends Validate
{
    /**
     * 验证规则
     * @var string[]
     */
    protected $rule = [
        'mer_name' => 'require|max:50',
        'mer_phone' => 'max:20',
        'contact_name' => 'max:30',
        'contact_phone' => 'max:20',
        'mer_address' => 'max:255',
        'description' => 'max:500',
        'mer_notice' => 'max:1000',
        'status' => 'in:0,1',
        'audit_status' => 'in:0,1,2',
        'business_status' => 'in:0,1',
        'commission_rate' => 'float|between:0,100',
        'settlement_cycle' => 'integer|egt:1',
        'sort' => 'integer',
        'is_recommend' => 'in:0,1',
        'product_audit_mode' => 'in:1,2',
        'live_audit_mode' => 'in:1,2',
        'live_product_audit_mode' => 'in:1,2',
        'content_audit_mode' => 'in:1,2',
    ];

    /**
     * 错误信息
     * @var string[]
     */
    protected $message = [
        'mer_name.require' => '商户名称不能为空',
        'mer_name.max' => '商户名称不能超过50个字符',
        'mer_phone.max' => '商户电话不能超过20个字符',
        'contact_name.max' => '联系人姓名不能超过30个字符',
        'contact_phone.max' => '联系人电话不能超过20个字符',
        'mer_address.max' => '商户地址不能超过255个字符',
        'description.max' => '商户描述不能超过500个字符',
        'mer_notice.max' => '店铺公告不能超过1000个字符',
        'status.in' => '状态值不正确',
        'audit_status.in' => '审核状态值不正确',
        'business_status.in' => '营业状态值不正确',
        'commission_rate.float' => '抽佣比例必须是数字',
        'commission_rate.between' => '抽佣比例必须在0-100之间',
        'settlement_cycle.integer' => '结算周期必须是整数',
        'settlement_cycle.egt' => '结算周期必须大于等于1天',
        'sort.integer' => '排序必须是整数',
        'is_recommend.in' => '推荐状态值不正确',
        'product_audit_mode.in' => '商品审核方式值不正确',
        'live_audit_mode.in' => '直播间审核方式值不正确',
        'live_product_audit_mode.in' => '直播商品审核方式值不正确',
        'content_audit_mode.in' => '内容审核方式值不正确',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'save' => [
            'mer_name',
            'mer_phone',
            'contact_name',
            'contact_phone',
            'mer_address',
            'description',
            'mer_notice',
            'status',
            'commission_rate',
            'settlement_cycle',
            'product_audit_mode',
            'live_audit_mode',
            'live_product_audit_mode',
            'content_audit_mode',
        ],
        'update' => [
            'mer_name',
            'mer_phone',
            'contact_name',
            'contact_phone',
            'mer_address',
            'description',
            'mer_notice',
            'status',
            'commission_rate',
            'settlement_cycle',
            'product_audit_mode',
            'live_audit_mode',
            'live_product_audit_mode',
            'content_audit_mode',
        ],
        'audit' => ['audit_status'],
        'store_setting' => ['mer_name', 'mer_avatar', 'mer_notice', 'description', 'business_hours', 'business_status', 'contact_name', 'contact_phone', 'mer_address'],
    ];
}
