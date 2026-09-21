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
use app\common\service\setting\AdminService;
use Rabbit\service\websocket\ConnectionManager;
use Rabbit\service\websocket\TokenHelper;
use Rabbit\service\websocket\WebSocketService;
use think\facade\Log;

/**
 * 中控台回复处理器
 * 用于平台管理员在直播中控台回复用户消息
 */
class ConsoleReplyHandler implements MessageHandlerInterface
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

        // 验证权限：仅平台端可发送中控回复
        if ($connection['client_type'] !== ClientTypeEnum::PLATFORM) {
            throw new AdminResponseException('权限不足：仅平台管理员可发送中控回复');
        }

        if (!TokenHelper::hasScope($connection, 'console_reply')) {
            throw new AdminResponseException('权限不足：当前账号无权发送中控回复');
        }

        // 通过 AdminService 验证管理员状态，并获取昵称/头像
        $adminId = $connection['user_id'];
        $adminService = app()->make(AdminService::class);
        $adminInfo = $adminService->get(['id' => $adminId], ['id', 'real_name', 'avatar', 'status']);
        if (!$adminInfo || !$adminInfo['status']) {
            throw new AdminResponseException('管理员不存在或已禁用');
        }

        $roomId = (int)($data['data']['room_id'] ?? 0);
        if ($roomId <= 0) {
            throw new AdminResponseException('直播间ID 无效');
        }

        $content = trim($data['data']['content'] ?? '');
        if (empty($content)) {
            throw new AdminResponseException('回复内容不能为空');
        }

        // 可选：发送给特定用户（通过 fd 或 user_id）
        $targetFd = (int)($data['data']['fd'] ?? 0);
        $targetUserId = (int)($data['data']['user_id'] ?? 0);

        // 构建回复消息
        $replyData = [
            'msg_id'      => 'reply_' . time() . '_' . $adminId . '_' . mt_rand(1000, 9999),
            'user_id'     => $adminId,
            'nickname'    => $adminInfo['real_name'] ?: '管理员',
            'avatar'      => $adminInfo['avatar'] ?? '',
            'room_id'     => $roomId,
            'content'     => $content,
            'msg_type'    => 'console_reply',
            'send_time'   => date('Y-m-d H:i:s'),
            'is_console'  => true,
            'timestamp'   => time()
        ];

        Log::info("[中控回复] room={$roomId}, admin={$adminId}, content={$content}");

        $wsService = app()->make(WebSocketService::class);
        $message = [
            'type'      => 'console_reply',
            'event'     => 'console_reply',
            'code'      => 200,
            'data'      => $replyData,
            'message'   => 'success',
            'timestamp' => time()
        ];

        if ($targetFd > 0) {
            // 发送给指定 FD
            $wsService->sendToFd($targetFd, $message);
        } elseif ($targetUserId > 0) {
            // 发送给指定用户的所有连接
            $wsService->sendToUser($targetUserId, $message, null);
        } else {
            // 广播给直播间内的所有用户
            $wsService->sendToRoom($roomId, $message);
        }
    }

    /**
     * @inheritDoc
     */
    public static function getEvents(): array
    {
        return ['console_reply'];
    }
}
