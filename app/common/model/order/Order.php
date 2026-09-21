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
 * 订单模型
 */
class Order extends Base
{
    use SoftDelete;

    /**
     * 表名
     */
    protected $name = 'order';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     */
    protected $type = [
        'pid' => 'integer',
        'order_role' => 'integer',
        'original_amount' => 'float',
        'total_amount' => 'float',
        'discount_amount' => 'float',
        'points_deduct_amount' => 'float',
        'member_discount_amount' => 'float',
        'member_discount_rate' => 'float',
        'postage' => 'float',
        'pay_amount' => 'float',
        'platform_coupon_amount' => 'float',
        'merchant_coupon_amount' => 'float',
        'first_commission_amount' => 'float',
        'second_commission_amount' => 'float',
        'commission_base_amount' => 'float',
        'first_commission_rate' => 'float',
        'second_commission_rate' => 'float',
        'commission_level' => 'integer',
        'platform_commission_amount' => 'float',
        'merchant_income_amount' => 'float',
        'plugin_payload_json' => 'array',
    ];

    /**
     * 订单类型常量
     */
    const ORDER_TYPE_CONTENT = 1;    // 内容订单
    const ORDER_TYPE_GOODS = 2;      // 商品订单
    const ORDER_TYPE_SPECIAL = 3;    // 专题订单

    /**
     * 订单角色常量
     */
    const ROLE_NORMAL = 0;       // 普通订单
    const ROLE_MAIN = 1;         // 主支付单
    const ROLE_CHILD = 2;        // 商户子单

    /**
     * 订单状态常量
     */
    const STATUS_PENDING = 0;        // 待支付
    const STATUS_PAID = 1;           // 已支付
    const STATUS_COMPLETED = 2;      // 已完成
    const STATUS_CANCELLED = 3;      // 已取消
    const STATUS_REFUNDED = 4;       // 已退款
    const STATUS_REFUNDING = 5;      // 退款中
    const STATUS_WAIT_REVIEW = 6;    // 待评价

    /**
     * 佣金状态常量
     */
    const COMMISSION_STATUS_PENDING = 0;   // 待处理
    const COMMISSION_STATUS_FROZEN = 1;    // 冻结中
    const COMMISSION_STATUS_GRANTED = 2;   // 已发放

    /**
     * 搜索器：订单号
     */
    public function searchOrderSnAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('order_sn', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：父订单ID
     */
    public function searchPidAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('pid', $value);
        }
    }

    /**
     * 搜索器：订单角色
     */
    public function searchOrderRoleAttr($query, $value): void
    {
        if ($value !== '') {
            if ($value === 'business') {
                $query->where('order_role', '<>', self::ROLE_MAIN);
                return;
            }

            $query->where('order_role', $value);
        }
    }

    /**
     * 搜索器：主支付订单号
     */
    public function searchMainOrderSnAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('main_order_sn', $value);
        }
    }

    /**
     * 搜索器：用户ID
     */
    public function searchUserIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    /**
     * 搜索器：商户ID
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：订单状态
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            if ($value === 'unsent') {
                $query->where('status', self::STATUS_PAID)
                    ->where('order_type', self::ORDER_TYPE_GOODS)
                    ->whereNull('delivery_time');
                $this->applyPhysicalGoodsExists($query);
                return;
            }

            if ($value === 'undelivered') {
                $query->where('status', self::STATUS_PAID)
                    ->where('order_type', self::ORDER_TYPE_GOODS)
                    ->whereNotNull('delivery_time')
                    ->whereNull('receive_time');
                $this->applyPhysicalGoodsExists($query);
                return;
            }

            $query->where('status', $value);
        }
    }

    /**
     * 限定订单包含实物商品。
     */
    protected function applyPhysicalGoodsExists($query): void
    {
        $orderTable = $this->getTable();
        $orderGoodsTable = (new OrderGoods())->getTable();

        $query->whereExists(function ($existsQuery) use ($orderTable, $orderGoodsTable): void {
            $existsQuery->table($orderGoodsTable)
                ->whereColumn($orderGoodsTable . '.order_id', $orderTable . '.id')
                ->where($orderGoodsTable . '.goods_type', OrderGoods::GOODS_TYPE_PHYSICAL);
        });
    }

    /**
     * 搜索器：支付方式
     */
    public function searchPayTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('pay_type', $value);
        }
    }

    /**
     * 搜索器：订单类型
     */
    public function searchOrderTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('order_type', $value);
        }
    }

    /**
     * 搜索器：退款状态
     */
    public function searchRefundStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('refund_status', $value);
        }
    }

    /**
     * 搜索器：用户关键字
     */
    public function searchUserKeywordAttr($query, $value): void
    {
        $keyword = trim((string)$value);
        if ($keyword === '') {
            return;
        }

        $query->where(function ($subQuery) use ($keyword) {
            $subQuery->where('user_phone', 'like', '%' . $keyword . '%');

            if (ctype_digit($keyword)) {
                $subQuery->whereOr('user_id', (int)$keyword);
            }

            $subQuery->whereExists(function ($existsQuery) use ($keyword): void {
                $existsQuery->table('user')
                    ->whereColumn('user.uid', 'order.user_id')
                    ->where(function ($userQuery) use ($keyword): void {
                        $userQuery->where('user.nickname', 'like', '%' . $keyword . '%')
                            ->whereOr('user.phone', 'like', '%' . $keyword . '%');
                    });
            }, 'OR');
        });
    }

    /**
     * 搜索器：商品名称
     */
    public function searchGoodsNameAttr($query, $value): void
    {
        $goodsName = trim((string)$value);
        if ($goodsName === '') {
            return;
        }

        $query->whereExists(function ($existsQuery) use ($goodsName): void {
            $existsQuery->table('order_goods')
                ->whereColumn('order_goods.order_id', 'order.id')
                ->where('order_goods.goods_name', 'like', '%' . $goodsName . '%');
        });
    }

    /**
     * 搜索器：开始时间
     */
    public function searchStartTimeAttr($query, $value): void
    {
        $startTime = trim((string)$value);
        if ($startTime !== '') {
            $query->where('create_time', '>=', $startTime . ' 00:00:00');
        }
    }

    /**
     * 搜索器：结束时间
     */
    public function searchEndTimeAttr($query, $value): void
    {
        $endTime = trim((string)$value);
        if ($endTime !== '') {
            $query->where('create_time', '<=', $endTime . ' 23:59:59');
        }
    }

    /**
     * 搜索器：创建时间
     */
    public function searchCreateTimeAttr($query, $value): void
    {
        if ($value === '' || $value === null) {
            return;
        }

        if (is_array($value) && count($value) >= 2) {
            $query->whereBetweenTime('create_time', $value[0], $value[1]);
            return;
        }

        set_model_time($query, (string)$value, 'create_time');
    }

    /**
     * 搜索器：推广人ID
     */
    public function searchReferrerIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('referrer_id', $value);
        }
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_PENDING => '待支付',
            self::STATUS_PAID => '已支付',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_REFUNDED => '已退款',
            self::STATUS_REFUNDING => '退款中',
            self::STATUS_WAIT_REVIEW => '待评价',
        ];
        return $status[$data['status'] ?? 0] ?? '未知';
    }

    /**
     * 获取器：订单类型文本
     */
    public function getOrderTypeTextAttr($value, $data)
    {
        $types = [
            self::ORDER_TYPE_CONTENT => '内容订单',
            self::ORDER_TYPE_GOODS => '商品订单',
            self::ORDER_TYPE_SPECIAL => '专题订单',
        ];
        return $types[$data['order_type'] ?? 1] ?? '未知';
    }

    /**
     * 关联：订单商品
     */
    public function orderGoods()
    {
        return $this->hasMany(OrderGoods::class, 'order_id', 'id');
    }

    /**
     * 关联：父订单
     */
    public function parentOrder()
    {
        return $this->belongsTo(self::class, 'pid', 'id');
    }

    /**
     * 关联：子订单
     */
    public function childOrders()
    {
        return $this->hasMany(self::class, 'pid', 'id');
    }
}
