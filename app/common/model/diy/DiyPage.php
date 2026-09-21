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
use think\model\concern\SoftDelete;

/**
 * DIY页面模型
 */
class DiyPage extends Base
{
    use SoftDelete;

    /**
     * @var string
     */
    protected $name = 'diy_page';

    /**
     * 主键字段名
     * @var string
     */
    protected $pk = 'id';

    /**
     * JSON字段自动转换
     * @var array
     */
    protected $json = ['global_config'];

    /**
     * 关联组件
     */
    public function components()
    {
        return $this->hasMany(DiyComponent::class, 'diy_page_id')->order('sort', 'asc');
    }

    /**
     * 页面名称搜索器
     */
    public function searchPageNameAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('page_name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 页面类型搜索器
     */
    public function searchPageTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('page_type', $value);
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
     * 是否默认搜索器
     */
    public function searchIsDefaultAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_default', $value);
        }
    }
}
