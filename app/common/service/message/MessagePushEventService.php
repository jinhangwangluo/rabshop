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

namespace app\common\service\message;

use app\common\job\message\MessagePushJob;
use think\facade\Log;

/**
 * 消息推送事件入口服务。
 */
class MessagePushEventService
{
    /**
     * 投递推送事件。
     */
    public function dispatch(string $eventKey, array $payload = []): void
    {
        try {
            if (!app()->make(MessagePushDefinitionService::class)->hasEvent($eventKey)) {
                return;
            }
            MessagePushJob::dispatch($eventKey, $payload);
        } catch (\Throwable $throwable) {
            Log::error('[MessagePushEventService] 投递失败：' . $throwable->getMessage() . '|' . json_encode([
                'event_key' => $eventKey,
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
