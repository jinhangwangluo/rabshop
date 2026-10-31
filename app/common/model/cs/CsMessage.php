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

namespace app\common\model\cs;

use app\common\model\Base;

/**
 * 聊天消息模型
 */
class CsMessage extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_message';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * JSON 字段
     */
    protected $json = ['extra'];

    /**
     * 字段类型转换
     */
    protected $type = [
        'extra' => 'array',
    ];

    /**
     * 发送者类型常量
     */
    const SENDER_TYPE_USER = 'user';     // 用户
    const SENDER_TYPE_STAFF = 'staff';   // 客服
    const SENDER_TYPE_SYSTEM = 'system'; // 系统
    const SENDER_TYPE_AI = 'ai';         // AI

    /**
     * 消息类型常量
     */
    const MESSAGE_TYPE_TEXT = 'text';     // 文本
    const MESSAGE_TYPE_IMAGE = 'image';   // 图片
    const MESSAGE_TYPE_FILE = 'file';     // 文件

    /**
     * 搜索器：会话ID
     */
    public function searchSessionIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('session_id', $value);
        }
    }

    /**
     * 搜索器：发送者类型
     */
    public function searchSenderTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('sender_type', $value);
        }
    }

    /**
     * 搜索器：发送者ID
     */
    public function searchSenderIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('sender_id', $value);
        }
    }

    /**
     * 搜索器：消息类型
     */
    public function searchMessageTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('message_type', $value);
        }
    }

    /**
     * 搜索器：是否已读
     */
    public function searchIsReadAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_read', $value);
        }
    }

    /**
     * 获取器：发送者类型文本
     */
    public function getSenderTypeTextAttr($value, $data)
    {
        $types = [
            self::SENDER_TYPE_USER => '用户',
            self::SENDER_TYPE_STAFF => '客服',
            self::SENDER_TYPE_SYSTEM => '系统',
            self::SENDER_TYPE_AI => 'AI',
        ];
        return $types[$data['sender_type'] ?? 'user'] ?? '未知';
    }

    /**
     * 关联：会话
     */
    public function session()
    {
        return $this->belongsTo(CsSession::class, 'session_id', 'session_id');
    }
}
