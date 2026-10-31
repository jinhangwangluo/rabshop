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

use app\common\enum\ContentTypeEnum;
use think\Validate;

/**
 * 文章验证器
 */
class ArticleValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'title'               => 'require|length:2,100',
        'article_category_id' => 'require|number|gt:0',
        'author'              => 'length:0,30',
        'source'              => 'length:0,50',
        'keywords'            => 'length:0,100',
        'description'         => 'length:0,255',
        'cover'               => 'require|length:1,255',
        'content_type'        => 'in:' . ContentTypeEnum::CONTENT_TYPE_STR,
        'media_url'           => 'length:0,255',
        'media_duration'      => 'number',
        'trial_media_url'     => 'length:0,255',
        'images'              => 'array',
        'content'             => 'checkContentType',
        'virtual_learn_count' => 'number|egt:0',
        'gift_points'         => 'checkGiftPoints',
        'sort'                => 'number',
        'is_hot'              => 'in:0,1',
        'is_banner'           => 'in:0,1',
        'status'              => 'in:0,1',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'title.require'               => '文章标题不能为空',
        'title.length'                => '文章标题长度为2-100个字符',
        'article_category_id.require' => '请选择内容分类',
        'article_category_id.number'  => '分类ID格式错误',
        'article_category_id.gt'      => '请选择内容分类',
        'author.length'               => '作者长度不能超过30个字符',
        'source.length'               => '来源长度不能超过50个字符',
        'keywords.length'             => '关键词长度不能超过100个字符',
        'description.length'          => '描述长度不能超过255个字符',
        'cover.require'               => '封面图不能为空',
        'cover.length'                => '封面图片长度不能超过255个字符',
        'content.require'             => '文章内容不能为空',
        'virtual_learn_count.number'  => '虚拟学习人数必须为数字',
        'virtual_learn_count.egt'     => '虚拟学习人数不能小于0',
        'sort.number'                 => '排序必须为数字',
        'is_hot.in'                   => '是否热门值错误',
        'is_banner.in'                => '是否轮播图值错误',
        'status.in'                   => '状态值错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['title', 'article_category_id', 'author', 'source', 'keywords', 'description', 'cover', 'content_type', 'content', 'virtual_learn_count', 'gift_points', 'sort', 'is_hot', 'is_banner', 'status'],
        'edit' => ['title', 'article_category_id', 'author', 'source', 'keywords', 'description', 'cover', 'content_type', 'content', 'virtual_learn_count', 'gift_points', 'sort', 'is_hot', 'is_banner', 'status'],
    ];

    /**
     * 检测内容类型对应的字段
     * @param mixed $value 当前字段值
     * @param mixed $rule 规则
     * @param array $data 全部数据
     * @return string|true
     */
    protected function checkContentType(mixed $value, mixed $rule, array $data = []): string|bool
    {
        $contentType = (int)($data['content_type'] ?? ContentTypeEnum::GRAPHIC);

        // 图文/文字/小说 - 需要富文本内容
        if (ContentTypeEnum::needsContent($contentType)) {
            if (empty($value)) {
                return '文章内容不能为空';
            }
        }

        // 音频/视频 - 需要媒体文件
        if (ContentTypeEnum::needsMedia($contentType)) {
            if (empty($data['media_url'])) {
                return '媒体地址不能为空';
            }
            // 如果开启了试听/试看
            if (!empty($data['is_trial']) && empty($data['trial_media_url'])) {
                return '试听地址不能为空';
            }
        }

        // 图片类型 - 需要图片数组
        if (ContentTypeEnum::needsImages($contentType)) {
            if (empty($data['images'])) {
                return '图片不能为空';
            }
        }

        return true;
    }

    /**
     * 检测赠送积分为非负整数。
     */
    protected function checkGiftPoints(mixed $value, mixed $rule, array $data = []): string|bool
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (!preg_match('/^\d+$/', (string)$value)) {
            return '赠送积分必须为非负整数';
        }

        return true;
    }
}
