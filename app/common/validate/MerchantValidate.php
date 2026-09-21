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
 * 商户验证器
 */
class MerchantValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'mer_name' => 'require|length:2,50|unique:merchant',
        'mer_phone' => 'require|mobile',
        'mer_address' => 'require|length:5,255',
        'contact_name' => 'require|length:2,30',
        'contact_phone' => 'require|mobile',
        'logo' => 'length:0,255',
        'mer_banner' => 'length:0,255',
        'mer_street_view' => 'length:0,255',
        'mer_keyword' => 'length:0,100',
        'description' => 'length:0,500',
        'lat' => 'number',
        'lng' => 'number',
        'mer_class' => 'in:1,2',
        'product_audit_mode' => 'in:1,2',
        'live_audit_mode' => 'in:1,2',
        'live_product_audit_mode' => 'in:1,2',
        'content_audit_mode' => 'in:1,2',
        'status' => 'in:0,1',
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
        'logo.length' => ' logo 长度不能超过 255 个字符',
        'mer_banner.length' => '商户店面图长度不能超过 255 个字符',
        'mer_street_view.length' => '商户街景图长度不能超过 255 个字符',
        'mer_keyword.length' => '店铺关键字长度不能超过 100 个字符',
        'description.length' => '描述长度不能超过500个字符',
        'lat.number' => '纬度必须是数字',
        'lng.number' => '经度必须是数字',
        'mer_class.in' => '店铺类别值错误',
        'product_audit_mode.in' => '商品审核方式值错误',
        'live_audit_mode.in' => '直播间审核方式值错误',
        'live_product_audit_mode.in' => '直播商品审核方式值错误',
        'content_audit_mode.in' => '内容审核方式值错误',
        'status.in' => '状态值错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => [
            'mer_name',
            'mer_phone',
            'mer_address',
            'contact_name',
            'contact_phone',
            'logo',
            'mer_banner',
            'mer_street_view',
            'mer_keyword',
            'description',
            'lat',
            'lng',
            'mer_class',
            'product_audit_mode',
            'live_audit_mode',
            'live_product_audit_mode',
            'content_audit_mode',
            'status'
        ],
        'edit' => [
            'mer_name',
            'mer_phone',
            'mer_address',
            'contact_name',
            'contact_phone',
            'logo',
            'mer_banner',
            'mer_street_view',
            'mer_keyword',
            'description',
            'lat',
            'lng',
            'mer_class',
            'product_audit_mode',
            'live_audit_mode',
            'live_product_audit_mode',
            'content_audit_mode',
            'status'
        ],
    ];
}
