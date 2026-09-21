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

namespace Rabbit\service\websocket;

use app\common\enum\ClientTypeEnum;
use app\common\model\cs\CsMessage;
use app\common\service\cs\CsMessageService;
use app\common\service\cs\CsSessionService;
use app\common\service\cs\CsStaffService;
use app\common\service\websocket\WebSocketRateLimitService;
use app\common\service\websocket\WebSocketPushStatsService;
use app\common\service\websocket\WebSocketMessageHistoryService;
use think\facade\Log;

/**
 * WebSocket 消息推送助手（增强版）
 * 
 * 功能特性：
 * - ✅ 性能监控（成功率、延迟统计）
 * - ✅ 消息持久化（重要通知入库）
 * - ✅ 限流保护（防止拥塞）
 * - ✅ 配置化管理（config/websocket.php）
 */
class PushNotification
{
    /**
     * @var WebSocketService|null
     */
    protected static $webSocketService = null;
    
    /**
     * @var WebSocketRateLimitService|null
     */
    protected static $rateLimitService = null;
    
    /**
     * @var WebSocketPushStatsService|null
     */
    protected static $statsService = null;
    
    /**
     * @var WebSocketMessageHistoryService|null
     */
    protected static $historyService = null;

    /**
     * 获取 WebSocketService 实例
     */
    protected static function getWebSocketService(): WebSocketService
    {
        if (self::$webSocketService === null) {
            self::$webSocketService = app()->make(WebSocketService::class);
        }
        return self::$webSocketService;
    }
    
    /**
     * 获取限流服务实例
     */
    protected static function getRateLimitService(): WebSocketRateLimitService
    {
        if (self::$rateLimitService === null) {
            self::$rateLimitService = app()->make(WebSocketRateLimitService::class);
        }
        return self::$rateLimitService;
    }
    
    /**
     * 获取统计服务实例
     */
    protected static function getStatsService(): WebSocketPushStatsService
    {
        if (self::$statsService === null) {
            self::$statsService = app()->make(WebSocketPushStatsService::class);
        }
        return self::$statsService;
    }
    
    /**
     * 获取历史消息服务实例
     */
    protected static function getHistoryService(): WebSocketMessageHistoryService
    {
        if (self::$historyService === null) {
            self::$historyService = app()->make(WebSocketMessageHistoryService::class);
        }
        return self::$historyService;
    }

    /**
     * 构建统一消息格式
     */
    protected static function buildMessage(
        string $event,
        array $data,
        int $code = 200,
        string $message = ''
    ): array {
        return [
            'type'      => 'response',
            'event'     => $event,
            'code'      => $code,
            'data'      => $data,
            'message'   => $message ?: ($code === 200 ? 'success' : 'error'),
            'timestamp' => time()
        ];
    }

    /**
     * 直播间升级广播（带监控、限流、持久化）
     * @param array $upgradeInfo [user_id, nickname, avatar, live_room_id, old_level, new_level, level_name, level_icon, enter_effect, upgrade_points]
     */
    public static function broadcastLevelUp(array $upgradeInfo): void
    {
        $roomId = $upgradeInfo['live_room_id'] ?? 0;
        if (!$roomId) {
            return;
        }
        
        $userId = $upgradeInfo['user_id'] ?? 0;
        $eventType = 'level_upgrade';
        
        // 1. 限流检查
        $rateLimitResult = self::getRateLimitService()->check($eventType, $roomId, $userId);
        if (!$rateLimitResult['allowed']) {
            Log::warning("[WebSocket 限流] {$eventType} - 房间：{$roomId}, 用户：{$userId}");
            return;
        }
        
        // 2. 构建消息
        $message = self::buildMessage($eventType, [
            'type'           => $eventType,
            'user_id'        => $userId,
            'nickname'       => $upgradeInfo['nickname'] ?? '',
            'avatar'         => $upgradeInfo['avatar'] ?? '',
            'live_room_id'   => $roomId,
            'old_level'      => $upgradeInfo['old_level'] ?? 0,
            'new_level'      => $upgradeInfo['new_level'] ?? 0,
            'level_name'     => $upgradeInfo['level_name'] ?? '',
            'level_icon'     => $upgradeInfo['level_icon'] ?? '',
            'enter_effect'   => $upgradeInfo['enter_effect'] ?? 0,
            'upgrade_points' => $upgradeInfo['upgrade_points'] ?? 0
        ]);
        
        // 3. 记录推送开始时间
        $startTime = microtime(true);
        $pushId = uniqid('PUSH_', true);
        $recipientCount = count(ConnectionManager::getRoomFds($roomId));
        
        try {
            // 4. 发送消息
            $successCount = 0;
            $failedCount = 0;
            self::getWebSocketService()->sendToRoom($roomId, $message);
            $successCount = $recipientCount > 0 ? $recipientCount : 1;
            
            // 5. 计算延迟
            $latencyMs = round((microtime(true) - $startTime) * 1000);
            
            // 6. 记录统计
            self::recordPushStats([
                'push_id'        => $pushId,
                'event_type'     => $eventType,
                'target_type'    => 'room',
                'target_id'      => $roomId,
                'message_data'   => $message,
                'recipient_count'=> $recipientCount,
                'success_count'  => $successCount,
                'failed_count'   => $failedCount,
                'avg_latency_ms' => $latencyMs,
                'is_important'   => true, // 升级消息为重要消息
            ]);
            
            // 7. 消息持久化
            self::persistMessage([
                'push_id'         => $pushId,
                'event_type'      => $eventType,
                'room_id'         => $roomId,
                'message_content' => $message,
            ]);
            
            // 8. 记录限流统计
            self::getRateLimitService()->recordPushResult($eventType, $successCount > 0, $latencyMs);
            
        } catch (\Throwable $e) {
            Log::error("[WebSocket推送失败] {$eventType}: " . $e->getMessage());
        }
    }

    /**
     * 直播间贡献榜更新广播（带监控、限流、持久化）
     * @param int $roomId
     * @param array $rankList [{rank, user_id, nickname, avatar, level, level_icon, contribution}]
     */
    public static function broadcastRankUpdate(int $roomId, array $rankList): void
    {
        $eventType = 'room_rank_update';
        
        // 1. 限流检查
        $rateLimitResult = self::getRateLimitService()->check($eventType, $roomId, null);
        if (!$rateLimitResult['allowed']) {
            Log::warning("[WebSocket 限流] {$eventType} - 房间：{$roomId}");
            return;
        }
        
        // 2. 构建消息
        $message = self::buildMessage($eventType, [
            'type'         => $eventType,
            'live_room_id' => $roomId,
            'rank_list'    => $rankList
        ]);
        
        // 3. 记录推送开始时间
        $startTime = microtime(true);
        $pushId = uniqid('PUSH_', true);
        $recipientCount = count(ConnectionManager::getRoomFds($roomId));
        
        try {
            // 4. 发送消息
            $successCount = 0;
            $failedCount = 0;
            self::getWebSocketService()->sendToRoom($roomId, $message);
            $successCount = $recipientCount > 0 ? $recipientCount : 1;
            
            // 5. 计算延迟
            $latencyMs = round((microtime(true) - $startTime) * 1000);
            
            // 6. 记录统计
            self::recordPushStats([
                'push_id'        => $pushId,
                'event_type'     => $eventType,
                'target_type'    => 'room',
                'target_id'      => $roomId,
                'message_data'   => $message,
                'recipient_count'=> $recipientCount,
                'success_count'  => $successCount,
                'failed_count'   => $failedCount,
                'avg_latency_ms' => $latencyMs,
                'is_important'   => false,
            ]);
            
            // 7. 记录限流统计
            self::getRateLimitService()->recordPushResult($eventType, $successCount > 0, $latencyMs);
            
        } catch (\Throwable $e) {
            Log::error("[WebSocket推送失败] {$eventType}: " . $e->getMessage());
        }
    }

    /**
     * 中控台操作通知
     * @param int $roomId
     * @param string $action 操作类型：start_live/end_live/mute/kick/toggle_hot 等
     * @param array $data 附加数据
     */
    public static function notifyConsoleAction(int $roomId, string $action, array $data = []): void
    {
        $message = self::buildMessage('console_action', [
            'type'          => 'console_action',
            'live_room_id'  => $roomId,
            'action'        => $action,
            'action_data'   => $data
        ]);

        // 发送给直播间内的所有用户
        self::getWebSocketService()->sendToRoom($roomId, $message);
    }

    /**
     * 直播间商品变更通知（上下架/库存/信息更新）
     * 广播给直播间内所有连接用户，前端据此实时刷新商品展示
     * @param int    $roomId      直播间 ID
     * @param string $changeType  变更类型：shelf=上下架，stock=库存变更，info=信息更新
     * @param array  $goodsData   商品变更数据，来自 LiveGoodsService 各方法返回值
     */
    public static function notifyGoodsChange(int $roomId, string $changeType, array $goodsData = []): void
    {
        $message = self::buildMessage('goods_change', [
            'type'         => 'goods_change',
            'live_room_id' => $roomId,
            'change_type'  => $changeType,
            'goods_data'   => $goodsData,
        ]);

        // 广播给直播间内所有用户
        self::getWebSocketService()->sendToRoom($roomId, $message);
    }

    /**
     * 发送直播间聊天消息
     * @param int $roomId
     * @param array $chatData [msg_id, user_id, nickname, avatar, content, msg_type, send_time]
     */
    public static function sendChatMessage(int $roomId, array $chatData): void
    {
        $message = self::buildMessage('live_chat', $chatData);

        // 发送给直播间内的所有用户
        self::getWebSocketService()->sendToRoom($roomId, $message);
    }

    /**
     * 直播间在线人数变更广播。
     */
    public static function broadcastOnlineCountUpdate(int $roomId, int $onlineCount): void
    {
        if ($roomId <= 0) {
            return;
        }

        $message = self::buildMessage('online_count_update', [
            'type' => 'online_count_update',
            'room_id' => $roomId,
            'live_room_id' => $roomId,
            'online_count' => max(0, $onlineCount),
        ]);

        self::getWebSocketService()->sendToRoom($roomId, $message);
    }

    /**
     * 直播间用户离开广播。
     */
    public static function broadcastRoomUserLeave(array $leaveData): void
    {
        $roomId = (int)($leaveData['room_id'] ?? $leaveData['live_room_id'] ?? 0);
        if ($roomId <= 0) {
            return;
        }

        $message = self::buildMessage('live_user_leave', [
            'type' => 'live_user_leave',
            'room_id' => $roomId,
            'live_room_id' => $roomId,
            'user_id' => (int)($leaveData['user_id'] ?? 0),
            'online_count' => max(0, (int)($leaveData['online_count'] ?? 0)),
            'timestamp' => (int)($leaveData['timestamp'] ?? time()),
        ]);

        self::getWebSocketService()->sendToRoom($roomId, $message);
    }

    /**
     * 直播间礼物消息广播。
     */
    public static function broadcastGiftMessage(int $roomId, array $giftData): void
    {
        if ($roomId <= 0) {
            return;
        }

        $message = self::buildMessage('gift_message', array_merge([
            'type' => 'gift_message',
            'room_id' => $roomId,
            'live_room_id' => $roomId,
        ], $giftData));

        self::getWebSocketService()->sendToRoom($roomId, $message);
    }

    /**
     * 直播间点赞增量广播。
     */
    public static function broadcastRoomLike(int $roomId, array $likeData): void
    {
        if ($roomId <= 0) {
            return;
        }

        $message = self::buildMessage('live_like', array_merge([
            'type' => 'live_like',
            'room_id' => $roomId,
            'live_room_id' => $roomId,
        ], $likeData));

        self::getWebSocketService()->sendToRoom($roomId, $message);
    }

    /**
     * 直播间用户进场广播（带限流与监控）
     * @param array $enterData [event_id, room_id, user_id, nickname, avatar, welcome_msg, display_text, enter_effect, name, icon, timestamp]
     */
    public static function broadcastRoomUserEnter(array $enterData): void
    {
        $roomId = (int)($enterData['room_id'] ?? $enterData['live_room_id'] ?? 0);
        if ($roomId <= 0) {
            return;
        }

        $userId = (int)($enterData['user_id'] ?? 0);
        $eventType = 'live_user_enter';
        $levelName = (string)($enterData['name'] ?? $enterData['level_name'] ?? '');
        $levelIcon = (string)($enterData['icon'] ?? $enterData['level_icon'] ?? '');
        $rateLimitResult = self::getRateLimitService()->check($eventType, $roomId, $userId);
        if (!$rateLimitResult['allowed']) {
            Log::warning("[WebSocket 限流] {$eventType} - 房间：{$roomId}, 用户：{$userId}");
            return;
        }

        $message = self::buildMessage($eventType, [
            'type'         => $eventType,
            'event_id'     => $enterData['event_id'] ?? uniqid('enter_', true),
            'room_id'      => $roomId,
            'live_room_id' => $roomId,
            'user_id'      => $userId,
            'nickname'     => $enterData['nickname'] ?? '',
            'avatar'       => $enterData['avatar'] ?? '',
            'welcome_msg'  => $enterData['welcome_msg'] ?? '',
            'display_text' => $enterData['display_text'] ?? '',
            'enter_effect' => (int)($enterData['enter_effect'] ?? 0),
            'name'         => $levelName,
            'icon'         => $levelIcon,
            'level_name'   => $levelName,
            'level_icon'   => $levelIcon,
            'timestamp'    => (int)($enterData['timestamp'] ?? time()),
        ]);

        $startTime = microtime(true);
        $pushId = uniqid('PUSH_', true);
        $recipientCount = count(ConnectionManager::getRoomFds($roomId));

        try {
            self::getWebSocketService()->sendToRoom($roomId, $message);
            $successCount = $recipientCount > 0 ? $recipientCount : 1;
            $latencyMs = round((microtime(true) - $startTime) * 1000);

            self::recordPushStats([
                'push_id'         => $pushId,
                'event_type'      => $eventType,
                'target_type'     => 'room',
                'target_id'       => $roomId,
                'message_data'    => $message,
                'recipient_count' => $recipientCount,
                'success_count'   => $successCount,
                'failed_count'    => 0,
                'avg_latency_ms'  => $latencyMs,
                'is_important'    => false,
            ]);

            self::getRateLimitService()->recordPushResult($eventType, $successCount > 0, $latencyMs);
        } catch (\Throwable $e) {
            Log::error("[WebSocket推送失败] {$eventType}: " . $e->getMessage());
        }
    }

    /**
     * 发送系统通知给指定用户
     * @param int $userId
     * @param string $content 通知内容
     * @param string $noticeType 通知类型：system/order/finance 等
     * @param array $extraData 扩展数据
     */
    public static function sendSystemNotice(int $userId, string $content, string $noticeType = 'system', array $extraData = []): void
    {
        $message = self::buildMessage('system_notice', [
            'type'        => 'system_notice',
            'notice_type' => $noticeType,
            'content'     => $content,
            'extra_data'  => $extraData
        ]);

        // 发送给用户的所有连接（排除直播间连接，避免打扰）
        self::getWebSocketService()->sendToUser($userId, $message, ClientTypeEnum::LIVE_ROOM);
    }
    
    /**
     * 记录推送统计信息
     * @param array $data
     */
    protected static function recordPushStats(array $data): void
    {
        try {
            // 如果未启用监控，直接返回
            if (!config('websocket.monitor.enabled', true)) {
                return;
            }
            
            self::getStatsService()->recordPush($data);
        } catch (\Throwable $e) {
            Log::error('[WebSocket 统计失败] ' . $e->getMessage());
        }
    }
    
    /**
     * 持久化重要消息
     * @param array $data
     */
    protected static function persistMessage(array $data): void
    {
        try {
            // 如果未启用持久化，直接返回
            if (!config('websocket.persistence.enabled', true)) {
                return;
            }
            
            // 检查是否为重要消息类型
            $importantEvents = config('websocket.persistence.important_events', []);
            if (!in_array($data['event_type'], $importantEvents)) {
                return;
            }
            
            // 设置过期时间
            $retentionDays = config('websocket.persistence.retention_days', 30);
            $data['expire_time'] = strtotime("+{$retentionDays} days");
            
            self::getHistoryService()->saveMessage($data);
        } catch (\Throwable $e) {
            Log::error('[WebSocket 持久化失败] ' . $e->getMessage());
        }
    }

    /**
     * 全服广播
     * @param string $content
     * @param int|null $clientType 可选，仅广播给指定客户端类型
     */
    public static function broadcastAll(string $content, ?int $clientType = null): void
    {
        $message = self::buildMessage('system_broadcast', [
            'type'    => 'system_broadcast',
            'content' => $content
        ]);

        if ($clientType !== null) {
            // 仅广播给指定客户端类型
            self::getWebSocketService()->sendToClientType($clientType, $message);
        } else {
            // 全服广播（需要谨慎使用）
            $allFds = ConnectionManager::getAllFds();
            foreach ($allFds as $fd) {
                self::getWebSocketService()->sendToFd($fd, $message);
            }
        }
    }

    /**
     * 发送订单通知给商户
     * @param int $merchantId
     * @param array $orderData 订单数据
     */
    public static function notifyMerchantOrder(int $merchantId, array $orderData): void
    {
        $message = self::buildMessage('merchant_order', [
            'type'       => 'merchant_order',
            'order_data' => $orderData
        ]);

        // 发送给商户端
        self::getWebSocketService()->sendToUser($merchantId, $message, null);
    }

    /**
     * 发送财务通知
     * @param int $userId
     * @param array $financeData 财务数据
     */
    public static function notifyFinance(int $userId, array $financeData): void
    {
        $message = self::buildMessage('finance_notice', [
            'type'          => 'finance_notice',
            'finance_data'  => $financeData
        ]);

        // 发送给用户
        self::getWebSocketService()->sendToUser($userId, $message, null);
    }

    /**
     * 发送后台消息通知。
     *
     * @param string $receiverType 接收端类型：platform/merchant。
     * @param int $receiverId 接收端ID，平台全体为0，商户端为merchant_id。
     * @param array $messageData 消息记录数据。
     * @param int $unreadCount 接收端最新未读数。
     */
    public static function notifyBackendMessage(
        string $receiverType,
        int $receiverId,
        array $messageData,
        int $unreadCount = 0
    ): void {
        $message = self::buildMessage('backend_message', [
            'type' => 'backend_message',
            'message' => $messageData,
            'unread_count' => $unreadCount,
        ]);

        if ($receiverType === 'platform') {
            if ($receiverId > 0) {
                self::sendToUserClientType($receiverId, ClientTypeEnum::PLATFORM, $message);
                return;
            }

            self::getWebSocketService()->sendToClientType(ClientTypeEnum::PLATFORM, $message);
            return;
        }

        if ($receiverType === 'merchant') {
            if ($receiverId > 0) {
                self::sendToUserClientType($receiverId, ClientTypeEnum::MERCHANT, $message);
                return;
            }

            self::getWebSocketService()->sendToClientType(ClientTypeEnum::MERCHANT, $message);
        }
    }

    /**
     * 发送给指定用户在指定客户端类型下的所有连接。
     *
     * @param int $userId 用户或主体 ID。
     * @param int $clientType 客户端类型。
     * @param array $message 消息载荷。
     */
    protected static function sendToUserClientType(int $userId, int $clientType, array $message): void
    {
        $fds = ConnectionManager::getUserFds($userId);
        foreach ($fds as $fd) {
            $connection = ConnectionManager::getConnection($fd);
            if ($connection && (int)$connection['client_type'] === $clientType) {
                self::getWebSocketService()->sendToFd($fd, $message);
            }
        }
    }

    /**
     * 中控台操作回执（多 tab 同步）
     * 操作成功后向执行操作的管理员的所有 fd 广播，确保多个中控台标签页状态一致
     *
     * @param int    $userId   管理员 user_id
     * @param string $action   操作类型（与 ConsoleActionHandler 中的 action 一致）
     * @param int    $roomId   直播间 ID
     * @param array  $extra    附加数据（可选）
     */
    public static function notifyConsoleAck(int $userId, string $action, int $roomId, array $extra = []): void
    {
        $message = self::buildMessage('console_ack', [
            'type'     => 'console_ack',
            'action'   => $action,
            'room_id'  => $roomId,
            'result'   => 'success',
            'extra'    => $extra,
        ]);

        // 向该管理员的所有 fd 推送（涵盖多个浏览器 tab）
        self::getWebSocketService()->sendToUser($userId, $message, null);
    }

    /**
     * 新连接状态同步（多 tab 新开时同步当前直播间状态）
     * 在 authenticate 成功后，若该用户已有同类型的连接，则向新 fd 推送当前直播间快照
     *
     * @param int   $fd      新连接的 fd
     * @param int   $roomId  直播间 ID（0 表示无需同步房间状态）
     * @param array $roomInfo 直播间快照数据（由 LiveRoomService::get 提供）
     */
    public static function notifyStateSync(int $fd, int $roomId, array $roomInfo): void
    {
        if ($roomId <= 0 || empty($roomInfo)) {
            return;
        }

        $message = self::buildMessage('state_sync', [
            'type'      => 'state_sync',
            'room_id'   => $roomId,
            'room_info' => $roomInfo,
        ]);

        self::getWebSocketService()->sendToFd($fd, $message);
    }

    /**
     * 强制下线通知
     * @param int $userId
     * @param int $clientType
     * @param string $reason 下线原因
     */
    public static function notifyForceLogout(int $userId, int $clientType, string $reason = ''): void
    {
        $message = self::buildMessage('force_logout', [
            'type'         => 'force_logout',
            'client_type'  => $clientType,
            'reason'       => $reason ?: '账号在其他设备登录'
        ], 401, '强制下线');

        // 发送给用户的所有连接
        $fds = ConnectionManager::getUserFds($userId);
        foreach ($fds as $fd) {
            self::getWebSocketService()->sendToFd($fd, $message);
        }
    }

    /**
     * 发送客服消息
     * @param string $sessionId 会话ID
     * @param array $messageData 消息数据
     */
    public static function sendCsMessage(string $sessionId, array $messageData): void
    {
        $messageData['session_id'] = $messageData['session_id'] ?? $sessionId;
        $message = self::buildMessage('cs_message', $messageData);
        foreach (self::getCsSessionParticipantFds($sessionId) as $fd) {
            self::getWebSocketService()->sendToFd($fd, $message);
        }
    }

    /**
     * 持久化并推送客服系统消息。
     *
     * @param string $sessionId 会话 ID
     * @param string $content 消息内容
     * @param array $extra 扩展信息
     * @return CsMessage
     */
    public static function sendCsSystemMessage(string $sessionId, string $content, array $extra = []): CsMessage
    {
        /** @var CsMessageService $messageService */
        $messageService = app()->make(CsMessageService::class);
        $message = $messageService->sendSystemMessage($sessionId, $content, $extra);

        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $sessionService->updateLastMessage($sessionId, $content);

        self::sendCsMessage($sessionId, [
            'id' => (int)$message->id,
            'sender_type' => CsMessage::SENDER_TYPE_SYSTEM,
            'sender_id' => 0,
            'content' => $content,
            'message_type' => CsMessage::MESSAGE_TYPE_TEXT,
            'send_time' => (string)$message->create_time,
            'msg_uuid' => (string)$message->msg_uuid,
            'extra' => $extra,
        ]);

        return $message;
    }

    /**
     * 获取客服会话已绑定客服管理员的连接 fd。
     *
     * @param string $sessionId 会话ID
     * @return array
     */
    protected static function getCsSessionStaffFds(string $sessionId): array
    {
        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        /** @var CsStaffService $staffService */
        $staffService = app()->make(CsStaffService::class);

        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session || (int)$session->staff_id <= 0) {
            return [];
        }

        $staff = $staffService->getStaffById((int)$session->staff_id);
        if (!$staff || (int)$staff->admin_id <= 0) {
            return [];
        }

        $merchantId = (int)$session->merchant_id;
        $candidateFds = $merchantId > 0
            ? ConnectionManager::getClientTypeFds(ClientTypeEnum::MERCHANT)
            : ConnectionManager::getUserFds((int)$staff->admin_id);

        return array_values(array_filter($candidateFds, static function ($fd) use ($merchantId, $staff): bool {
            $connection = ConnectionManager::getConnection($fd);
            if (!$connection) {
                return false;
            }

            if ($merchantId <= 0) {
                return (int)$connection['client_type'] === ClientTypeEnum::PLATFORM
                    && (int)$connection['user_id'] === (int)$staff->admin_id;
            }

            return (int)$connection['client_type'] === ClientTypeEnum::MERCHANT
                && (int)($connection['merchant_id'] ?? 0) === $merchantId
                && (int)($connection['admin_id'] ?? 0) === (int)$staff->admin_id;
        }));
    }

    /**
     * 获取会话用户的连接 fd。
     *
     * @param string $sessionId 会话 ID
     * @return array
     */
    protected static function getCsSessionUserFds(string $sessionId): array
    {
        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session || (int)$session->user_id <= 0) {
            return [];
        }

        return array_values(array_filter(
            ConnectionManager::getUserFds((int)$session->user_id),
            static function ($fd): bool {
                $connection = ConnectionManager::getConnection($fd);
                return $connection && (int)$connection['client_type'] === ClientTypeEnum::USER;
            }
        ));
    }

    /**
     * 获取会话全部参与方的连接 fd。
     *
     * @param string $sessionId 会话 ID
     * @return array
     */
    protected static function getCsSessionParticipantFds(string $sessionId): array
    {
        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session) {
            return [];
        }

        $fds = array_merge(
            ConnectionManager::getCsSessionFds($sessionId),
            self::getCsSessionUserFds($sessionId),
            self::getCsSessionStaffFds($sessionId)
        );

        $fds = array_unique(array_map('strval', $fds));
        return array_values(array_filter($fds, static function ($fd) use ($session): bool {
            $connection = ConnectionManager::getConnection($fd);
            if (!$connection) {
                return false;
            }

            $clientType = (int)$connection['client_type'];
            if ($clientType === ClientTypeEnum::USER) {
                return (int)$connection['user_id'] === (int)$session->user_id;
            }

            if ($clientType === ClientTypeEnum::PLATFORM) {
                return (int)$session->merchant_id === 0;
            }

            return $clientType === ClientTypeEnum::MERCHANT
                && (int)$session->merchant_id > 0
                && (int)($connection['merchant_id'] ?? 0) === (int)$session->merchant_id;
        }));
    }

    /**
     * 通知客服转接
     * @param string $sessionId 会话ID
     * @param int $fromStaffId 原客服ID
     * @param int $toStaffId 目标客服ID
     * @param string $remark 备注
     */
    public static function notifyCsTransfer(string $sessionId, int $fromStaffId, int $toStaffId, string $remark = ''): void
    {
        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session) {
            return;
        }

        $systemContent = $fromStaffId > 0
            ? '会话已转接到其他客服，请继续咨询。'
            : '已转接人工客服，请继续咨询。';
        self::sendCsSystemMessage($sessionId, $systemContent, [
            'event' => 'human_transfer',
            'status' => 'assigned',
            'from_staff_id' => $fromStaffId,
            'to_staff_id' => $toStaffId,
            'remark' => $remark,
        ]);

        self::sendCsTransferEvent($sessionId, $fromStaffId, $toStaffId, $remark);
        self::notifyCsSessionUpdate($sessionId);
    }

    /**
     * 通知用户已进入人工客服等待队列。
     *
     * @param string $sessionId 会话 ID
     * @param string $content 用户可见状态文案
     * @param string $reason 等待原因
     * @return void
     */
    public static function notifyCsHumanWaiting(
        string $sessionId,
        string $content = '已转人工处理中，请稍候客服接入。',
        string $reason = 'no_staff'
    ): void {
        self::sendCsSystemMessage($sessionId, $content, [
            'event' => 'human_transfer',
            'status' => 'waiting',
            'reason' => $reason,
        ]);
        self::sendCsTransferEvent($sessionId, 0, 0, $reason);
        self::notifyCsSessionUpdate($sessionId);
    }

    /**
     * 向会话参与方广播转人工状态事件。
     *
     * @param string $sessionId 会话 ID
     * @param int $fromStaffId 原客服 ID
     * @param int $toStaffId 目标客服 ID
     * @param string $remark 备注
     * @return void
     */
    protected static function sendCsTransferEvent(
        string $sessionId,
        int $fromStaffId,
        int $toStaffId,
        string $remark = ''
    ): void {
        $message = self::buildMessage('cs_transfer', [
            'session_id' => $sessionId,
            'from_staff_id' => $fromStaffId,
            'to_staff_id' => $toStaffId,
            'remark' => $remark,
            'transfer_time' => date('Y-m-d H:i:s'),
        ]);

        foreach (self::getCsSessionParticipantFds($sessionId) as $fd) {
            self::getWebSocketService()->sendToFd($fd, $message);
        }
    }

    /**
     * 通知结束会话
     * @param string $sessionId 会话ID
     */
    public static function notifyCsClose(string $sessionId): void
    {
        $message = self::buildMessage('cs_close', [
            'session_id' => $sessionId,
            'close_time' => date('Y-m-d H:i:s'),
        ]);

        $sessionFds = ConnectionManager::getCsSessionFds($sessionId);
        foreach ($sessionFds as $fd) {
            self::getWebSocketService()->sendToFd($fd, $message);
        }

        self::notifyCsSessionUpdate($sessionId);
    }

    /**
     * 推送客服会话摘要更新。
     * 仅推给会话已绑定客服对应的管理员；未绑定客服或客服未绑定管理员时不推送。
     *
     * @param string $sessionId 会话ID
     */
    public static function notifyCsSessionUpdate(string $sessionId): void
    {
        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        /** @var CsStaffService $staffService */
        $staffService = app()->make(CsStaffService::class);

        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session || (int)$session->staff_id <= 0) {
            return;
        }

        $staff = $staffService->getStaffById((int)$session->staff_id);
        if (!$staff || (int)$staff->admin_id <= 0) {
            return;
        }

        $message = self::buildMessage('cs_session_update', self::formatCsSessionSummary($session));
        self::getWebSocketService()->sendToUser((int)$staff->admin_id, $message, null);
    }

    /**
     * 格式化客服会话摘要。
     *
     * @param mixed $session 会话模型
     * @return array
     */
    protected static function formatCsSessionSummary($session): array
    {
        return [
            'id' => (int)($session->id ?? 0),
            'session_id' => (string)($session->session_id ?? ''),
            'user_id' => (int)($session->user_id ?? 0),
            'staff_id' => (int)($session->staff_id ?? 0),
            'status' => (int)($session->status ?? 0),
            'service_type' => (int)($session->service_type ?? 0),
            'unread_count' => (int)($session->unread_count ?? 0),
            'last_message' => (string)($session->last_message ?? ''),
            'last_message_time' => (string)($session->last_message_time ?? ''),
            'create_time' => (string)($session->create_time ?? ''),
            'update_time' => (string)($session->update_time ?? ''),
        ];
    }

    /**
     * 通知客服新会话
     * @param string $sessionId 会话ID
     * @param int $staffId 客服ID
     */
    public static function notifyCsNewSession(string $sessionId, int $staffId): void
    {
        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        /** @var CsStaffService $staffService */
        $staffService = app()->make(CsStaffService::class);

        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session) {
            return;
        }

        $message = self::buildMessage('cs_new_session', [
            'session_id' => $sessionId,
            'user_id' => $session->user_id,
            'staff_id' => $staffId,
            'unread_count' => $session->unread_count,
            'last_message' => $session->last_message,
            'last_message_time' => $session->last_message_time,
        ]);

        $staff = $staffService->getStaffById($staffId);
        if ($staff && $staff->admin_id) {
            self::getWebSocketService()->sendToUser((int)$staff->admin_id, $message, null);
        }

        self::notifyCsSessionUpdate($sessionId);
    }

    /**
     * 通知输入状态
     * @param string $sessionId 会话ID
     * @param int $userId 用户ID
     * @param bool $isTyping 是否正在输入
     */
    public static function notifyCsTyping(string $sessionId, int $userId, bool $isTyping): void
    {
        $message = self::buildMessage('cs_typing', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'is_typing' => $isTyping,
        ]);

        $sessionFds = ConnectionManager::getCsSessionFds($sessionId);
        foreach ($sessionFds as $fd) {
            $connection = ConnectionManager::getConnection($fd);
            if ($connection && in_array((int)$connection['client_type'], [ClientTypeEnum::PLATFORM, ClientTypeEnum::MERCHANT], true)) {
                self::getWebSocketService()->sendToFd($fd, $message);
            }
        }
    }
}
