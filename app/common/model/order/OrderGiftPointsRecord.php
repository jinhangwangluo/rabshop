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

namespace app\common\model\order;

use app\common\model\Base;

/**
 * 订单赠送积分记录模型。
 */
class OrderGiftPointsRecord extends Base
{
    /**
     * 表名。
     */
    protected $name = 'order_gift_points_record';

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
        'order_id' => 'integer',
        'user_id' => 'integer',
        'order_type' => 'integer',
        'base_points' => 'integer',
        'multiplier' => 'float',
        'final_points' => 'integer',
        'detail_json' => 'json',
        'status' => 'integer',
    ];

    public const STATUS_RECORDED = 0;
    public const STATUS_FROZEN = 1;
    public const STATUS_RELEASED = 2;
    public const STATUS_REFUNDED = 3;
}
