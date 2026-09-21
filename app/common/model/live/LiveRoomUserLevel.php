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
 * 用户直播间等级记录模型
 */
class LiveRoomUserLevel extends Base
{
    protected $name = 'live_room_user_level';
    protected $pk   = 'id';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联直播间
     */
    public function liveRoom()
    {
        return $this->belongsTo(LiveRoom::class, 'live_room_id');
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
     * 直播间ID搜索器
     */
    public function searchLiveRoomIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('live_room_id', $value);
        }
    }
}
