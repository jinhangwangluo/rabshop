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

namespace app\common\model\diy;

use app\common\model\Base;
use app\common\model\setting\system\Category;
use think\model\concern\SoftDelete;

/**
 * DIY页面链接模型
 */
class DiyPageLink extends Base
{
    use SoftDelete;

    /**
     * @var string
     */
    protected $name = 'diy_page_link';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'cate_id');
    }

    /**
     * 分类搜索器
     */
    public function searchCateIdAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('cate_id', (int)$value);
        }
    }

    /**
     * 链接名称搜索器
     */
    public function searchLinkNameAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('link_name', 'like', '%' . trim($value) . '%');
        }
    }

    /**
     * 关键词搜索器
     */
    public function searchKeywordAttr($query, $value): void
    {
        if ($value !== '') {
            $keyword = trim($value);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereOr('link_name', 'like', '%' . $keyword . '%')
                    ->whereOr('link_url', 'like', '%' . $keyword . '%')
                    ->whereOr('remark', 'like', '%' . $keyword . '%');
            });
        }
    }

    /**
     * 链接类型搜索器
     */
    public function searchLinkTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('link_type', $value);
        }
    }

    /**
     * 平台类型搜索器
     */
    public function searchPlatformTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('platform_type', $value);
        }
    }

    /**
     * 状态搜索器
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', (int)$value);
        }
    }
}
