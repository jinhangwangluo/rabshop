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

namespace app\common\model\order;

use app\common\model\Base;

/**
 * 订单支付记录模型
 */
class OrderPayRecord extends Base
{
    protected $name = 'order_pay_record';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'amount' => 'float',
        'request_payload' => 'json',
        'response_payload' => 'json',
        'notify_payload' => 'json',
    ];
}
