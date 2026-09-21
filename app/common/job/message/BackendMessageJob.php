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

namespace app\common\job\message;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\service\message\BackendMessageService;

/**
 * 后台消息异步写入与推送任务。
 */
class BackendMessageJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 执行后台消息任务。
     *
     * @param array $payload 后台消息事件载荷。
     */
    public function handle(array $payload): void
    {
        app()->make(BackendMessageService::class)->createFromPayload($payload);
    }
}
