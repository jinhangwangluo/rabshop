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

namespace app\validate;


use think\Validate;

/**
 * 文章验证
 * Class ArticleValidate
 * @author 橙猫
 * @email 17349395805@163.com
 * @date 2023/10/27
 * @package app\validate
 */
class ArticleValidate extends Validate
{
    /**
     * @var string[]
     */
    protected $rule = [
        'title' => 'require|min:5',
        'content' => 'require|min:100',
        'cate_id' => 'require',
        'label' => 'require|array',
        'info' => 'require|min:5',
    ];

    /**
     * @var string[]
     */
    protected $message = [
        'title.require' => '文章标题必须填写',
        'title.min' => '文章标题不能少于10个字符',
        'content.require' => '文章内容必须填写',
        'content.min' => '文章内容不能少于10个字符',
        'cate_id.require' => '请选择板块',
        'label.require' => '请选择标签',
        'label.array' => '标签必须为数组',
        'info.require' => '文章摘要必须填写',
        'info.min' => '文章摘要不能少于10个字符',
    ];
}
