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
 * WebSocket OnOpen 事件监听器
 */
class WebSocketOpenListener
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
     * 处理 WebSocket 连接打开事件
     * @param \Swoole\Http\Request $request
     */
    public function handle($request): void
    {
        $fd = app()->make(Websocket::class)->getSender();
        $token = $request->get('token');
        $ip = (string)($request->server('remote_addr') ?? '0.0.0.0');

        Log::info("[WebSocket] 新连接请求 fd={$fd}, ip={$ip}");

        if (empty($token)) {
            Log::error("[WebSocket] 连接失败：缺少 token");
            $this->sendError($fd, 'missing_token', '缺少认证 token');
            return;
        }

        // 验证 token
        $success = $this->webSocketService->authenticate($fd, $token, $ip);
        if (!$success) {
            Log::error("[WebSocket] 认证失败 fd={$fd}");
            $this->sendError($fd, 'auth_failed', '认证失败，请检查 token 是否有效');
            return;
        }

        // 发送欢迎消息
        $this->sendSuccess($fd, [
            'message' => '连接成功',
            'time'    => date('Y-m-d H:i:s')
        ]);

        Log::info("[WebSocket] 连接认证成功 fd={$fd}");
    }

    /**
     * 发送错误消息
     */
    protected function sendError(string|int $fd, string $code, string $message): void
    {
        $this->webSocketService->rejectConnection($fd, $code, $message);
    }

    /**
     * 发送成功消息
     */
    protected function sendSuccess(string|int $fd, array $data): void
    {
        $this->webSocketService->sendToFd($fd, [
            'type'      => 'connected',
            'event'     => 'connect',
            'code'      => 200,
            'data'      => $data,
            'message'   => 'success',
            'timestamp' => time()
        ]);
    }
}
