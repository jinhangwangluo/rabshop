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

namespace app\common\model\user;

use app\common\model\Base;

/**
 * 用户充值单模型.
 */
class UserRechargeOrder extends Base
{
    public const STATUS_PENDING = 0;
    public const STATUS_PAID = 1;
    public const STATUS_FAILED = 2;

    /** @var string 订单类型：充值 */
    public const ORDER_TYPE_RECHARGE = 'recharge';
    /** @var string 订单类型：会员 */
    public const ORDER_TYPE_MEMBER = 'member';
    /** @var string 订单类型：直播金币 */
    public const ORDER_TYPE_LIVE_COIN = 'live_coin';

    /**
     * 数据表名.
     *
     * @var string
     */
    protected $name = 'user_recharge_order';

    /**
     * 主键.
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳.
     *
     * @var string|bool
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换.
     *
     * @var array
     */
    protected $type = [
        'recharge_id' => 'integer',
        'package_id' => 'integer',
        'duration_days' => 'integer',
        'amount' => 'float',
        'pay_amount' => 'float',
    ];

    public function setRequestPayloadAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public function getRequestPayloadAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }

    public function setResponsePayloadAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public function getResponsePayloadAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }

    public function setNotifyPayloadAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public function getNotifyPayloadAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }

    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    public function searchOrderSnAttr($query, $value): void
    {
        if ($value !== '') {
            $query->whereLike('order_sn', '%' . $value . '%');
        }
    }

    public function searchPayTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('pay_type', $value);
        }
    }

    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    public function searchOrderTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_type', $value);
        }
    }

    public function getBenefitSnapshotAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }

    public function setBenefitSnapshotAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
