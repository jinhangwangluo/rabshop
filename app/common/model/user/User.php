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
 * 用户模型
 */
class User extends Base
{
    /**
     * 表名
     */
    protected $name = 'user';

    /**
     * 主键
     */
    protected $pk = 'uid';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 隐藏字段
     */
    protected $hidden = ['password'];

    /**
     * 关联用户资产
     */
    public function asset(): \think\model\relation\HasOne
    {
        return $this->hasOne(UserAsset::class, 'user_id', 'uid');
    }

    /**
     * 关联用户资产记录
     */
    public function assetLogs(): \think\model\relation\HasMany
    {
        return $this->hasMany(UserAssetLog::class, 'user_id', 'uid');
    }

    /**
     * 关联上级分销人
     */
    public function spreadUser(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(User::class, 'spread_uid', 'uid');
    }

    /**
     * 关联下级分销人
     */
    public function spreadChildren(): \think\model\relation\HasMany
    {
        return $this->hasMany(User::class, 'spread_uid', 'uid');
    }

    // ===========================
    //        搜索器
    // ===========================

    /**
     * 搜索器：昵称
     */
    public function searchNicknameAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('nickname', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：手机号
     */
    public function searchPhoneAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('phone', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：性别
     */
    public function searchSexAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('sex', $value);
        }
    }

    /**
     * 搜索器：注册时间
     */
    public function searchCreateTimeAttr($query, $value): void
    {
        if (is_array($value) && count($value) === 2) {
            $query->whereBetweenTime('create_time', $value[0], $value[1]);
        } elseif ($value !== '') {
            $query->where('create_time', 'between', $value);
        }
    }

    /**
     * 搜索器：分销权限
     */
    public function searchIsDistributionAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_distribution', $value);
        }
    }

    /**
     * 搜索器：分销权限覆盖
     */
    public function searchDistributionPermissionAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('distribution_permission', (int)$value);
        }
    }

    /**
     * 搜索器：上级分销人UID
     */
    public function searchSpreadUidAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('spread_uid', $value);
        }
    }

    /**
     * 搜索器：职业
     */
    public function searchOccupationAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('occupation', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：地址
     */
    public function searchAddressAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('address', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：是否禁止评论
     */
    public function searchIsCommentBanAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('is_comment_ban', (int)$value);
        }
    }
}
