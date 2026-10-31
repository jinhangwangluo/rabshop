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
 * 商户入驻申请验证器
 */
class MerchantApplyValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'mer_name' => 'require|length:2,50|unique:merchant_apply',
        'mer_phone' => 'require|mobile',
        'mer_address' => 'require|length:5,255',
        'contact_name' => 'require|length:2,30',
        'contact_phone' => 'require|mobile',
        'business_license' => 'require',
        'status' => 'in:0,1,2',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'mer_name.require' => '商户名称不能为空',
        'mer_name.length' => '商户名称长度为2-50个字符',
        'mer_name.unique' => '商户名称已存在',
        'mer_phone.require' => '商户电话不能为空',
        'mer_phone.mobile' => '商户电话格式错误',
        'mer_address.require' => '商户地址不能为空',
        'mer_address.length' => '商户地址长度为5-255个字符',
        'contact_name.require' => '联系人姓名不能为空',
        'contact_name.length' => '联系人姓名长度为2-30个字符',
        'contact_phone.require' => '联系人电话不能为空',
        'contact_phone.mobile' => '联系人电话格式错误',
        'business_license.require' => '营业执照不能为空',
        'status.in' => '状态值错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['mer_name', 'mer_phone', 'mer_address', 'contact_name', 'contact_phone', 'business_license'],
        'edit' => ['mer_name', 'mer_phone', 'mer_address', 'contact_name', 'contact_phone', 'business_license'],
        'audit' => ['status'],
    ];
}
