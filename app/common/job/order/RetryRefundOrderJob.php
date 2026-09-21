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

namespace app\common\job\order;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;

class RetryRefundOrderJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;


    public function handle($data, $type)
    {
        // 旧退款重试服务已移除，保留空任务兼容历史队列。
        unset($data, $type);
    }

}
