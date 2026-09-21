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
 * 直播间贡献榜更新监听器
 * 通过 WebSocket推送最新的 Top 10 榜单给直播间内所有用户
 */
class RoomRankUpdateListener
{
    /**
     * 处理事件
     * @param array $payload [live_room_id, rank_list: [{rank, user_id, nickname, avatar, level, level_icon, contribution}]]
     */
    public function handle(array $payload): void
    {
        try {
            // 通过 WebSocket推送给直播间内所有用户
            PushNotification::broadcastRankUpdate(
                (int)($payload['live_room_id'] ?? 0),
                $payload['rank_list'] ?? []
            );

            Log::info('[直播间贡献榜更新] 已推送至房间 ' . ($payload['live_room_id'] ?? 0));
        } catch (\Throwable $e) {
            Log::error('[RoomRankUpdateListener] 失败：' . $e->getMessage());
        }
    }

    public function __invoke(array $payload): void
    {
        $this->handle($payload);
    }
}
