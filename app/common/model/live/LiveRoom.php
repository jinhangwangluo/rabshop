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
use app\common\model\setting\system\Category;

/**
 * 直播间模型
 */
class LiveRoom extends Base
{
    protected $name = 'live_room';
    protected $pk   = 'id';

    /**
     * 关联主播
     */
    public function anchor()
    {
        return $this->belongsTo(LiveAnchor::class, 'live_anchor_id');
    }

    /**
     * 所属商户
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 关联直播分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
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
     * 标题搜索器
     */
    public function searchTitleAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('title', 'like', '%' . $value . '%');
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
     * 移动端列表只展示未开始和直播中的直播间
     */
    public function searchMobileVisibleAttr($query, $value): void
    {
        if ((int)$value === 1) {
            $query->whereIn('status', [0, 1]);
        }
    }

    /**
     * 审核状态搜索器
     */
    public function searchAuditStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('audit_status', $value);
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
     * 主播ID搜索器
     */
    public function searchLiveAnchorIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('live_anchor_id', $value);
        }
    }

    /**
     * 热门搜索器
     */
    public function searchIsHotAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_hot', $value);
        }
    }

    /**
     * 直播分类搜索器
     */
    public function searchCategoryIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('category_id', $value);
        }
    }
}
