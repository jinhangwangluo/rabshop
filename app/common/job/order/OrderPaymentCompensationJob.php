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

namespace app\common\job\order;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\service\order\OrderPaymentCompensationService;

/**
 * 处理迟到实收款的退款任务，使用宿主永久凭证保证重试与幂等。
 */
class OrderPaymentCompensationJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 执行一项迟到实收款退款补偿任务。
     *
     * @param int $id 宿主永久实收退款补偿记录编号。
     * @return bool 本项迟到实收款补偿退款是否已完成。
     */
    public function handle(int $id): bool
    {
        return app()->make(OrderPaymentCompensationService::class)->retryOne($id);
    }
}
