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
 * 客服会话模型
 */
class CsSession extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_session';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 会话状态常量
     */
    const STATUS_WAITING = 0;      // 待接入
    const STATUS_SERVING = 1;      // 服务中
    const STATUS_FINISHED = 2;     // 已结束

    /**
     * 服务类型常量
     */
    const SERVICE_TYPE_AI = 1;     // AI优先
    const SERVICE_TYPE_HUMAN = 2;  // 人工

    /**
     * 搜索器：会话ID
     */
    public function searchSessionIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('session_id', $value);
        }
    }

    /**
     * 搜索器：用户ID
     */
    public function searchUserIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('user_id', $value);
        }
    }

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
     * 搜索器：客服ID
     */
    public function searchStaffIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('staff_id', $value);
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
     * 搜索器：服务类型
     */
    public function searchServiceTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('service_type', $value);
        }
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_WAITING => '待接入',
            self::STATUS_SERVING => '服务中',
            self::STATUS_FINISHED => '已结束',
        ];
        return $status[$data['status'] ?? 0] ?? '未知';
    }

    /**
     * 获取器：服务类型文本
     */
    public function getServiceTypeTextAttr($value, $data)
    {
        $types = [
            self::SERVICE_TYPE_AI => 'AI优先',
            self::SERVICE_TYPE_HUMAN => '人工',
        ];
        return $types[$data['service_type'] ?? 1] ?? '未知';
    }

    /**
     * 关联：客服人员
     */
    public function staff()
    {
        return $this->belongsTo(CsStaff::class, 'staff_id', 'id');
    }

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(\app\common\model\user\User::class, 'user_id', 'uid');
    }

    /**
     * 关联：消息
     */
    public function messages()
    {
        return $this->hasMany(CsMessage::class, 'session_id', 'session_id');
    }
}
