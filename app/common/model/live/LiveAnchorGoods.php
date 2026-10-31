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
 * 主播可挂载商品分配模型
 */
class LiveAnchorGoods extends Base
{
    protected $name = 'live_anchor_goods';
    protected $pk   = 'id';

    /**
     * 主播ID搜索器
     */
    public function searchLiveAnchorIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('live_anchor_id', $value);
        }
    }

    /**
     * 商户ID搜索器
     */
    public function searchMerchantIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }
}
