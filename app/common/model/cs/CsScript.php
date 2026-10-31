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

namespace app\common\model\cs;

use app\common\model\Base;

/**
 * 话术模型
 */
class CsScript extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_script';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 搜索器：分类ID
     */
    public function searchCategoryIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('category_id', $value);
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
     * 搜索器：标题
     */
    public function searchTitleAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('title', 'like', '%' . $value . '%');
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
     * 搜索器：快捷键
     */
    public function searchShortcutAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('shortcut', 'like', '%' . $value . '%');
        }
    }

    /**
     * 关联：分类
     */
    public function category()
    {
        return $this->belongsTo(\app\common\model\setting\system\Category::class, 'category_id', 'id');
    }
}
