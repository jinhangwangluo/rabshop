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

namespace app\common\model\store;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 用户优惠券模型
 */
class StoreCouponUser extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'store_coupon_user';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     * @var array
     */
    protected $type = [
        'discount_amount' => 'float',
        'min_order_amount' => 'float',
    ];

    /**
     * 获取方式
     */
    const RECEIVE_TYPE_RECEIVE = 'receive';    // 领取
    const RECEIVE_TYPE_SEND = 'send';          // 后台发送
    const RECEIVE_TYPE_GIVE = 'give';          // 满赠
    const RECEIVE_TYPE_NEW = 'new';            // 新人
    const RECEIVE_TYPE_BUY = 'buy';            // 买赠
    const RECEIVE_TYPE_FIRST = 'first';        // 首单赠送

    /**
     * 状态
     */
    const STATUS_UNUSED = 0;     // 未使用
    const STATUS_USED = 1;       // 已使用
    const STATUS_EXPIRED = 2;    // 已过期

    /**
     * 搜索器：优惠券ID
     */
    public function searchCouponIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('coupon_id', $value);
        }
    }

    /**
     * 搜索器：发放记录ID
     */
    public function searchIssueIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('issue_id', $value);
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
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：获取方式
     */
    public function searchReceiveTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('receive_type', $value);
        }
    }

    /**
     * 搜索器：创建时间
     */
    public function searchCreateTimeAttr($query, $value)
    {
        if (!empty($value) && is_array($value)) {
            $query->whereBetweenTime('create_time', $value[0], $value[1]);
        }
    }

    /**
     * 获取器：获取方式文本
     */
    public function getReceiveTypeTextAttr($value, $data)
    {
        $types = [
            self::RECEIVE_TYPE_RECEIVE => '领取',
            self::RECEIVE_TYPE_SEND => '后台发送',
            self::RECEIVE_TYPE_GIVE => '满赠',
            self::RECEIVE_TYPE_NEW => '新人',
            self::RECEIVE_TYPE_BUY => '买赠',
            self::RECEIVE_TYPE_FIRST => '首单赠送',
        ];
        return $types[$data['receive_type'] ?? 'receive'] ?? '领取';
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_UNUSED => '未使用',
            self::STATUS_USED => '已使用',
            self::STATUS_EXPIRED => '已过期',
        ];
        return $status[$data['status'] ?? 0] ?? '未使用';
    }

    /**
     * 关联：优惠券
     */
    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'coupon_id', 'id');
    }

    /**
     * 关联：发放记录
     */
    public function issue()
    {
        return $this->belongsTo(StoreCouponIssue::class, 'issue_id', 'id');
    }
}
