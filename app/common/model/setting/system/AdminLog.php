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
use think\model\relation\HasOne;

/**
 * 日志
 */
class AdminLog extends Base
{
    /**
     * @var string
     */
    protected $name = 'admin_log';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * @var bool
     */
    protected bool $isPathStr = true;

    /**
     * 关联管理员
     * @return HasOne
     */
    public function adminUser()
    {
        return $this->hasOne(Admin::class, 'id', 'admin_id');
    }

    /**
     * 搜索
     * @param array $where
     * @return AdminLog
     */
    public function search(array $where)
    {
        return $this->when(isset($where['user_id']) && $where['user_id'], function ($query) use ($where) {
            $query->whereIn('admin_id', $where['user_id']);
        })->when(isset($where['path']) && '' !== $where['path'], function ($query) use ($where) {
            $query->where('path', 'like', '%' . $where['path'] . '%');
        })->when(isset($where['method']) && '' !== $where['method'], function ($query) use ($where) {
            $query->where('method', strtoupper($where['method']));
        })->when(isset($where['created_at']) && '' !== $where['created_at'], function ($query) use ($where) {
            set_model_time($query, $where['created_at']);
        });
    }
}