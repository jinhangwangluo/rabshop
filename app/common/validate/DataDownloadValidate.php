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
 * 资料下载验证器
 */
class DataDownloadValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'title' => 'require|length:2,100|unique:data_download',
        'cate_id' => 'number',
        'mer_id' => 'number',
        'keywords' => 'length:0,100',
        'description' => 'length:0,255',
        'cover' => 'length:0,255',
        'file_url' => 'require|length:5,255',
        'file_size' => 'length:0,20',
        'sort' => 'number',
        'is_show' => 'in:0,1',
        'status' => 'in:0,1',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'title.require' => '资料标题不能为空',
        'title.length' => '资料标题长度为2-100个字符',
        'title.unique' => '资料标题已存在',
        'cate_id.number' => '分类ID格式错误',
        'mer_id.number' => '商户ID格式错误',
        'keywords.length' => '关键词长度不能超过100个字符',
        'description.length' => '描述长度不能超过255个字符',
        'cover.length' => '封面图片长度不能超过255个字符',
        'file_url.require' => '文件路径不能为空',
        'file_url.length' => '文件路径长度为5-255个字符',
        'file_size.length' => '文件大小长度不能超过20个字符',
        'sort.number' => '排序必须为数字',
        'is_show.in' => '是否显示值错误',
        'status.in' => '状态值错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['title', 'cate_id', 'mer_id', 'keywords', 'description', 'cover', 'file_url', 'file_size', 'sort', 'is_show', 'status'],
        'edit' => ['title', 'cate_id', 'mer_id', 'keywords', 'description', 'cover', 'file_url', 'file_size', 'sort', 'is_show', 'status'],
    ];
}
