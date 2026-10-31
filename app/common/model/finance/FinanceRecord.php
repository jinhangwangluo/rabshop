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

namespace app\common\model\finance;

use app\common\model\Base;

/**
 * 财务记录模型
 */
class FinanceRecord extends Base
{
    /**
     * 表名
     */
    protected $name = 'finance_record';

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
        'amount' => 'float',
        'live_room_id' => 'integer',
        'live_gift_record_id' => 'integer',
        'live_anchor_id' => 'integer',
    ];

    /**
     * 搜索器：父流水ID
     */
    public function searchPidAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('pid', $value);
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
     * 搜索器：类型
     */
    public function searchTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('type', $value);
        }
    }

    /**
     * 搜索器：变动类型
     */
    public function searchChangeTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('change_type', $value);
        }
    }

    /**
     * 搜索器：资金状态
     */
    public function searchFundStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('fund_status', $value);
        }
    }

    /**
     * 搜索器：直播间ID
     */
    public function searchLiveRoomIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('live_room_id', $value);
        }
    }

    /**
     * 搜索器：直播礼物流水ID
     */
    public function searchLiveGiftRecordIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('live_gift_record_id', $value);
        }
    }

    /**
     * 搜索器：主播ID
     */
    public function searchLiveAnchorIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('live_anchor_id', $value);
        }
    }

    /**
     * 获取器：类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        $types = [
            'order' => '订单收入',
            'order_summary' => '订单聚合流水',
            'withdraw' => '提现',
            'recharge' => '充值',
            'refund' => '退款',
            'refund_summary' => '退款聚合流水',
            'commission' => '平台抽佣',
            'coupon_subsidy' => '平台券补贴',
            'live_gift_summary' => '直播礼物聚合流水',
            'live_gift_platform' => '直播礼物平台抽成',
            'live_gift_merchant' => '直播礼物商户抽成',
            'live_gift_anchor' => '直播礼物主播所得',
        ];
        return $types[$data['type'] ?? ''] ?? '其他';
    }

    /**
     * 获取器：变动类型文本
     */
    public function getChangeTypeTextAttr($value, $data)
    {
        $types = [
            'in' => '收入',
            'out' => '支出',
        ];
        return $types[$data['change_type'] ?? ''] ?? '其他';
    }

    /**
     * 获取器：资金状态文本
     */
    public function getFundStatusTextAttr($value, $data)
    {
        $types = [
            'frozen' => '冻结中',
            'available' => '可提现',
            'reversed' => '已冲正',
        ];
        return $types[$data['fund_status'] ?? 'available'] ?? '可提现';
    }
}
