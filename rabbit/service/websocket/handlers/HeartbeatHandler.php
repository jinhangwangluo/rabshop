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

namespace Rabbit\service\websocket\handlers;

use Rabbit\service\websocket\WebSocketService;

/**
 * 心跳处理器
 */
class HeartbeatHandler implements MessageHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function handle(string|int $fd, array $data): void
    {
        // 返回 pong 响应
        $response = [
            'type'      => 'pong',
            'event'     => 'heartbeat',
            'code'      => 200,
            'message'   => 'pong',
            'timestamp' => time()
        ];

        $service = app()->make(WebSocketService::class);
        $service->sendToFd($fd, $response);
    }

    /**
     * @inheritDoc
     */
    public static function getEvents(): array
    {
        return ['heartbeat'];
    }
}
