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

namespace app\common\model\wechat;

use app\common\model\Base;

/**
 * 微信回复关键词模型
 * Class WechatReplyKey
 * @package app\common\model\wechat
 */
class WechatReplyKey extends Base
{
    /**
     * @var string
     */
    protected $name = 'wechat_reply_key';

    /**
     * 主键字段名
     * @var string
     */
    protected $pk = 'id';

    /**
     * 匹配类型映射
     * @var array
     */
    protected $matchTypeMap = [
        'equal' => '完全匹配',
        'contain' => '包含匹配',
        'regexp' => '正则匹配'
    ];

    /**
     * 获取匹配类型文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getMatchTypeTextAttr($value, $data)
    {
        return $this->matchTypeMap[$data['match_type']] ?? $data['match_type'];
    }

    /**
     * 回复ID搜索器
     * @param $query
     * @param $value
     */
    public function searchReplyIdAttr($query, $value)
    {
        if ($value) {
            $query->where('reply_id', $value);
        }
    }

    /**
     * 关键词搜索器
     * @param $query
     * @param $value
     */
    public function searchKeywordAttr($query, $value)
    {
        if ($value) {
            $query->where('keyword', 'like', '%' . $value . '%');
        }
    }

    /**
     * 匹配类型搜索器
     * @param $query
     * @param $value
     */
    public function searchMatchTypeAttr($query, $value)
    {
        if ($value) {
            $query->where('match_type', $value);
        }
    }

    /**
     * 根据回复ID获取关键词列表（保留给service调用）
     * @param int $replyId
     * @return array
     */
    public static function getKeywordsByReplyId(int $replyId)
    {
        return self::where('reply_id', $replyId)
            ->order('sort desc,id desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取匹配类型选项（保留给service调用）
     * @return array
     */
    public static function getMatchTypes(): array
    {
        return [
            ['value' => 'equal', 'label' => '完全匹配'],
            ['value' => 'contain', 'label' => '包含匹配'],
            ['value' => 'regexp', 'label' => '正则匹配']
        ];
    }
}