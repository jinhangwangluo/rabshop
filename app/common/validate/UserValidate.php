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
 * 用户验证器
 */
class UserValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'username' => 'require|length:3,20|unique:user',
        'password' => 'require|length:6,20',
        'nickname' => 'require|length:2,30',
        'phone' => 'require|mobile|unique:user',
        'email' => 'email|unique:user',
        'avatar' => 'length:0,255',
        'sex' => 'in:0,1,2',
        'status' => 'in:0,1',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'username.require' => '用户名不能为空',
        'username.length' => '用户名长度为3-20个字符',
        'username.unique' => '用户名已存在',
        'password.require' => '密码不能为空',
        'password.length' => '密码长度为6-20个字符',
        'nickname.require' => '昵称不能为空',
        'nickname.length' => '昵称长度为2-30个字符',
        'phone.require' => '手机号不能为空',
        'phone.mobile' => '手机号格式错误',
        'phone.unique' => '手机号已存在',
        'email.email' => '邮箱格式错误',
        'email.unique' => '邮箱已存在',
        'avatar.length' => '头像路径长度不能超过255个字符',
        'sex.in' => '性别值错误',
        'status.in' => '状态值错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['username', 'password', 'nickname', 'phone', 'email', 'avatar', 'sex', 'status'],
        'edit' => ['nickname', 'phone', 'email', 'avatar', 'sex', 'status'],
        'login' => ['username', 'password'],
    ];
}
