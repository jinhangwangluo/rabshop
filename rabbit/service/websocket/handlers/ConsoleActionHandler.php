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

namespace Rabbit\service\websocket\handlers;

use app\common\enum\ClientTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\service\live\LiveRoomService;
use app\common\service\live\LiveRoomUserService;
use Rabbit\service\websocket\ConnectionManager;
use Rabbit\service\websocket\PushNotification;
use Rabbit\service\websocket\TokenHelper;
use Rabbit\service\websocket\WebSocketService;
use think\facade\Log;

/**
 * 中控台操作推送处理器
 * 处理开播/关播/禁言/踢人等操作并广播
 */
class ConsoleActionHandler implements MessageHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function handle(string|int $fd, array $data): void
    {
        $connection = ConnectionManager::getConnection($fd);
        if (!$connection) {
            throw new AdminResponseException('连接不存在');
        }

        // 验证权限：仅平台端可发送中控操作
        if ($connection['client_type'] !== ClientTypeEnum::PLATFORM) {
            throw new AdminResponseException('权限不足：仅平台管理员可执行中控操作');
        }

        $adminId = $connection['user_id'];
        $roomId = (int)($data['data']['room_id'] ?? 0);
        $action = (string)($data['data']['action'] ?? '');
        $actionData = $data['data']['data'] ?? [];

        if ($roomId <= 0) {
            throw new AdminResponseException('直播间ID 无效');
        }

        if (empty($action)) {
            throw new AdminResponseException('操作类型不能为空');
        }

        if (!TokenHelper::hasScope($connection, 'console_action:' . $action)) {
            throw new AdminResponseException('权限不足：当前账号无权执行该中控操作');
        }

        Log::info("[中控操作] room={$roomId}, admin={$adminId}, action={$action}");

        // 商品类操作：在各自 handle 方法内已通过 notifyGoodsChange 推送，不再走通用广播
        $goodsActions = ['goods_shelf', 'goods_stock', 'goods_info'];

        // 查询类操作：直接返回结果给发起者，不广播
        $queryActions = ['get_online_users'];

        // 需要特殊处理的操作（手动控制广播时机）
        $specialActions = ['kick'];

        // 根据操作类型执行业务逻辑
        switch ($action) {
            case 'start_live':
                // 开播
                $this->handleStartLive($roomId, $adminId, $actionData);
                break;

            case 'end_live':
                // 关播
                $this->handleEndLive($roomId, $adminId, $actionData);
                break;

            case 'mute':
                // 禁言用户
                $this->handleMuteUser($roomId, $adminId, $actionData);
                break;

            case 'unmute':
                // 解除禁言
                $this->handleUnmuteUser($roomId, $adminId, $actionData);
                break;

            case 'kick':
                // 踢出用户
                $this->handleKickUser($roomId, $adminId, $actionData);
                break;

            case 'toggle_hot':
                // 切换热门推荐
                $this->handleToggleHot($roomId, $adminId, $actionData);
                break;

            case 'get_online_users':
                // 查询当前在线用户列表（仅回给发起者）
                $this->handleGetOnlineUsers($fd, $roomId, $adminId);
                break;

            case 'goods_shelf':
                // 商品上下架
                $this->handleGoodsShelf($roomId, $adminId, $actionData);
                break;

            case 'goods_stock':
                // 商品库存变更
                $this->handleGoodsStock($roomId, $adminId, $actionData);
                break;

            case 'goods_info':
                // 商品信息变更
                $this->handleGoodsInfo($roomId, $adminId, $actionData);
                break;

            default:
                throw new AdminResponseException('未知的操作类型');
        }

        // 广播操作结果给直播间内的所有用户（开关播由 LiveRoomService::switchStatus 统一广播）
        $skipActions = [...$goodsActions, ...$queryActions, ...$specialActions];
        $skipActions[] = 'start_live';
        $skipActions[] = 'end_live';
        if (!in_array($action, $skipActions, true)) {
            PushNotification::notifyConsoleAction($roomId, $action, $actionData);
        }

        // 向当前管理员的所有 fd 发送操作回执（多 tab/多浏览器状态同步）
        // 查询类操作已由各自 handle 方法直接 sendToFd 回复，无需再发 ack
        if (!in_array($action, $queryActions, true)) {
            PushNotification::notifyConsoleAck($adminId, $action, $roomId, $actionData);
        }
    }

    /**
     * 处理开播
     */
    protected function handleStartLive(int $roomId, int $adminId, array $data): void
    {
        $service = app()->make(LiveRoomService::class);
        $service->switchStatus($roomId, 1);

        Log::info("直播间 {$roomId} 已开播");
    }

    /**
     * 处理关播
     */
    protected function handleEndLive(int $roomId, int $adminId, array $data): void
    {
        $service = app()->make(LiveRoomService::class);
        $service->switchStatus($roomId, 2);

        // 将直播间所有用户标记为离线
        $roomUserService = app()->make(LiveRoomUserService::class);
        $roomUserService->markAllOffline($roomId);
        app()->make(LiveRoomService::class)->clearMobileRoomCaches($roomId);

        Log::info("直播间 {$roomId} 已结束");
    }

    /**
     * 处理禁言用户
     * @param int   $roomId
     * @param int   $adminId
     * @param array $data ['user_id' => int, 'duration' => int, 'reason' => string]
     *                    duration: 禁言秒数，0=永久，默认 3600（1 小时）
     */
    protected function handleMuteUser(int $roomId, int $adminId, array $data): void
    {
        $userId   = (int)($data['user_id'] ?? 0);
        $duration = (int)($data['duration'] ?? 3600);
        $reason   = trim($data['reason'] ?? '');
    
        if ($userId <= 0) {
            throw new AdminResponseException('用户 ID 无效');
        }
    
        $roomUserService = app()->make(LiveRoomUserService::class);
        $result          = $roomUserService->muteUser($roomId, $userId, $adminId, $duration, $reason);
    
        $label = $duration > 0 ? "{$duration} 秒" : '永久';
        Log::info("用户 {$userId} 在直播间 {$roomId} 被禁言 {$label}，操作者：{$adminId}");
    
        // 广播禁言状态变更（包含完整的用户信息）
        PushNotification::notifyConsoleAction($roomId, 'mute', $result);
    }

    /**
     * 处理解除禁言
     * @param int   $roomId
     * @param int   $adminId
     * @param array $data ['user_id' => int]
     */
    protected function handleUnmuteUser(int $roomId, int $adminId, array $data): void
    {
        $userId = (int)($data['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new AdminResponseException('用户 ID 无效');
        }

        $roomUserService = app()->make(LiveRoomUserService::class);
        $roomUserService->unmuteUser($roomId, $userId);

        Log::info("用户 {$userId} 在直播间 {$roomId} 被解除禁言，操作者：{$adminId}");

        // 广播解除禁言状态
        PushNotification::notifyConsoleAction($roomId, 'unmute', [
            'user_id' => $userId,
            'is_muted' => 0
        ]);
    }

    /**
     * 查询当前在线用户列表（仅返回给发起者，不广播）
     * @param int $fd     发起者连接描述符
     * @param int $roomId
     * @param int $adminId
     */
    protected function handleGetOnlineUsers(int $fd, int $roomId, int $adminId): void
    {
        $roomUserService = app()->make(LiveRoomUserService::class);
        $onlineUsers     = $roomUserService->getOnlineUsers($roomId);
        $onlineCount     = count($onlineUsers);

        $wsService = app()->make(WebSocketService::class);
        $wsService->sendToFd($fd, [
            'type'      => 'response',
            'event'     => 'console_action',
            'code'      => 200,
            'data'      => [
                'action'       => 'get_online_users',
                'live_room_id' => $roomId,
                'online_count' => $onlineCount,
                'online_users' => $onlineUsers,
            ],
            'message'   => 'success',
            'timestamp' => time(),
        ]);

        Log::info("[中控查询] room={$roomId} 在线用户数：{$onlineCount}，操作者：{$adminId}");
    }

    /**
     * 处理踢出用户
     */
    protected function handleKickUser(int $roomId, int $adminId, array $data): void
    {
        $userId = (int)($data['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new AdminResponseException('用户 ID 无效');
        }

        // 获取该用户在直播间的所有连接 FD
        $allFds = ConnectionManager::getRoomFds($roomId);
        $wsService = app()->make(WebSocketService::class);
        foreach ($allFds as $fd) {
            $conn = ConnectionManager::getConnection($fd);
            if ($conn && $conn['user_id'] === $userId) {
                // 关闭连接
                $wsService->closeConnection($fd);
            }
        }

        Log::info("用户 {$userId} 被踢出直播间 {$roomId}");

        // 广播通知前端刷新用户列表（踢人操作需要手动广播）
        PushNotification::notifyConsoleAction($roomId, 'kick', [
            'user_id' => $userId,
            'action_type' => 'kick_out'
        ]);
    }

    /**
     * 处理切换热门推荐
     */
    protected function handleToggleHot(int $roomId, int $adminId, array $data): void
    {
        $service = app()->make(LiveRoomService::class);
        $result = $service->toggleHot($roomId);

        Log::info("直播间 {$roomId} 热门状态：" . ($result['is_hot'] ?? ''));
    }

    /**
     * 处理商品上下架
     * @param int   $roomId
     * @param int   $adminId
     * @param array $data ['goods_id' => int, 'status' => 0|1]
     */
    protected function handleGoodsShelf(int $roomId, int $adminId, array $data): void
    {
        $goodsId = (int)($data['goods_id'] ?? 0);
        $status  = (int)($data['status'] ?? 1);

        if ($goodsId <= 0) {
            throw new AdminResponseException('商品 ID 无效');
        }

        if (!in_array($status, [0, 1], true)) {
            throw new AdminResponseException('状态值无效，1=上架，0=下架');
        }

        $service = app()->make(\app\common\service\live\LiveGoodsService::class);
        $result  = $service->switchGoodsStatus($goodsId, $status);

        // 验证商品属于当前直播间
        if ((int)($result['live_room_id'] ?? 0) !== $roomId) {
            throw new AdminResponseException('商品不属于当前直播间');
        }

        $label = $status === 1 ? '上架' : '下架';
        Log::info("直播间 {$roomId} 商品 {$goodsId}（{$result['goods_title']}）已{$label}，操作者：{$adminId}");

        // 推送商品上下架变更通知给直播间用户
        PushNotification::notifyGoodsChange($roomId, 'shelf', $result);
    }

    /**
     * 处理商品库存变更
     * @param int   $roomId
     * @param int   $adminId
     * @param array $data ['goods_id' => int, 'stock_change' => int]  正数增加，负数减少
     */
    protected function handleGoodsStock(int $roomId, int $adminId, array $data): void
    {
        $goodsId     = (int)($data['goods_id'] ?? 0);
        $stockChange = (int)($data['stock_change'] ?? 0);

        if ($goodsId <= 0) {
            throw new AdminResponseException('商品 ID 无效');
        }

        if ($stockChange === 0) {
            throw new AdminResponseException('库存变化量不能为 0');
        }

        $service = app()->make(\app\common\service\live\LiveGoodsService::class);
        $result  = $service->updateGoodsStock($goodsId, $stockChange);

        if ((int)($result['live_room_id'] ?? 0) !== $roomId) {
            throw new AdminResponseException('商品不属于当前直播间');
        }

        $direction = $stockChange > 0 ? '+' : '';
        Log::info("直播间 {$roomId} 商品 {$goodsId} 库存变更 {$direction}{$stockChange}，当前库存：{$result['stock']}，操作者：{$adminId}");

        // 推送库存变更通知给直播间用户
        PushNotification::notifyGoodsChange($roomId, 'stock', $result);
    }

    /**
     * 处理商品信息变更（展示标题/封面/价格）
     * @param int   $roomId
     * @param int   $adminId
     * @param array $data ['goods_id' => int, 'goods_title' => string, 'goods_cover' => string, 'goods_price' => float]
     */
    protected function handleGoodsInfo(int $roomId, int $adminId, array $data): void
    {
        $goodsId = (int)($data['goods_id'] ?? 0);

        if ($goodsId <= 0) {
            throw new AdminResponseException('商品 ID 无效');
        }

        $infoFields = array_intersect_key($data, array_flip(['goods_title', 'goods_cover', 'goods_price']));

        $service = app()->make(\app\common\service\live\LiveGoodsService::class);
        $result  = $service->updateGoodsInfo($goodsId, $infoFields);

        if ((int)($result['live_room_id'] ?? 0) !== $roomId) {
            throw new AdminResponseException('商品不属于当前直播间');
        }

        Log::info("直播间 {$roomId} 商品 {$goodsId} 展示信息已更新，操作者：{$adminId}");

        // 推送商品信息变更通知给直播间用户
        PushNotification::notifyGoodsChange($roomId, 'info', $result);
    }

    /**
     * @inheritDoc
     */
    public static function getEvents(): array
    {
        return ['console_action'];
    }
}
