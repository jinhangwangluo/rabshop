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

namespace app\common\model\live;

use app\common\model\Base;
use app\common\model\user\User;

/**
 * 直播间用户记录模型
 * 职责：在线状态 + 禁言状态
 */
class LiveRoomUser extends Base
{
    protected $name = 'live_room_user';
    protected $pk   = 'id';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field(['id', 'nickname', 'avatar']);
    }

    /**
     * 关联直播间
     */
    public function liveRoom()
    {
        return $this->belongsTo(LiveRoom::class, 'room_id');
    }

    /**
     * 直播间ID搜索器
     */
    public function searchRoomIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('room_id', $value);
        }
    }

    /**
     * 用户ID搜索器
     */
    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    /**
     * 在线状态搜索器
     */
    public function searchIsOnlineAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_online', $value);
        }
    }

    /**
     * 禁言状态搜索器
     */
    public function searchIsMutedAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_muted', $value);
        }
    }
}
