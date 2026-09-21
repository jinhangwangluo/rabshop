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
use app\common\model\merchant\Merchant;

/**
 * 主播模型
 */
class LiveAnchor extends Base
{
    protected $name = 'live_anchor';
    protected $pk   = 'id';

    protected $type = [
        'commission_rate' => 'float',
    ];

    /**
     * 所属商户
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 管理端列表 Tab：all|platform|merchant|pending|violation
     */
    public function searchTabAttr($query, $value): void
    {
        if ($value === '' || $value === 'all') {
            return;
        }
        match ($value) {
            'platform' => $query->where('merchant_id', 0),
            'merchant' => $query->where('merchant_id', '>', 0),
            'pending' => $query->where('audit_status', 0),
            'violation' => $query->where('violation_count', '>', 0),
            default => null,
        };
    }

    /**
     * 审核状态文字搜索器
     */
    public function searchAuditStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('audit_status', $value);
        }
    }

    /**
     * 状态搜索器
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
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

    /**
     * 主播姓名搜索器
     */
    public function searchNameAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }
}
