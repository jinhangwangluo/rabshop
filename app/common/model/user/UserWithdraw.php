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
 * 用户提现记录模型
 */
class UserWithdraw extends Base
{
    protected $name = 'user_withdraw';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $createTime = 'created_at';

    protected $updateTime = 'updated_at';

    protected $type = [
        'amount'        => 'float',
        'fee'           => 'float',
        'actual_amount' => 'float',
    ];

    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

    public function searchSourceAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('source', $value);
        }
    }

    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    public function getStatusTextAttr($value, $data): string
    {
        $map = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];
        return $map[$data['status'] ?? 0] ?? '未知';
    }
}
