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
 * DIY分类验证器
 */
class DiyCategoryValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require|length:2,30',
        'pid' => 'number',
        'sort' => 'number',
        'status' => 'in:0,1',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'name.require' => '分类名称不能为空',
        'name.length' => '分类名称长度为2-30个字符',
        'pid.number' => '父级分类格式错误',
        'sort.number' => '排序必须为数字',
        'status.in' => '状态值错误',
    ];
}
