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

declare(strict_types=1);

namespace app\common\service\websocket;

use app\common\model\websocket\WebSocketMessageHistory;
use app\common\service\BaseService;

/**
 * WebSocket消息持久化服务
 */
class WebSocketMessageHistoryService extends BaseService
{
    /**
     * 设置对应的模型
     */
    protected function setModel(): string
    {
        return WebSocketMessageHistory::class;
    }

    /**
     * 保存重要消息到历史库
     * @param array $data
     * @return bool
     */
    public function saveMessage(array $data)
    {
        $this->getModel()->save([
            'push_id' => $data['push_id'] ?? '',
            'event_type' => $data['event_type'] ?? '',
            'user_id' => $data['user_id'] ?? 0,
            'room_id' => $data['room_id'] ?? 0,
            'message_content' => json_encode($data['message_content'] ?? [], JSON_UNESCAPED_UNICODE),
            'expire_time' => isset($data['expire_time'])
                ? date('Y-m-d H:i:s', $data['expire_time'])
                : null,
        ]);
    }

    /**
     * 标记为已读
     * @param int $id
     * @return bool
     */
    public function markAsRead(int $id)
    {
        $message = $this->getModel()->find($id);
        if (!$message) {
            return false;
        }

        $message->read_status = true;
        $message->read_time = date('Y-m-d H:i:s');
        $message->save();
    }

    /**
     * 批量标记为已读
     * @param int $userId
     * @param string|null $eventType
     * @return int 影响的行数
     */
    public function batchMarkAsRead(int $userId, ?string $eventType = null)
    {

        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('read_status', false);

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $query->update([
            'read_status' => true,
            'read_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 获取用户未读消息
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserUnreadMessages(int $userId, int $limit = 50): array
    {
        return $this->getModel()
            ->where('user_id', $userId)
            ->where('read_status', false)
            ->whereTime('expire_time', '>', date('Y-m-d H:i:s'))
            ->order('create_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

    }

    /**
     * 清理过期消息
     * @return int 删除的消息数
     */
    public function cleanupExpiredMessages()
    {
        $this->getModel()
            ->whereNotNull('expire_time')
            ->where('expire_time', '<', date('Y-m-d H:i:s'))
            ->delete();
    }
}
