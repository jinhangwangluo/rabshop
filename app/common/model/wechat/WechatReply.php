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
 * 微信回复模型
 * Class WechatReply
 * @package app\common\model\wechat
 */
class WechatReply extends Base
{
    /**
     * @var string
     */
    protected $name = 'wechat_reply';

    /**
     * 主键字段名
     * @var string
     */
    protected $pk = 'id';

    /**
     * 回复类型映射
     * @var array
     */
    public static $reply_type = [
        'text' => '文本消息',
        'image' => '图片消息',
        'news' => '图文消息',
        'voice' => '语音消息',
        'video' => '视频消息'
    ];

    /**
     * 获取回复类型文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getTypeTextAttr($value, $data)
    {
        return self::$reply_type[$data['type']] ?? $data['type'];
    }

    /**
     * 状态搜索器
     * @param $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 类型搜索器
     * @param $query
     * @param $value
     */
    public function searchTypeAttr($query, $value)
    {
        if ($value) {
            $query->where('type', $value);
        }
    }

    /**
     * 关键词搜索器
     * @param $query
     * @param $value
     */
    public function searchKeyAttr($query, $value)
    {
        if ($value) {
            $query->where('key', $value);
        }
    }

    /**
     * 根据关键词获取回复（保留给service调用）
     * @param string $keyword
     * @return array|null
     */
    public static function getReplyByKey(string $keyword)
    {
        // 先查找完全匹配
        $reply = self::alias('r')
            ->join('wechat_reply_key rk', 'r.id = rk.reply_id')
            ->where('rk.keyword', $keyword)
            ->where('rk.match_type', 'equal')
            ->where('r.status', 1)
            ->find();
            
        if ($reply) {
            return $reply->toArray();
        }
        
        // 查找包含匹配
        $replies = self::alias('r')
            ->join('wechat_reply_key rk', 'r.id = rk.reply_id')
            ->where('rk.match_type', 'contain')
            ->where('r.status', 1)
            ->select();
            
        foreach ($replies as $reply) {
            if (strpos($keyword, $reply['keyword']) !== false) {
                return $reply->toArray();
            }
        }
        
        // 查找正则匹配
        $replies = self::alias('r')
            ->join('wechat_reply_key rk', 'r.id = rk.reply_id')
            ->where('rk.match_type', 'regexp')
            ->where('r.status', 1)
            ->select();
            
        foreach ($replies as $reply) {
            if (preg_match('/' . $reply['keyword'] . '/', $keyword)) {
                return $reply->toArray();
            }
        }
        
        // 返回默认回复
        return self::where('key', 'default')->where('status', 1)->find();
    }

    /**
     * 获取关注回复（保留给service调用）
     * @return array|null
     */
    public static function getSubscribeReply()
    {
        return self::where('key', 'subscribe')->where('status', 1)->find();
    }
}