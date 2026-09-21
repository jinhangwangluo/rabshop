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
 * 订单商户结算明细模型
 */
class OrderMerchantSettlement extends Base
{
    protected $name = 'order_merchant_settlement';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'pay_amount' => 'float',
        'commission_rate' => 'float',
        'commission_amount' => 'float',
        'merchant_income_amount' => 'float',
        'refunded_amount' => 'float',
        'reversed_commission_amount' => 'float',
        'reversed_merchant_income_amount' => 'float',
    ];
}
