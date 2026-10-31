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

namespace app\common\service\cs;

use app\common\model\cs\CsMessage;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 聊天消息服务
 */
class CsMessageService extends BaseService
{
    private const MAX_HISTORY_LIMIT = 50;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return CsMessage::class;
    }

    /**
     * 发送消息
     * @param string $sessionId 会话ID
     * @param string $senderType 发送者类型
     * @param int $senderId 发送者ID
     * @param string $content 消息内容
     * @param string $messageType 消息类型
     * @param array $extra 额外信息
     * @param string $msgUuid 消息唯一标识
     * @return mixed
     */
    public function sendMessage(
        string $sessionId,
        string $senderType,
        int $senderId,
        string $content,
        string $messageType = CsMessage::MESSAGE_TYPE_TEXT,
        array $extra = [],
        string $msgUuid = ''
    )
    {
        $uuid = $msgUuid !== '' ? $msgUuid : $this->generateMsgUuid();

        $data = [
            'session_id' => $sessionId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'content' => $content,
            'message_type' => $messageType,
            'extra' => $extra,
            'msg_uuid' => $uuid,
        ];

        return $this->getModel()->create($data);
    }

    /**
     * 发送持久化系统消息。
     *
     * @param string $sessionId 会话 ID
     * @param string $content 消息内容
     * @param array $extra 扩展信息
     * @return CsMessage
     */
    public function sendSystemMessage(string $sessionId, string $content, array $extra = []): CsMessage
    {
        return $this->sendMessage(
            $sessionId,
            CsMessage::SENDER_TYPE_SYSTEM,
            0,
            $content,
            CsMessage::MESSAGE_TYPE_TEXT,
            $extra
        );
    }

    /**
     * 根据消息 UUID 获取消息
     * @param string $msgUuid
     * @return CsMessage|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getByMsgUuid(string $msgUuid): ?CsMessage
    {
        return $this->getModel()->where('msg_uuid', $msgUuid)->find();
    }

    /**
     * 获取会话历史消息
     * @param string $sessionId 会话ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param int $afterId 只获取指定消息ID之后的消息
     * @param bool $latest 是否获取最近一页消息
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getHistory(
        string $sessionId,
        int $page = 1,
        int $limit = 50,
        int $afterId = 0,
        bool $latest = false
    ): array
    {
        $page = max(1, $page);
        $limit = max(1, min($limit, self::MAX_HISTORY_LIMIT));
        $afterId = max(0, $afterId);
        $total = $this->getModel()->where('session_id', $sessionId)->count();

        if ($afterId > 0) {
            $list = $this->getModel()
                ->where('session_id', $sessionId)
                ->where('id', '>', $afterId)
                ->order('id asc')
                ->limit($limit)
                ->select()
                ->toArray();

            return [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'after_id' => $afterId,
                'latest' => false,
            ];
        }

        if ($latest) {
            $list = $this->getModel()
                ->where('session_id', $sessionId)
                ->order('id desc')
                ->limit($limit)
                ->select()
                ->toArray();

            $list = array_reverse($list);

            return [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'after_id' => 0,
                'latest' => true,
            ];
        }

        $list = $this->getModel()
            ->where('session_id', $sessionId)
            ->order('create_time asc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'after_id' => 0,
            'latest' => false,
        ];
    }

    /**
     * 获取会话未读消息
     * @param string $sessionId 会话ID
     * @param string $senderType 发送者类型
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUnread(string $sessionId, string $senderType): array
    {
        return $this->getModel()
            ->where('session_id', $sessionId)
            ->where('sender_type', $senderType)
            ->where('is_read', 0)
            ->order('create_time asc')
            ->select()
            ->toArray();
    }

    /**
     * 标记消息已读
     * @param int $messageId 消息ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function markAsRead(int $messageId): bool
    {
        $message = $this->getModel()->find($messageId);
        if (!$message) {
            return false;
        }

        $message->is_read = 1;
        $message->read_time = date('Y-m-d H:i:s');

        return (bool)$message->save();
    }

    /**
     * 批量标记会话消息已读
     * @param string $sessionId 会话ID
     * @param string $senderType 发送者类型
     * @return bool
     */
    public function markSessionAsRead(string $sessionId, string $senderType): bool
    {
        return $this->getModel()
            ->where('session_id', $sessionId)
            ->where('sender_type', $senderType)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_time' => date('Y-m-d H:i:s'),
            ]) >= 0;
    }

    /**
     * 获取最后一条消息
     * @param string $sessionId 会话ID
     * @return CsMessage|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getLastMessage(string $sessionId): ?CsMessage
    {
        return $this->getModel()
            ->where('session_id', $sessionId)
            ->order('create_time desc')
            ->find();
    }

    /**
     * 检查消息是否重复
     * @param string $msgUuid 消息UUID
     * @return bool
     */
    public function isDuplicate(string $msgUuid): bool
    {
        if ($msgUuid === '') {
            return false;
        }

        return $this->getModel()->where('msg_uuid', $msgUuid)->count() > 0;
    }

    /**
     * 生成消息UUID
     * @return string
     */
    private function generateMsgUuid(): string
    {
        return date('YmdHis') . '_' . mt_rand(100000, 999999) . '_' . md5(uniqid((string)mt_rand(), true));
    }

}
