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

namespace app\common\model\content;

use app\common\model\Base;
use app\common\model\merchant\Merchant;
use think\model\relation\HasOne;

/**
 * 专题模型
 */
class Special extends Base
{
    /**
     * 表名
     */
    protected $name = 'special';

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
        'special_type'   => 'integer',
        'price'          => 'float',
        'original_price' => 'float',
        'content_count'  => 'integer',
        'view_count'     => 'integer',
        'buy_count'      => 'integer',
        'is_member_only' => 'integer',
    ];

    /**
     * 搜索器：专题标题
     */
    public function searchTitleAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('title', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：专题分类（支持父级查子级）
     */
    public function searchCategoryIdAttr($query, $value): void
    {
        if ($value === '' || $value === null || (int)$value === 0) {
            return;
        }
        $categoryId = (int)$value;
        $childIds = \app\common\model\setting\system\Category::where('pid', $categoryId)->column('id');
        if (!empty($childIds)) {
            $query->whereIn('category_id', array_merge([$categoryId], $childIds));
        } else {
            $query->where('category_id', $categoryId);
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
     * 搜索器：内容来源
     */
    public function searchContentSourceAttr($query, $value): void
    {
        if ($value === '' || $value === null || $value === 'all') {
            return;
        }

        if ((string)$value === 'platform') {
            $query->where('merchant_id', 0);
            return;
        }

        if ((string)$value === 'merchant') {
            $query->where('merchant_id', '>', 0);
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
     * 搜索器：是否热门
     */
    public function searchIsHotAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_hot', $value);
        }
    }

    /**
     * 搜索器：是否会员专属
     */
    public function searchIsMemberOnlyAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_member_only', $value);
        }
    }

    /**
     * 搜索器：专题类型
     */
    public function searchSpecialTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('special_type', $value);
        }
    }

    /**
     * 搜索器：价格（免费/付费）
     */
    public function searchPriceAttr($query, $value)
    {
        if ($value !== '') {
            if ($value == 0) {
                $query->where('price', 0);
            } else {
                $query->where('price', '>', 0);
            }
        }
    }

    /**
     * 搜索器：关键词（标题/关键词/描述）
     */
    public function searchKeywordAttr($query, $value): void
    {
        $keyword = trim((string)$value);
        if ($keyword === '') {
            return;
        }

        $query->where(function ($subQuery) use ($keyword) {
            $subQuery->whereLike('title', '%' . $keyword . '%')
                ->whereOr('keywords', 'like', '%' . $keyword . '%')
                ->whereOr('description', 'like', '%' . $keyword . '%');
        });
    }

    /**
     * 搜索器：最低价格
     */
    public function searchMinPriceAttr($query, $value): void
    {
        if ($value === '' || $value === null || !is_numeric((string)$value)) {
            return;
        }

        $query->where('price', '>=', (float)$value);
    }

    /**
     * 搜索器：最高价格
     */
    public function searchMaxPriceAttr($query, $value): void
    {
        if ($value === '' || $value === null || !is_numeric((string)$value)) {
            return;
        }

        $query->where('price', '<=', (float)$value);
    }

    /**
     * 搜索器：审核状态
     */
    public function searchAuditStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('audit_status', $value);
        }
    }

    /**
     * 获取器：审核状态文本
     */
    public function getAuditStatusTextAttr($value, $data): string
    {
        $map = [0 => '待审核', 1 => '已通过', 2 => '已驳回', 3 => '已下架'];
        return $map[$data['audit_status'] ?? 0] ?? '待审核';
    }

    /**
     * 获取器：内容来源
     */
    public function getSourceTextAttr($value, $data): string
    {
        return ($data['merchant_id'] ?? 0) > 0 ? '商户发布' : '平台发布';
    }

    /**
     * 获取器：是否付费
     */
    public function getIsPaidAttr($value, $data): bool
    {
        return ($data['special_type'] ?? 0) == 1;
    }

    /**
     * 获取器：专题类型文本
     */
    public function getSpecialTypeTextAttr($value, $data): string
    {
        return ($data['special_type'] ?? 0) == 1 ? '付费' : '免费';
    }

    /**
     * 关联：所属商户
     * @return HasOne
     */
    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'id', 'merchant_id');
    }

    /**
     * 关联：专题内容列表
     */
    public function specialContents()
    {
        return $this->hasMany(SpecialContent::class, 'special_id', 'id')
            ->order('sort', 'asc');
    }

    /**
     * 关联：专题关联的内容（通过中间表）
     */
    public function articles()
    {
        return $this->belongsToMany(
            Article::class,
            SpecialContent::class,
            'article_id',
            'special_id'
        );
    }
}
