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

/**
 * 直播间礼物赠送流水模型
 */
class LiveGiftRecord extends Base
{
    protected $name = 'live_gift_record';
    protected $pk = 'id';

    protected $type = [
        'merchant_id' => 'integer',
        'live_anchor_id' => 'integer',
        'platform_commission_rate' => 'float',
        'merchant_commission_rate' => 'float',
        'platform_amount' => 'float',
        'merchant_amount' => 'float',
        'anchor_amount' => 'float',
    ];

    public function searchRoomIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('room_id', $value);
        }
    }

    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    public function searchGiftIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('gift_id', $value);
        }
    }
}
