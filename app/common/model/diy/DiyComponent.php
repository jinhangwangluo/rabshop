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
 * DIY组件模型
 */
class DiyComponent extends Base
{
    use SoftDelete;

    /**
     * @var string
     */
    protected $name = 'diy_component';

    /**
     * 主键字段名
     * @var string
     */
    protected $pk = 'id';

    /**
     * JSON字段自动转换
     * @var array
     */
    protected $json = ['config'];

    /**
     * 关联页面
     */
    public function page()
    {
        return $this->belongsTo(DiyPage::class, 'diy_page_id');
    }

    /**
     * 页面ID搜索器
     */
    public function searchDiyPageIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('diy_page_id', $value);
        }
    }

    /**
     * 组件类型搜索器
     */
    public function searchComponentTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('component_type', $value);
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
}
