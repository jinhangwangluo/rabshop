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
 * 菜单验证器
 */
class MenuValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'pid' => 'require|number',
        'title' => 'require|length:2,30',
        'icon' => 'length:0,50',
        'href' => 'length:0,100',
        'sort' => 'number|min:0',
        'status' => 'in:0,1',
        'is_show' => 'in:0,1',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'pid.require' => '父级菜单不能为空',
        'pid.number' => '父级菜单格式错误',
        'title.require' => '菜单标题不能为空',
        'title.length' => '菜单标题长度为2-30个字符',
        'icon.length' => '图标长度不能超过50个字符',
        'href.length' => '链接长度不能超过100个字符',
        'sort.number' => '排序必须为数字',
        'sort.min' => '排序不能为负数',
        'status.in' => '状态值错误',
        'is_show.in' => '显示状态值错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['pid', 'title', 'icon', 'href', 'sort', 'status', 'is_show'],
        'edit' => ['pid', 'title', 'icon', 'href', 'sort', 'status', 'is_show'],
    ];
}
