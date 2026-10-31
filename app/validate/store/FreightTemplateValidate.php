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

namespace app\validate\store;

use think\Validate;

/**
 * 运费模板验证器
 *
 * 校验模板名称/发货时间/计价方式必填，默认与包邮状态合法，
 * 首续件数量与运费金额数值边界。
 */
class FreightTemplateValidate extends Validate
{
    /**
     * 校验规则
     * @var array
     */
    protected $rule = [
        'name' => 'require|max:50',
        'delivery_time' => 'require|max:50',
        'is_default' => 'integer|in:0,1',
        'is_free' => 'integer|in:0,1',
        'charge_type' => 'require|integer|in:1,2',
        'default_first_quantity' => 'float|gt:0',
        'default_first_amount' => 'float|egt:0',
        'default_continue_quantity' => 'float|egt:0',
        'default_continue_amount' => 'float|egt:0',
        'free_amount' => 'float|egt:0',
        'status' => 'integer|in:0,1',
        'sort' => 'integer|egt:0',
    ];

    /**
     * 错误提示
     * @var array
     */
    protected $message = [
        'name.require' => '请输入模板名称',
        'name.max' => '模板名称最多50个字符',
        'delivery_time.require' => '请选择发货时间',
        'charge_type.require' => '请选择计价方式',
        'charge_type.in' => '计价方式不正确',
        'default_first_quantity.gt' => '首件/首重数量必须大于0',
        'is_default.in' => '默认模板状态不正确',
        'is_free.in' => '包邮状态不正确',
    ];
}
