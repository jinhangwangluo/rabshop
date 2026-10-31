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

namespace app\common\model\order;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 购物车模型
 */
class Cart extends Base
{
    use SoftDelete;

    /**
     * 表名
     */
    protected $name = 'cart';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 软删除字段
     */
    protected $deleteTime = 'delete_time';

    /**
     * 字段类型转换
     */
    protected $type = [
        'user_id' => 'integer',
        'merchant_id' => 'integer',
        'order_type' => 'integer',
        'goods_id' => 'integer',
        'cart_num' => 'integer',
        'is_pay' => 'integer',
        'is_new' => 'integer',
        'status' => 'integer',
        'plugin_payload_json' => 'array',
    ];

    /**
     * 订单类型常量
     */
    public const ORDER_TYPE_CONTENT = 1;
    public const ORDER_TYPE_GOODS = 2;
    public const ORDER_TYPE_SPECIAL = 3;

    /**
     * 搜索器：用户 ID
     */
    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    /**
     * 搜索器：商户 ID
     */
    public function searchMerchantIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：订单类型
     */
    public function searchOrderTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_type', $value);
        }
    }

    /**
     * 搜索器：商品 ID
     */
    public function searchGoodsIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('goods_id', $value);
        }
    }

    /**
     * 搜索器：是否已下单
     */
    public function searchIsPayAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_pay', $value);
        }
    }

    /**
     * 搜索器：是否立即购买
     */
    public function searchIsNewAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_new', $value);
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
