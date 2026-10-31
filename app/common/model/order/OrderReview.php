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
use app\common\model\user\User;
use think\model\concern\SoftDelete;

/**
 * 订单评价模型
 */
class OrderReview extends Base
{
    use SoftDelete;

    /**
     * 表名
     */
    protected $name = 'order_review';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * JSON 字段
     */
    protected $json = ['images'];

    /**
     * 字段类型转换
     */
    protected $type = [
        'order_goods_id'   => 'integer',
        'images'           => 'array',
        'star'             => 'integer',
        'description_star' => 'integer',
        'logistics_star'   => 'integer',
        'service_star'     => 'integer',
        'is_anonymous'     => 'integer',
    ];

    /**
     * 最低星级
     */
    const STAR_MIN = 1;

    /**
     * 最高星级
     */
    const STAR_MAX = 5;

    /**
     * 搜索器：订单ID
     */
    public function searchOrderIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_review.order_id', (int)$value);
        }
    }

    /**
     * 搜索器：订单商品ID
     */
    public function searchOrderGoodsIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_review.order_goods_id', (int)$value);
        }
    }

    /**
     * 搜索器：用户ID
     */
    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_review.user_id', (int)$value);
        }
    }

    /**
     * 搜索器：星级
     */
    public function searchStarAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_review.star', (int)$value);
        }
    }

    /**
     * 搜索器：商户ID
     */
    public function searchMerchantIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_review.merchant_id', (int)$value);
        }
    }

    /**
     * 搜索器：是否已回复（1=已回复，0=未回复）
     */
    public function searchIsRepliedAttr($query, $value): void
    {
        if ($value === '' || $value === null) {
            return;
        }
        if ((int)$value === 1) {
            $query->whereNotNull('admin_reply')->where('admin_reply', '<>', '');
        } else {
            $query->where(function ($q): void {
                $q->whereNull('admin_reply')->whereOr('admin_reply', '');
            });
        }
    }

    /**
     * 搜索器：订单号（模糊匹配，需 join order 表时使用）
     */
    public function searchOrderSnAttr($query, $value): void
    {
        if ($value !== '') {
            $query->whereExists(function ($q) use ($value): void {
                $q->table('order')->whereColumn('order.id', 'order_review.order_id')
                    ->where('order.order_sn', 'like', '%' . $value . '%');
            });
        }
    }

    /**
     * 获取器：是否已回复
     */
    public function getIsRepliedAttr($value, $data): bool
    {
        return !empty($data['admin_reply']);
    }

    /**
     * 关联：订单
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 关联：订单商品
     */
    public function orderGoods()
    {
        return $this->belongsTo(OrderGoods::class, 'order_goods_id', 'id');
    }

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }
}
