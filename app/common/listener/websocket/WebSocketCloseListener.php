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
use think\facade\Log;
use think\swoole\Websocket;

/**
 * WebSocket OnClose 事件监听器
 */
class WebSocketCloseListener
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
     * 处理 WebSocket 关闭事件
     */
    public function handle(): void
    {
        $fd = app()->make(Websocket::class)->getSender();

        Log::info("[WebSocket] 连接关闭 fd={$fd}");

        try {
            // 清理连接
            $this->webSocketService->closeConnection($fd);
        } catch (\Throwable $e) {
            Log::error("[WebSocket] 关闭连接失败 fd={$fd}: " . $e->getMessage());
        }
    }
}
