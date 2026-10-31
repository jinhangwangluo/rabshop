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
 * 优惠券模型
 */
class StoreCoupon extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'store_coupon';

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
        'min_consume_amount' => 'float',
    ];

    /**
     * 适用范围类型
     */
    const SCOPE_TYPE_SHOP = 0;          // 店铺券
    const SCOPE_TYPE_PRODUCT = 1;       // 商品券
    const SCOPE_TYPE_PLATFORM = 10;     // 平台通用券
    const SCOPE_TYPE_CATEGORY = 11;     // 平台品类券

    /**
     * 有效期类型
     */
    const EXPIRE_TYPE_DAYS = 0;         // 有效天数
    const EXPIRE_TYPE_RANGE = 1;        // 固定时间段

    /**
     * 发放类型
     */
    const DISTRIBUTE_TYPE_RECEIVE = 0;  // 领取
    const DISTRIBUTE_TYPE_FULL = 1;     // 消费满赠
    const DISTRIBUTE_TYPE_NEW = 2;      // 新人
    const DISTRIBUTE_TYPE_BUY = 3;      // 买赠
    const DISTRIBUTE_TYPE_FIRST = 4;    // 首单赠送

    /**
     * 状态
     */
    const STATUS_CLOSE = 0;     // 关闭
    const STATUS_OPEN = 1;      // 开启
    const STATUS_INVALID = -1;  // 失效

    /**
     * 搜索器：优惠券名称
     */
    public function searchNameAttr($query, $value)
    {
        $query->where('name', 'like', '%' . $value . '%');
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
     * 搜索器：适用范围
     */
    public function searchScopeTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('scope_type', $value);
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
     * 搜索器：发放类型
     */
    public function searchDistributeTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('distribute_type', $value);
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
     * 获取器：适用范围文本
     */
    public function getScopeTypeTextAttr($value, $data)
    {
        $types = [
            self::SCOPE_TYPE_SHOP => '店铺券',
            self::SCOPE_TYPE_PRODUCT => '商品券',
            self::SCOPE_TYPE_PLATFORM => '平台通用券',
            self::SCOPE_TYPE_CATEGORY => '平台品类券',
        ];
        return $types[$data['scope_type'] ?? 0] ?? '店铺券';
    }

    /**
     * 获取器：有效期类型文本
     */
    public function getExpireTypeTextAttr($value, $data)
    {
        $types = [
            self::EXPIRE_TYPE_DAYS => '有效天数',
            self::EXPIRE_TYPE_RANGE => '固定时间段',
        ];
        return $types[$data['expire_type'] ?? 0] ?? '有效天数';
    }

    /**
     * 获取器：发放类型文本
     */
    public function getDistributeTypeTextAttr($value, $data)
    {
        $types = [
            self::DISTRIBUTE_TYPE_RECEIVE => '领取',
            self::DISTRIBUTE_TYPE_FULL => '消费满赠',
            self::DISTRIBUTE_TYPE_NEW => '新人',
            self::DISTRIBUTE_TYPE_BUY => '买赠',
            self::DISTRIBUTE_TYPE_FIRST => '首单赠送',
        ];
        return $types[$data['distribute_type'] ?? 0] ?? '领取';
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_CLOSE => '关闭',
            self::STATUS_OPEN => '开启',
            self::STATUS_INVALID => '失效',
        ];
        return $status[$data['status'] ?? 0] ?? '关闭';
    }

    /**
     * 关联：优惠券关联（商品/品类）
     */
    public function relations()
    {
        return $this->hasMany(StoreCouponRelation::class, 'coupon_id', 'id');
    }

    /**
     * 关联：发放记录
     */
    public function issues()
    {
        return $this->hasMany(StoreCouponIssue::class, 'coupon_id', 'id');
    }
}
