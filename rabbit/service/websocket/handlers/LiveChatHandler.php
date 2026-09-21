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
use app\common\service\live\LiveRoomUserService;
use app\common\service\SensitiveFilterTrait;
use app\common\service\user\UserService;
use Rabbit\service\websocket\ConnectionManager;
use Rabbit\service\websocket\PushNotification;
use think\facade\Log;

/**
 * 直播间聊天处理器
 */
class LiveChatHandler implements MessageHandlerInterface
{
    use SensitiveFilterTrait;

    /**
     * @inheritDoc
     */
    public function handle(string|int $fd, array $data): void
    {
        $connection = ConnectionManager::getConnection($fd);
        if (!$connection) {
            throw new AdminResponseException('连接不存在');
        }

        // 验证是否为直播间连接
        if ($connection['client_type'] !== ClientTypeEnum::LIVE_ROOM) {
            throw new AdminResponseException('非直播间连接，无法发送聊天消息');
        }

        $roomId = $connection['room_id'];
        $userId = $connection['user_id'];
        $content = trim($data['data']['content'] ?? '');

        // 验证内容
        if (empty($content)) {
            throw new AdminResponseException('聊天内容不能为空');
        }

        // 禁言检查
        $roomUserService = app()->make(LiveRoomUserService::class);
        $remainingSeconds = $roomUserService->getMuteRemainingSeconds($roomId, $userId);
        if ($remainingSeconds > 0) {
            $minutes = ceil($remainingSeconds / 60);
            throw new AdminResponseException("您已被禁言，还剩约 {$minutes} 分钟");
        }

        // 敏感词过滤：将敏感词替换为 *，过滤后结果为纯 * 才拦截
        $filtered = $this->filterSensitiveText($content);
        if (trim($filtered, '*') === '' && strlen($filtered) > 0) {
            throw new AdminResponseException('消息包含敏感词，发送失败');
        }
        $content = $filtered;

        // 生成消息 ID
        $msgId = 'chat_' . time() . '_' . $userId . '_' . mt_rand(1000, 9999);

        // 获取用户信息（使用 Service 规范方法）
        $userService = app()->make(UserService::class);
        $userInfo = $userService->get(['uid' => $userId], ['nickname', 'avatar']);

        // 构建聊天消息数据
        $chatData = [
            'msg_id'      => $msgId,
            'user_id'     => $userId,
            'nickname'    => $userInfo['nickname'] ?? '游客',
            'avatar'      => $userInfo['avatar'] ?? '',
            'room_id'     => $roomId,
            'content'     => $content,
            'msg_type'    => $data['data']['msg_type'] ?? 'text', // text/gift/system
            'send_time'   => date('Y-m-d H:i:s'),
            'timestamp'   => time()
        ];

        // 持久化到数据库（通过 Service）
        $chatRecordService = app()->make(\app\common\service\live\LiveChatRecordService::class);
        $record = $chatRecordService->createRecord([
            'msg_id'    => $msgId,
            'room_id'   => $roomId,
            'user_id'   => $userId,
            'nickname'  => $userInfo['nickname'] ?? '',
            'avatar'    => $userInfo['avatar'] ?? '',
            'content'   => $content,
            'msg_type'  => $chatData['msg_type'],
            'send_time' => $chatData['send_time'],
        ]);
        $chatData['id'] = (int)($record['id'] ?? 0);

        Log::info("[直播聊天] room={$roomId}, user={$userId}, content={$content}");

        // 广播给直播间内的所有用户
        PushNotification::sendChatMessage($roomId, $chatData);
    }

    /**
     * @inheritDoc
     */
    public static function getEvents(): array
    {
        return ['live_chat'];
    }
}
