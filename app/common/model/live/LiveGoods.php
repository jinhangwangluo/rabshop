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
use think\model\concern\SoftDelete;

/**
 * 直播带货关联模型
 */
class LiveGoods extends Base
{
    use SoftDelete;

    protected $name = 'live_goods';
    protected $pk   = 'id';

    /**
     * 直播间ID搜索器
     */
    public function searchLiveRoomIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('live_room_id', $value);
        }
    }

    /**
     * 商品类型搜索器
     */
    public function searchGoodsTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('goods_type', $value);
        }
    }

    /**
     * 状态搜索器
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', (int)$value);
        }
    }
}
