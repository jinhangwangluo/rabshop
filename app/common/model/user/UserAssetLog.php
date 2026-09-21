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
 * 用户资产记录模型
 */
class UserAssetLog extends Base
{
    /**
     * 表名
     */
    protected $name = 'user_asset_log';

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
     * 关联来源用户（如下级用户）
     */
    public function sourceUser(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id', 'uid');
    }

    // ===========================
    //        搜索器
    // ===========================

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
     * 搜索器：类型
     */
    public function searchTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('type', $value);
        }
    }

    /**
     * 搜索器：变动类型
     */
    public function searchChangeTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('change_type', $value);
        }
    }

    /**
     * 搜索器：来源类型
     */
    public function searchSourceTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('source_type', $value);
        }
    }

    /**
     * 搜索器：关联订单号
     */
    public function searchOrderNoAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('order_no', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：来源用户ID
     */
    public function searchSourceUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('source_user_id', $value);
        }
    }

    /**
     * 搜索器：创建时间
     */
    public function searchCreateTimeAttr($query, $value): void
    {
        if (is_array($value) && count($value) === 2) {
            $query->whereBetweenTime('create_time', $value[0], $value[1]);
        } elseif ($value !== '') {
            $query->where('create_time', 'between', $value);
        }
    }
}
