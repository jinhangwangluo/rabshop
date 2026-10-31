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

use Rabbit\service\websocket\PushNotification;
use think\facade\Log;

/**
 * 用户直播间等级升级监听器
 * 通过 WebSocket推送升级消息给直播间内所有用户
 */
class UserLevelUpListener
{
    /**
     * 处理事件
     * @param array $payload [user_id, nickname, avatar, live_room_id, old_level, new_level, level_name, level_icon, upgrade_points]
     */
    public function handle(array $payload): void
    {
        try {
            // 通过 WebSocket推送升级广播给直播间内所有用户
            PushNotification::broadcastLevelUp($payload);

            Log::info('[直播间升级广播] 用户 ' . ($payload['nickname'] ?? $payload['user_id']) . ' 升级为 ' . ($payload['new_level'] ?? '?'));
        } catch (\Throwable $e) {
            Log::error('[UserLevelUpListener] 失败：' . $e->getMessage());
        }
    }

    public function __invoke(array $payload): void
    {
        $this->handle($payload);
    }
}
