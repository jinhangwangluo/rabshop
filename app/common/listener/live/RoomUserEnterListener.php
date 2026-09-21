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

namespace app\common\listener\live;

use app\common\model\live\LiveRoom as LiveRoomModel;
use app\common\service\live\LiveRoomUserLevelService;
use app\common\service\user\UserService;
use Rabbit\service\websocket\PushNotification;
use think\facade\Log;

/**
 * 直播间用户进场监听器
 * 负责组装欢迎语与等级信息并广播给房间内用户
 */
class RoomUserEnterListener
{
    /**
     * 处理事件
     * @param array $payload [live_room_id, user_id]
     */
    public function handle(array $payload): void
    {
        try {
            $roomId = (int)($payload['live_room_id'] ?? $payload['room_id'] ?? 0);
            $userId = (int)($payload['user_id'] ?? 0);
            if ($roomId <= 0 || $userId <= 0) {
                return;
            }

            $user = app()->make(UserService::class)->get(['uid' => $userId], ['nickname', 'avatar']);
            $room = app()->make(LiveRoomModel::class)
                ->where('id', $roomId)
                ->field(['id', 'welcome_msg'])
                ->find();
            if (!$room) {
                return;
            }

            $levelInfo = app()->make(LiveRoomUserLevelService::class)->getUserLevel($userId, $roomId) ?? [];
            $nickname  = (string)($user['nickname'] ?? '新朋友');
            $welcomeMsg = trim((string)($room['welcome_msg'] ?? ''));
            $levelName = (string)($levelInfo['name'] ?? $levelInfo['level_name'] ?? '');
            $levelIcon = (string)($levelInfo['icon'] ?? $levelInfo['level_icon'] ?? '');

            PushNotification::broadcastRoomUserEnter([
                'event_id'      => uniqid('enter_', true),
                'room_id'       => $roomId,
                'live_room_id'  => $roomId,
                'user_id'       => $userId,
                'nickname'      => $nickname,
                'avatar'        => (string)($user['avatar'] ?? ''),
                'welcome_msg'   => $welcomeMsg,
                'display_text'  => $this->buildDisplayText($nickname, $welcomeMsg),
                'name'          => $levelName,
                'icon'          => $levelIcon,
                'timestamp'     => time(),
            ]);

            Log::info('[直播间进场广播] 用户 ' . $nickname . ' 进入房间 ' . $roomId);
        } catch (\Throwable $e) {
            Log::error('[RoomUserEnterListener] 失败：' . $e->getMessage());
        }
    }

    public function __invoke(array $payload): void
    {
        $this->handle($payload);
    }

    /**
     * 生成直播间系统欢迎文案
     */
    protected function buildDisplayText(string $nickname, string $welcomeMsg): string
    {
        if ($welcomeMsg === '') {
            return '欢迎 ' . $nickname . ' 进入直播间';
        }

        $replaced = str_replace(['{nickname}', '{{nickname}}'], $nickname, $welcomeMsg);
        if (mb_strpos($replaced, $nickname) !== false) {
            return $replaced;
        }

        return $nickname . ' ' . $replaced;
    }
}
