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
 * 保存被拒收实收款及退款的永久幂等凭证。
 */
class OrderPaymentCompensation extends Base
{
    protected $name = 'order_payment_compensation';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $type = ['amount_fen' => 'integer', 'attempts' => 'integer'];
}
