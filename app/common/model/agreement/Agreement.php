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

namespace app\common\model\agreement;

use app\common\model\Base;

/**
 * 协议模型
 */
class Agreement extends Base
{
    /** @var string 会员协议 */
    const TYPE_MEMBER = 'member';

    /**
     * 数据表名
     */
    protected $name = 'agreement';

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
        'status' => 'integer',
        'sort' => 'integer',
    ];

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
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：标题
     */
    public function searchTitleAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('title', 'like', '%' . $value . '%');
        }
    }
}
