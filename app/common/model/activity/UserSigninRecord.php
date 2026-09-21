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

declare(strict_types=1);

namespace app\common\model\activity;

use app\common\model\Base;

/**
 * 用户签到记录模型。
 */
class UserSigninRecord extends Base
{
    /**
     * 表名。
     */
    protected $name = 'user_signin_record';

    /**
     * 主键。
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳。
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换。
     */
    protected $type = [
        'user_id' => 'integer',
        'reward_points' => 'integer',
        'streak_days' => 'integer',
        'cycle_day' => 'integer',
    ];

    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    public function searchSignDateAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('sign_date', $value);
        }
    }
}
