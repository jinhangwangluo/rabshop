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
 * 角色验证器
 */
class RoleValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require|length:2,30|unique:admin_role',
        'description' => 'length:0,255',
        'rule' => 'require|array',
        'status' => 'in:0,1',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'name.require' => '角色名称不能为空',
        'name.length' => '角色名称长度为2-30个字符',
        'name.unique' => '角色名称已存在',
        'description.length' => '角色描述长度不能超过255个字符',
        'rule.require' => '请选择权限',
        'rule.array' => '权限格式错误',
        'status.in' => '状态值错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['name', 'description', 'rule', 'status'],
        'edit' => ['name', 'description', 'rule', 'status'],
    ];
}
