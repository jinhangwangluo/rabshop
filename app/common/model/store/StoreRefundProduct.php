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

declare(strict_types=1);

namespace app\common\model\store;

use app\common\model\Base;

/**
 * 退款商品明细模型
 */
class StoreRefundProduct extends Base
{
    protected $name = 'store_refund_product';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'refund_amount' => 'float',
        'platform_coupon_amount' => 'float',
        'commission_rate' => 'float',
        'commission_amount' => 'float',
        'merchant_income_amount' => 'float',
    ];

    /**
     * 搜索器：退款单号
     */
    public function searchRefundSnAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('refund_sn', $value);
        }
    }

    /**
     * 搜索器：订单ID
     */
    public function searchOrderIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_id', $value);
        }
    }

    /**
     * 搜索器：商户ID
     */
    public function searchMerchantIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }
}
