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
use think\model\concern\SoftDelete;

/**
 * 标签模型
 */
class Tag extends Base
{
    use SoftDelete;

    protected $name = 'tag';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'owner_id' => 'integer',
        'tag_type' => 'integer',
        'category_id' => 'integer',
        'effect_type' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
    ];

    public function searchNameAttr($query, $value): void
    {
        $name = trim((string)$value);
        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }
    }

    public function searchOwnerIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('owner_id', (int)$value);
        }
    }

    public function searchTagTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('tag_type', (int)$value);
        }
    }

    public function searchCategoryIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('category_id', (int)$value);
        }
    }

    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', (int)$value);
        }
    }
}
