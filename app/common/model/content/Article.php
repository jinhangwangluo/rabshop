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

use app\common\enum\ContentTypeEnum;
use app\common\model\Base;
use app\common\model\merchant\Merchant;
use app\common\model\setting\system\Category;
use think\model\relation\HasOne;

/**
 * 内容模型（支持图文/文字/小说/图片/音频/视频）
 */
class Article extends Base
{
    /**
     * 表名
     */
    protected $name = 'article';

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
        'content_type'   => 'integer',
        'view_count'     => 'integer',
        'virtual_learn_count' => 'integer',
        'like_count'     => 'integer',
        'media_duration' => 'integer',
        'word_count'     => 'integer',
        'chapter_count'  => 'integer',
        'is_trial'       => 'integer',
        'is_member_only' => 'integer',
        'price'          => 'float',
        'original_price' => 'float',
        'gift_points'    => 'integer',
        'images'         => 'array',
    ];

    /**
     * 搜索器：文章标题
     */
    public function searchTitleAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('title', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：分类ID（支持查询父分类时包含所有子分类数据）
     */
    public function searchArticleCategoryIdAttr($query, $value): void
    {
        if ($value === '' || $value === null || $value === 0) {
            return;
        }
        $categoryId = (int)$value;
        // 查询该分类下的所有子分类 ID
        $childIds = \app\common\model\setting\system\Category::where('pid', $categoryId)
            ->column('id');
        if (!empty($childIds)) {
            // 父级：匹配自身及所有子分类
            $ids = array_merge([$categoryId], $childIds);
            $query->whereIn('article_category_id', $ids);
        } else {
            // 叶子节点：直接匹配
            $query->where('article_category_id', $categoryId);
        }
    }

    /**
     * 搜索器：商户ID
     */
    public function searchMerIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('mer_id', $value);
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
            $query->where('mer_id', 0);
            return;
        }

        if ((string)$value === 'merchant') {
            $query->where('mer_id', '>', 0);
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
     * 搜索器：审核状态
     */
    public function searchAuditStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('audit_status', $value);
        }
    }

    /**
     * 搜索器：内容类型
     */
    public function searchContentTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('content_type', $value);
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
     * 搜索器：是否轮播图
     */
    public function searchIsBannerAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_banner', $value);
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
     * 搜索器：价格区间
     */
    public function searchPriceAttr($query, $value)
    {
        if ($value !== '') {
            if (is_array($value)) {
                [$min, $max] = $value;
                $query->whereBetween('price', [$min, $max]);
            } else {
                // 0=免费，1=付费
                if ($value == 0) {
                    $query->where('price', 0);
                } else {
                    $query->where('price', '>', 0);
                }
            }
        }
    }

    /**
     * 搜索器：是否试看
     */
    public function searchIsTrialAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_trial', $value);
        }
    }

    /**
     * 获取器：内容类型文本
     */
    public function getContentTypeTextAttr($value, $data): string
    {
        return ContentTypeEnum::getName((int)($data['content_type'] ?? 1));
    }

    /**
     * 获取器：审核状态文本
     */
    public function getAuditStatusTextAttr($value, $data): string
    {
        $status = [0 => '待审核', 1 => '已通过', 2 => '已驳回', 3 => '已下架'];
        return $status[(int)($data['audit_status'] ?? 1)] ?? '已通过';
    }

    /**
     * 获取器：内容来源
     */
    public function getSourceTextAttr($value, $data): string
    {
        return ($data['mer_id'] ?? 0) > 0 ? '商户发布' : '平台发布';
    }

    /**
     * 获取器：是否付费
     */
    public function getIsPaidAttr($value, $data): bool
    {
        return ($data['price'] ?? 0) > 0;
    }

    /**
     * 关联：内容详情（图文/文字/小说章节）
     */
    public function articleContent()
    {
        return $this->hasOne(ArticleContent::class, 'nid', 'id');
    }

    /**
     * 关联：所属商户
     * @return HasOne
     */
    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'id', 'mer_id');
    }

    /**
     * 关联：所属分类
     * @return HasOne
     */
    public function articleCategory()
    {
        return $this->hasOne(Category::class, 'id', 'article_category_id');
    }

    /**
     * 关联：所属专题（通过专题内容关联）
     */
    public function specials()
    {
        return $this->belongsToMany(
            Special::class,
            SpecialContent::class,
            'special_id',
            'article_id'
        );
    }
}
