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
 * DIY页面链接验证器
 */
class DiyPageLinkValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'cate_id' => 'require|number|gt:0',
        'link_name' => 'require|length:2,100',
        'link_url' => 'max:500',
        'link_type' => 'require|in:page,url,mini',
        'platform_type' => 'require|in:common,h5,mp,pc',
        'mini_appid' => 'max:100',
        'mini_path' => 'max:500',
        'sort' => 'number',
        'status' => 'in:0,1',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'cate_id.require' => '请选择所属分类',
        'cate_id.number' => '所属分类格式错误',
        'cate_id.gt' => '请选择所属分类',
        'link_name.require' => '链接名称不能为空',
        'link_name.length' => '链接名称长度为2-100个字符',
        'link_url.max' => '链接地址长度不能超过500个字符',
        'link_type.require' => '请选择链接类型',
        'link_type.in' => '链接类型不正确',
        'platform_type.require' => '请选择平台类型',
        'platform_type.in' => '平台类型不正确',
        'mini_appid.max' => '小程序 AppID 长度不能超过100个字符',
        'mini_path.max' => '小程序页面路径长度不能超过500个字符',
        'sort.number' => '排序必须为数字',
        'status.in' => '状态值错误',
    ];
}
