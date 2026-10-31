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

namespace app\validate\setting;

use think\Validate;

/**
 * 省市区地址验证器。
 */
class CityAreaValidate extends Validate
{
    /**
     * @var string[]
     */
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'parent_id' => 'require|integer|egt:0',
        'type' => 'require|integer|between:1,3',
        'name' => 'require|max:64',
        'level' => 'require|integer|between:1,3',
        'sort' => 'integer|egt:0',
        'status' => 'integer|in:0,1',
    ];

    /**
     * @var string[]
     */
    protected $message = [
        'parent_id.require' => '请选择上级地址',
        'name.require' => '请输入地址名称',
        'name.max' => '地址名称最多64个字符',
        'level.between' => '地址层级只能为省、市、区',
        'type.between' => '地址类型只能为省、市、区',
    ];
}
