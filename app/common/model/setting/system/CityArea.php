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

namespace app\common\model\setting\system;

use app\common\model\Base;

class CityArea extends Base
{

    /**
     * @var string
     */
    protected $name = 'city_area';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * @var bool
     */
    protected bool $isPathStr = true;

    /**
     * 搜索
     * @param array $where
     * @return CityArea
     */
    public function search(array $where)
    {
        return $this->when(isset($where['id']) && '' !== $where['id'], fn($q) => $q->where('id', $where['id']))
            ->when(isset($where['ids']) && '' !== $where['ids'], fn($q) => $q->whereIn('id', $where['ids']))
            ->when(isset($where['pid']) && '' !== $where['pid'], fn($q) => $q->where('parent_id', $where['pid']))
            ->when(isset($where['parent_id']) && '' !== $where['parent_id'], fn($q) => $q->where('parent_id', $where['parent_id']))
            ->when(isset($where['level']) && '' !== $where['level'], fn($q) => $q->where('level', $where['level']))
            ->when(isset($where['status']) && '' !== $where['status'], fn($q) => $q->where('status', $where['status']))
            ->when(isset($where['name']) && '' !== $where['name'], fn($q) => $q->whereLike('name', '%' . $where['name'] . '%'));
    }
}
