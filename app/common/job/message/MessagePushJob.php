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
use app\common\service\message\MessagePushService;

/**
 * 消息推送异步发送任务。
 */
class MessagePushJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * 执行消息推送任务。
     */
    public function handle(string $eventKey, array $payload = []): void
    {
        app()->make(MessagePushService::class)->sendEvent($eventKey, $payload);
    }
}
