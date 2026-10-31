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
 * 优惠券发放记录模型
 */
class StoreCouponIssue extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'store_coupon_issue';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 发放类型
     */
    const ISSUE_TYPE_SEND = 1;      // 后台发送
    const ISSUE_TYPE_FULL = 2;      // 满赠
    const ISSUE_TYPE_NEW = 3;       // 新人
    const ISSUE_TYPE_BUY = 4;       // 买赠
    const ISSUE_TYPE_FIRST = 5;     // 首单赠送

    /**
     * 状态
     */
    const STATUS_PENDING = 0;       // 待发放
    const STATUS_ISSUING = 1;       // 发放中
    const STATUS_COMPLETED = 2;     // 已发放完成
    const STATUS_CANCELLED = 3;     // 已取消

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
    public function searchIssueTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('issue_type', $value);
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
     * 搜索器：批次号
     */
    public function searchBatchNoAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('batch_no', 'like', '%' . $value . '%');
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
     * 获取器：发放类型文本
     */
    public function getIssueTypeTextAttr($value, $data)
    {
        $types = [
            self::ISSUE_TYPE_SEND => '后台发送',
            self::ISSUE_TYPE_FULL => '满赠',
            self::ISSUE_TYPE_NEW => '新人',
            self::ISSUE_TYPE_BUY => '买赠',
            self::ISSUE_TYPE_FIRST => '首单赠送',
        ];
        return $types[$data['issue_type'] ?? 1] ?? '后台发送';
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_PENDING => '待发放',
            self::STATUS_ISSUING => '发放中',
            self::STATUS_COMPLETED => '已发放完成',
            self::STATUS_CANCELLED => '已取消',
        ];
        return $status[$data['status'] ?? 0] ?? '待发放';
    }

    /**
     * 关联：优惠券
     */
    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'coupon_id', 'id');
    }

    /**
     * 关联：用户优惠券
     */
    public function userCoupons()
    {
        return $this->hasMany(StoreCouponUser::class, 'issue_id', 'id');
    }
}
