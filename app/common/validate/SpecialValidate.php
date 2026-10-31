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
 * 专题验证器
 */
class SpecialValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'title' => 'require|length:2,100|unique:special',
        'merchant_id' => 'number',
        'keywords' => 'length:0,100',
        'description' => 'length:0,255',
        'cover' => 'length:0,255',
        'sort' => 'number',
        'is_hot' => 'in:0,1',
        'status' => 'in:0,1',
        'contents' => 'array',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'title.require' => '专题标题不能为空',
        'title.length' => '专题标题长度为2-100个字符',
        'title.unique' => '专题标题已存在',
        'merchant_id.number' => '商户ID格式错误',
        'keywords.length' => '关键词长度不能超过100个字符',
        'description.length' => '描述长度不能超过255个字符',
        'cover.length' => '封面图片长度不能超过255个字符',
        'sort.number' => '排序必须为数字',
        'is_hot.in' => '是否热门值错误',
        'status.in' => '状态值错误',
        'contents.array' => '专题内容必须为数组',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['title', 'merchant_id', 'keywords', 'description', 'cover', 'sort', 'is_hot', 'status', 'contents'],
        'edit' => ['title', 'merchant_id', 'keywords', 'description', 'cover', 'sort', 'is_hot', 'status', 'contents'],
    ];
}
