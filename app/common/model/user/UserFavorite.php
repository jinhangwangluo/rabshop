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

namespace app\common\model\user;

use app\common\model\Base;

/**
 * 用户收藏模型
 */
class UserFavorite extends Base
{
    /**
     * 表名
     */
    protected $name = 'user_favorite';

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
        'user_id' => 'integer',
        'target_id' => 'integer',
        'merchant_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 搜索器：用户ID。
     */
    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    /**
     * 搜索器：收藏对象类型。
     */
    public function searchTargetTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('target_type', $value);
        }
    }

    /**
     * 搜索器：收藏对象ID。
     */
    public function searchTargetIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('target_id', $value);
        }
    }

    /**
     * 搜索器：状态。
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }
}
