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
 * 用户资产模型
 */
class UserAsset extends Base
{
    /**
     * 表名
     */
    protected $name = 'user_asset';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 关联用户
     */
    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }

    /**
     * 关联资产记录
     */
    public function logs(): \think\model\relation\HasMany
    {
        return $this->hasMany(UserAssetLog::class, 'user_id', 'user_id');
    }

    /**
     * 搜索器：用户ID
     */
    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    /**
     * 搜索器：余额
     */
    public function searchBalanceAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('balance', '>=', $value);
        }
    }

    /**
     * 搜索器：积分
     */
    public function searchPointsAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('points', '>=', $value);
        }
    }

    /**
     * 搜索器：金币
     */
    public function searchCoinsAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('coins', '>=', $value);
        }
    }

    /**
     * 搜索器：经验值
     */
    public function searchExpAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('exp', '>=', $value);
        }
    }

    /**
     * 搜索器：可用佣金
     */
    public function searchCommissionAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('commission', '>=', $value);
        }
    }
}
