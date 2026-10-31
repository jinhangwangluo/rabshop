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

namespace app\common\listener\message;

use app\common\job\message\BackendMessageJob;
use think\facade\Log;

/**
 * 后台消息事件监听器。
 */
class BackendMessageListener
{
    /**
     * 处理后台消息事件。
     *
     * @param array $payload 消息事件载荷。
     */
    public function handle(array $payload): void
    {
        try {
            BackendMessageJob::dispatch($payload);
        } catch (\Throwable $e) {
            Log::error('[BackendMessageListener] 投递失败：' . $e->getMessage() . '|' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 兼容事件调用。
     *
     * @param array $payload 消息事件载荷。
     */
    public function __invoke(array $payload): void
    {
        $this->handle($payload);
    }
}
