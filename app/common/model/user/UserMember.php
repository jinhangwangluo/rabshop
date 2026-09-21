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
 * 用户会员模型
 */
class UserMember extends Base
{
    /** @var int 已过期 */
    public const STATUS_EXPIRED = 0;
    /** @var int 有效中 */
    public const STATUS_ACTIVE = 1;

    /**
     * 数据表名
     */
    protected $name = 'user_member';

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
        'status' => 'integer',
    ];

    /**
     * benefit_snapshot 获取器
     */
    public function getBenefitSnapshotAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }

    /**
     * benefit_snapshot 修改器
     */
    public function setBenefitSnapshotAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
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
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }
}
