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

namespace app\common\listener\websocket;

use Rabbit\service\websocket\WebSocketService;
use Swoole\WebSocket\Frame;
use think\facade\Log;
use think\swoole\Websocket;

/**
 * WebSocket OnMessage 事件监听器
 */
class WebSocketMessageListener
{
    /**
     * @var WebSocketService
     */
    protected $webSocketService;

    public function __construct()
    {
        $this->webSocketService = app()->make(WebSocketService::class);
    }

    /**
     * 处理 WebSocket 消息事件
     * @param Frame $payload 消息帧或消息内容
     */
    public function handle(Frame $payload): void
    {
        $fd = app()->make(Websocket::class)->getSender();
        $data = $payload->data;

        Log::debug("[WebSocket] 收到消息 fd={$fd}, data={$data}");

        try {
            // 调用 WebSocketService 处理消息
            $this->webSocketService->handleMessage($fd, $data);
        } catch (\Throwable $e) {
            Log::error("[WebSocket] 消息处理失败 fd={$fd}: " . $e->getMessage());
            
            // 发送错误响应
            $this->webSocketService->sendToFd($fd, [
                'type'      => 'error',
                'code'      => 'internal_error',
                'message'   => '消息处理失败：' . $e->getMessage(),
                'timestamp' => time()
            ]);
        }
    }
}
