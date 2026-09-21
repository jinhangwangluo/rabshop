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

namespace app\common\model\cs;

use app\common\model\Base;

/**
 * 客服人员模型
 */
class CsStaff extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_staff';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 状态常量
     */
    const STATUS_OFFLINE = 0;  // 离线
    const STATUS_ONLINE = 1;   // 在线
    const STATUS_BUSY = 2;     // 忙碌

    /**
     * 搜索器：商户ID
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：管理员ID
     */
    public function searchAdminIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('admin_id', $value);
        }
    }

    /**
     * 搜索器：客服工号
     */
    public function searchStaffNoAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('staff_no', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：是否在线
     */
    public function searchIsOnlineAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_online', $value);
        }
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_OFFLINE => '离线',
            self::STATUS_ONLINE => '在线',
            self::STATUS_BUSY => '忙碌',
        ];
        return $status[$data['status'] ?? 0] ?? '未知';
    }

    /**
     * 关联：管理员
     */
    public function admin()
    {
        return $this->belongsTo(\app\common\model\setting\system\Admin::class, 'admin_id', 'id');
    }

    /**
     * 关联：会话
     */
    public function sessions()
    {
        return $this->hasMany(CsSession::class, 'staff_id', 'id');
    }
}
