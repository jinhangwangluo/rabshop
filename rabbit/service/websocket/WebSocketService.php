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
use app\common\service\cs\CsSessionService;
use app\common\service\cs\CsStaffService;
use app\common\service\live\LiveRoomService;
use app\common\service\live\LiveRoomUserService;
use app\common\service\merchant\MerchantService;
use app\common\model\setting\system\Admin;
use app\common\service\setting\AdminService;
use app\common\service\user\UserService;
use Rabbit\service\websocket\handlers\MessageHandlerInterface;
use Rabbit\service\websocket\PushNotification;
use think\facade\Log;
use think\swoole\Websocket;

/**
 * WebSocket 核心服务类
 * 负责处理 WebSocket 连接、消息路由、消息推送等核心功能
 */
class WebSocketService
{
    /**
     * @var array 消息处理器类名注册表 [event => handler_class_name]
     */
    protected static array $handlerClasses = [];

    /**
     * @var bool 是否已初始化处理器
     */
    protected static bool $initialized = false;

    /**
     * 注册消息处理器类
     * @param string $event
     * @param string $handlerClass
     */
    public static function registerHandler(string $event, string $handlerClass): void
    {
        self::$handlerClasses[$event] = $handlerClass;
    }

    /**
     * 获取所有注册的处理器类名
     * @return array
     */
    public static function getHandlerClasses(): array
    {
        return self::$handlerClasses;
    }

    /**
     * 获取指定事件的处理器实例（延迟加载）
     * @param string $event
     * @return MessageHandlerInterface|null
     */
    protected static function getHandler(string $event): ?MessageHandlerInterface
    {
        $handlerClass = self::$handlerClasses[$event] ?? null;
        if (!$handlerClass) {
            return null;
        }

        // 每次都创建新实例，避免内存累积
        return app()->make($handlerClass);
    }

    /**
     * 初始化并注册所有消息处理器类名
     */
    public static function initializeHandlers(): void
    {
        // 如果已经初始化过，直接返回
        if (self::$initialized) {
            return;
        }

        // 注册所有处理器类名（不创建实例）
        $handlerClasses = [
            \Rabbit\service\websocket\handlers\HeartbeatHandler::class,
            \Rabbit\service\websocket\handlers\LiveChatHandler::class,
            \Rabbit\service\websocket\handlers\SystemBroadcastHandler::class,
            \Rabbit\service\websocket\handlers\ConsoleActionHandler::class,
            \Rabbit\service\websocket\handlers\ConsoleReplyHandler::class,
            \Rabbit\service\websocket\handlers\CustomerServiceHandler::class,
        ];

        foreach ($handlerClasses as $handlerClass) {
            $events = $handlerClass::getEvents();
            foreach ($events as $event) {
                self::registerHandler($event, $handlerClass);
            }
        }

        self::$initialized = true;
        Log::info('[WebSocket] 消息处理器初始化完成，已注册 ' . count(self::$handlerClasses) . ' 个事件处理器');
    }

    /**
     * 重置处理器注册表（用于测试或重启时清理）
     */
    public static function resetHandlers(): void
    {
        self::$handlerClasses = [];
        self::$initialized = false;

        // 强制垃圾回收（Swoole环境下的内存清理）
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * 获取内存使用情况（用于监控）
     * @return array
     */
    public static function getMemoryUsage(): array
    {
        return [
            'registered_handlers' => count(self::$handlerClasses),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'handler_classes' => array_keys(self::$handlerClasses)
        ];
    }

    /**
     * 验证 token 并建立连接映射
     * @param string|int $fd 连接描述符
     * @param string $token JWT token
     * @param string $ip 客户端 IP
     * @return bool 验证是否成功
     */
    public function authenticate(string|int $fd, string $token, string $ip): bool
    {
        try {
            $tokenData = $this->parseToken($token);
            if (!$tokenData) {
                return false;
            }

            $userId = (int)$tokenData['user_id'];
            $clientType = (int)$tokenData['client_type'];

            // Token 声明只负责承载签名数据，最终连接身份必须以当前数据库状态为准。
            $identity = $this->authenticateTokenIdentity($tokenData);
            if ($identity === null) {
                return false;
            }

            $userId = $identity['user_id'];
            $clientType = $identity['client_type'];

            // 直播间类型必须有 room_id
            $roomId = 0;
            $hasExistingLiveRoomConnection = false;
            if ($clientType === ClientTypeEnum::LIVE_ROOM) {
                $roomId = (int)($tokenData['room_id'] ?? 0);
                if ($roomId <= 0) {
                    return false;
                }

                $hasExistingLiveRoomConnection = $this->hasActiveLiveRoomConnection($userId, $roomId, $fd);
            }

            // 添加到连接管理器（Redis 存储）
            ConnectionManager::addConnection($fd, [
                'user_id'     => $identity['user_id'],
                'client_type' => $identity['client_type'],
                'room_id'     => $roomId,
                'merchant_id' => $identity['merchant_id'],
                'admin_id'    => $identity['admin_id'],
                'token'       => $token,
                'ip'          => $ip,
                'scopes'      => $tokenData['scopes'] ?? [],
            ]);

            // 多 tab 状态同步：若该用户已有同类型的其他连接，向新 fd 推送当前快照
            $this->syncStateToNewConnection($fd, $userId, $clientType, $roomId);

            // 直播间用户进入：维护在线状态到数据库，并在首个连接建立时触发进场广播
            if ($clientType === ClientTypeEnum::LIVE_ROOM && $roomId > 0) {
                try {
                    app()->make(LiveRoomUserService::class)->userEnter($roomId, $userId);
                    app()->make(LiveRoomService::class)->clearMobileRoomCaches($roomId);
                    PushNotification::broadcastOnlineCountUpdate(
                        $roomId,
                        app()->make(LiveRoomUserService::class)->getOnlineCount($roomId)
                    );
                } catch (\Throwable $e) {
                    Log::warning("[WebSocket] userEnter 失败：" . $e->getMessage());
                }

                if (!$hasExistingLiveRoomConnection) {
                    try {
                        event('live.room.userEnter', [
                            'live_room_id' => $roomId,
                            'user_id'      => $userId,
                            'fd'           => $fd,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("[WebSocket] live.room.userEnter 失败：" . $e->getMessage());
                    }
                }
            }

            Log::info("[WebSocket] 用户 {$userId}({$clientType}) 连接成功，fd={$fd}");

            return true;
        } catch (\Throwable $e) {
            Log::error("[WebSocket] 认证失败：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 解析 Token（委托给 TokenHelper，包含过期检查）
     * @param string $token
     * @return array|null ['user_id', 'client_type', 'room_id'?]
     */
    protected function parseToken(string $token): ?array
    {
        return TokenHelper::parseToken($token);
    }

    /**
     * 立即拒绝未认证的 WebSocket 连接。
     *
     * 认证失败的 FD 尚未进入 ConnectionManager，错误响应必须直接写入当前 Swoole sender，
     * 随后关闭连接，避免框架继续为未认证客户端维持 recv 协程。
     *
     * @param string|int $fd 连接描述符
     * @param string $code 错误码
     * @param string $message 错误消息
     * @return void
     */
    public function rejectConnection(string|int $fd, string $code, string $message): void
    {
        $payload = json_encode([
            'type' => 'error',
            'code' => $code,
            'message' => $message,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            app()->make(\think\swoole\Websocket::class)->push($payload);
        } catch (\Throwable $exception) {
            Log::warning('[WebSocket] 认证错误响应发送失败：' . $exception->getMessage());
        }

        try {
            app()->make(\think\swoole\Websocket::class)->close();
        } catch (\Throwable $exception) {
            Log::warning('[WebSocket] 认证失败连接关闭失败：' . $exception->getMessage());
        }

        unset($fd);
    }

    /**
     * 根据签名 Token 的客户端类型校验当前数据库身份。
     *
     * @param array $tokenData 已验证签名和生命周期的 Token 声明
     * @return array|null 可写入连接映射的服务端确认身份
     */
    protected function authenticateTokenIdentity(array $tokenData): ?array
    {
        $userId = (int)($tokenData['user_id'] ?? 0);
        $clientType = (int)($tokenData['client_type'] ?? 0);
        if ($userId <= 0 || !ClientTypeEnum::isValid($clientType)) {
            return null;
        }

        if ($clientType === ClientTypeEnum::PLATFORM) {
            $admin = app()->make(AdminService::class)->get(
                [
                    'id' => $userId,
                    'platform_type' => Admin::PLATFORM_TYPE_PLATFORM,
                    'status' => 1,
                ],
                ['id']
            );
            if (!$admin) {
                return null;
            }

            return [
                'user_id' => $userId,
                'client_type' => $clientType,
                'merchant_id' => 0,
                'admin_id' => $userId,
            ];
        }

        if ($clientType === ClientTypeEnum::MERCHANT) {
            $merchantId = (int)($tokenData['merchant_id'] ?? 0);
            $adminId = (int)($tokenData['admin_id'] ?? 0);
            if ($merchantId <= 0 || $adminId <= 0 || $userId !== $merchantId) {
                return null;
            }

            $admin = app()->make(AdminService::class)->get(
                [
                    'id' => $adminId,
                    'merchant_id' => $merchantId,
                    'platform_type' => Admin::PLATFORM_TYPE_MERCHANT,
                    'status' => 1,
                ],
                ['id']
            );
            $merchant = app()->make(MerchantService::class)->get(
                [
                    'id' => $merchantId,
                    'status' => 1,
                    'audit_status' => 1,
                ],
                ['id']
            );
            if (!$admin || !$merchant) {
                return null;
            }

            return [
                'user_id' => $merchantId,
                'client_type' => $clientType,
                'merchant_id' => $merchantId,
                'admin_id' => $adminId,
            ];
        }

        $user = app()->make(UserService::class)->get(['uid' => $userId, 'status' => 1], ['uid']);
        if (!$user) {
            return null;
        }

        return [
            'user_id' => $userId,
            'client_type' => $clientType,
            'merchant_id' => 0,
            'admin_id' => 0,
        ];
    }

    /**
     * 处理 WebSocket 消息
     * @param string|int $fd 连接描述符
     * @param string $message JSON 消息
     */
    public function handleMessage(string|int $fd, string $message): void
    {
        // 确保处理器已初始化
        self::initializeHandlers();

        $data = json_decode($message, true);
        if (!is_array($data)) {
            $this->sendError($fd, 'invalid_json', '消息格式错误');
            return;
        }

        // 每条消息重新确认撤销版本和数据库身份，保证登出/禁用后旧连接不能继续执行操作。
        if ($this->getAuthorizedConnection($fd) === null) {
            return;
        }

        // 更新活跃时间
        ConnectionManager::updateLastActiveTime($fd);

        // 获取事件类型
        $event = $data['event'] ?? '';
        if (!$event) {
            $this->sendError($fd, 'missing_event', '缺少 event 字段');
            return;
        }

        // 获取处理器实例（延迟加载，每次创建新实例）
        $handler = self::getHandler($event);
        if (!$handler) {
            $this->sendError($fd, 'unknown_event', "未知的事件：{$event}");
            return;
        }

        try {
            /** @var MessageHandlerInterface $handler */
            $handler->handle($fd, $data);
        } catch (\Throwable $e) {
            Log::error("[WebSocket] 消息处理失败：{$event} - " . $e->getMessage());
            $this->sendError($fd, 'handler_error', $e->getMessage());
        }
    }

    /**
     * 发送消息给指定 FD
     * @param string|int $fd
     * @param array $data 消息数据（会自动转为 JSON）
     * @return bool
     */
    public function sendToFd(string|int $fd, array $data): bool
    {
        // 推送前检查撤销版本，防止登出后的旧连接继续收到敏感通知。
        $connection = ConnectionManager::getConnection($fd);
        if (!$connection) {
            return false;
        }
        if (!$this->parseToken((string)($connection['token'] ?? ''))) {
            ConnectionManager::removeConnection($fd);
            return false;
        }

        $message = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 通过 ThinkSwoole 房间推送跨 worker 发送。
        try {
            app()->make(Websocket::class)->to((string)$fd)->push($message);
            return true;
        } catch (\Throwable) {
            // Swoole 环境不可用，降级为日志记录
        }

        // 非 Swoole 环境：记录日志（测试/开发用）
        try {
            Log::channel('websocket')->info("[WS SEND fd={$fd}] {$message}");
        } catch (\Throwable) {
            Log::info("[WS SEND fd={$fd}] {$message}");
        }
        return true;
    }

    /**
     * 获取仍然具备有效身份的连接。
     *
     * @param string|int $fd 连接描述符
     * @return array|null 连接信息，无效时返回 null 并清理索引
     */
    protected function getAuthorizedConnection(string|int $fd): ?array
    {
        $connection = ConnectionManager::getConnection($fd);
        if (!$connection) {
            return null;
        }

        $tokenData = $this->parseToken((string)($connection['token'] ?? ''));
        if (!$tokenData) {
            ConnectionManager::removeConnection($fd);
            return null;
        }

        $identity = $this->authenticateTokenIdentity($tokenData);
        if ($identity === null || (int)$identity['user_id'] !== (int)$connection['user_id']
            || (int)$identity['client_type'] !== (int)$connection['client_type']) {
            ConnectionManager::removeConnection($fd);
            return null;
        }

        return $connection;
    }

    /**
     * 发送给指定用户的所有连接
     * @param int $userId
     * @param array $data
     * @param int|null $excludeClientType 排除的客户端类型（可选）
     */
    public function sendToUser(int $userId, array $data, ?int $excludeClientType = null): void
    {
        $fds = ConnectionManager::getUserFds($userId);
        foreach ($fds as $fd) {
            $conn = ConnectionManager::getConnection($fd);
            if ($conn && ($excludeClientType === null || $conn['client_type'] !== $excludeClientType)) {
                $this->sendToFd($fd, $data);
            }
        }
    }

    /**
     * 发送给直播间内的所有用户
     * @param int $roomId
     * @param array $data
     */
    public function sendToRoom(int $roomId, array $data): void
    {
        $fds = ConnectionManager::getRoomFds($roomId);
        foreach ($fds as $fd) {
            $this->sendToFd($fd, $data);
        }
    }

    /**
     * 发送给指定客户端类型的所有连接
     * @param int $clientType
     * @param array $data
     */
    public function sendToClientType(int $clientType, array $data): void
    {
        $fds = ConnectionManager::getClientTypeFds($clientType);
        foreach ($fds as $fd) {
            $this->sendToFd($fd, $data);
        }
    }

    /**
     * 发送错误消息
     * @param string|int $fd
     * @param string $code
     * @param string $message
     */
    public function sendError(string|int $fd, string $code, string $message): void
    {
        $this->sendToFd($fd, [
            'type'      => 'error',
            'code'      => $code,
            'message'   => $message,
            'timestamp' => time()
        ]);
    }

    /**
     * 关闭连接
     * @param string|int $fd
     */
    public function closeConnection(string|int $fd): void
    {
        $connection = ConnectionManager::getConnection($fd);
        if ($connection) {
            Log::info("[WebSocket] 用户 {$connection['user_id']}({$connection['client_type']}) 断开连接，fd={$fd}");

            // 客服端离线回退进行中会话到待接入
            $this->handleCustomerServiceDisconnect($fd, $connection);

            // 直播间用户离开：维护在线状态到数据库
            if (
                (int)$connection['client_type'] === ClientTypeEnum::LIVE_ROOM
                && (int)$connection['room_id'] > 0
            ) {
                try {
                    $roomId = (int)$connection['room_id'];
                    $userId = (int)$connection['user_id'];
                    if (!$this->hasActiveLiveRoomConnection($userId, $roomId, $fd)) {
                        $roomUserService = app()->make(LiveRoomUserService::class);
                        $roomUserService->userLeave($roomId, $userId);
                        app()->make(LiveRoomService::class)->clearMobileRoomCaches($roomId);
                        $onlineCount = $roomUserService->getOnlineCount($roomId);
                        PushNotification::broadcastRoomUserLeave([
                            'room_id' => $roomId,
                            'user_id' => $userId,
                            'online_count' => $onlineCount,
                            'timestamp' => time(),
                        ]);
                        PushNotification::broadcastOnlineCountUpdate($roomId, $onlineCount);
                    }
                } catch (\Throwable $e) {
                    Log::warning("[WebSocket] userLeave 失败：" . $e->getMessage());
                }
            }

            ConnectionManager::removeConnection($fd);
        }
    }

    /**
     * 处理客服端连接断开
     * @param string|int $fd
     * @param array $connection
     */
    protected function handleCustomerServiceDisconnect(string|int $fd, array $connection): void
    {
        $clientType = (int)($connection['client_type'] ?? 0);
        if (!in_array($clientType, [ClientTypeEnum::PLATFORM, ClientTypeEnum::MERCHANT], true)) {
            return;
        }

        $userId = (int)($connection['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $adminId = $clientType === ClientTypeEnum::MERCHANT
            ? (int)($connection['admin_id'] ?? 0)
            : $userId;
        $merchantId = $clientType === ClientTypeEnum::MERCHANT
            ? (int)($connection['merchant_id'] ?? 0)
            : 0;
        if ($adminId <= 0 || ($clientType === ClientTypeEnum::MERCHANT && $merchantId <= 0)) {
            return;
        }

        // 同一管理员若仍有相同客户端类型连接在线，不做回退
        $userFds = ConnectionManager::getUserFds($userId);
        foreach ($userFds as $userFd) {
            if ((string)$userFd === (string)$fd) {
                continue;
            }

            $otherConnection = ConnectionManager::getConnection($userFd);
            if (!$otherConnection || (int)$otherConnection['client_type'] !== $clientType) {
                continue;
            }
            $otherAdminId = $clientType === ClientTypeEnum::MERCHANT
                ? (int)($otherConnection['admin_id'] ?? 0)
                : (int)($otherConnection['user_id'] ?? 0);
            if ($otherAdminId === $adminId) {
                return;
            }
        }

        try {
            /** @var CsStaffService $staffService */
            $staffService = app()->make(CsStaffService::class);
            $staff = $staffService->getStaffByAdminId($adminId, $merchantId);
            if (!$staff) {
                return;
            }

            $staffService->updateOnlineStatus((int)$staff->id, 0);

            /** @var CsSessionService $sessionService */
            $sessionService = app()->make(CsSessionService::class);
            $sessionIds = $sessionService->setServingSessionsWaitingByStaff((int)$staff->id);

            foreach ($sessionIds as $sessionId) {
                PushNotification::notifyCsHumanWaiting(
                    (string)$sessionId,
                    '当前客服已离线，已为您转为待接入，请稍候。',
                    'staff_offline'
                );
            }

            if ($sessionIds !== []) {
                Log::info("[WebSocket] 客服离线回退会话成功，staff_id={$staff->id}, sessions=" . count($sessionIds));
            }
        } catch (\Throwable $exception) {
            Log::warning('[WebSocket] 客服离线回退失败: ' . $exception->getMessage());
        }
    }

    /**
     * 心跳检测：清理超时连接并更新用户在线状态
     * 由 Swoole Timer 定时调用
     */
    public function heartbeatCheck(): void
    {
        $removed = ConnectionManager::cleanupExpiredConnections();

        if (empty($removed)) {
            return;
        }

        $liveRoomUserService = null;
        $liveRoomDirty = [];

        foreach ($removed as $conn) {
            $clientType = (int)($conn['client_type'] ?? 0);
            $roomId = (int)($conn['room_id'] ?? 0);
            $userId = (int)($conn['user_id'] ?? 0);

            // 直播间用户：标记离线需更新
            if ($clientType === ClientTypeEnum::LIVE_ROOM && $roomId > 0 && $userId > 0) {
                $liveRoomDirty[] = ['room_id' => $roomId, 'user_id' => $userId];
            }
        }

        // 批量更新直播间用户离线状态
        if (!empty($liveRoomDirty)) {
            try {
                $liveRoomUserService = app()->make(LiveRoomUserService::class);
                foreach ($liveRoomDirty as $item) {
                    $liveRoomUserService->userLeave($item['room_id'], $item['user_id']);
                }
            } catch (\Throwable $e) {
                Log::warning('[WebSocket] 心跳检测更新离线状态失败：' . $e->getMessage());
            }
        }

        Log::info('[WebSocket] 心跳检测完成，清理超时连接 ' . count($removed) . ' 个，其中直播间用户 ' . count($liveRoomDirty) . ' 个');
    }

    /**
     * 判断用户是否已有同直播间在线连接
     */
    protected function hasActiveLiveRoomConnection(int $userId, int $roomId, string|int $excludeFd = ''): bool
    {
        $fds = ConnectionManager::getUserFds($userId);
        foreach ($fds as $userFd) {
            if ((string)$excludeFd !== '' && (string)$userFd === (string)$excludeFd) {
                continue;
            }

            $connection = ConnectionManager::getConnection($userFd);
            if (!$connection) {
                continue;
            }

            if (
                (int)($connection['client_type'] ?? 0) === ClientTypeEnum::LIVE_ROOM
                && (int)($connection['room_id'] ?? 0) === $roomId
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 多 tab 状态同步：新连接建立后，若该用户已有同类型的旧连接，则向新 fd 推送当前状态快照
     * 目前仅对平台端（PLATFORM）中控台场景同步直播间状态；其他类型可按需扩展
     *
     * @param string|int $fd  新连接 fd
     * @param int $userId     用户 ID
     * @param int $clientType 客户端类型
     * @param int $roomId     直播间 ID（PLATFORM 类型目前从 token 中无 room_id，暂不同步；LIVE_ROOM 类型可按需扩展）
     */
    protected function syncStateToNewConnection(string|int $fd, int $userId, int $clientType, int $roomId): void
    {
        try {
            // 获取该用户的所有同类型 fd（排除刚建立的新 fd 本身）
            $existingFds = array_filter(
                ConnectionManager::getUserFds($userId),
                fn($existFd) => (string)$existFd !== (string)$fd
            );

            // 没有旧连接，无需同步
            if (empty($existingFds)) {
                return;
            }

            // 对 LIVE_ROOM 类型：向新 fd 推送直播间当前快照
            if ($clientType === ClientTypeEnum::LIVE_ROOM && $roomId > 0) {
                $roomInfo = app()->make(LiveRoomService::class)->get(['id' => $roomId], ['id', 'title', 'status', 'online_count']);
                if ($roomInfo) {
                    PushNotification::notifyStateSync($fd, $roomId, is_array($roomInfo) ? $roomInfo : $roomInfo->toArray());
                }
            }

            Log::info("[WebSocket] 多 tab 同步：用户 {$userId}({$clientType}) 新 fd={$fd}，已有旧连接 " . count($existingFds) . " 个");
        } catch (\Throwable $e) {
            // 状态同步失败不影响连接建立
            Log::warning("[WebSocket] 多 tab 状态同步失败：" . $e->getMessage());
        }
    }
}
