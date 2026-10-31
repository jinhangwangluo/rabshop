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
 * 转接日志模型
 */
class CsTransferLog extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_transfer_log';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

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
     * 搜索器：原客服ID
     */
    public function searchFromStaffIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('from_staff_id', $value);
        }
    }

    /**
     * 搜索器：目标客服ID
     */
    public function searchToStaffIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('to_staff_id', $value);
        }
    }

    /**
     * 关联：原客服
     */
    public function fromStaff()
    {
        return $this->belongsTo(CsStaff::class, 'from_staff_id', 'id');
    }

    /**
     * 关联：目标客服
     */
    public function toStaff()
    {
        return $this->belongsTo(CsStaff::class, 'to_staff_id', 'id');
    }

    /**
     * 关联：会话
     */
    public function session()
    {
        return $this->belongsTo(CsSession::class, 'session_id', 'session_id');
    }
}
