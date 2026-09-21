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
use Swoole\Timer;
use think\facade\Log;

/**
 * WebSocket WorkerStart 事件监听器
 * 在 worker 0 上启动心跳检测定时器
 */
class WebSocketWorkerStartListener
{
    /**
     * 延迟启动秒数，避免随 WorkerStart 事件立即启动时
     * app 容器尚未完全就绪
     */
    const START_DELAY_MS = 5000;

    /**
     * 处理 WorkerStart 事件
     * @param string $name 服务器类型名称（如 "websocket"）
     */
    public function handle(string $name): void
    {
        // 仅 websocket 服务器启动心跳定时器
        if ($name !== 'websocket') {
            return;
        }

        // 仅 worker 0 执行，避免多 worker 重复
        try {
            /** @var \Swoole\Server $server */
            $server = app(\Swoole\Server::class);
        } catch (\Throwable) {
            Log::warning('[WebSocket] WorkerStart 无法获取 Swoole Server 实例');
            return;
        }

        if ($server->getWorkerId() !== 0) {
            return;
        }

        Log::info('[WebSocket] WorkerStart worker_id=' . $server->getWorkerId() . '，将在 ' . self::START_DELAY_MS . 'ms 后启动心跳检测');

        Timer::after(self::START_DELAY_MS, function () {
            $this->startHeartbeatTimer();
        });
    }

    /**
     * 启动心跳检测定时器
     */
    protected function startHeartbeatTimer(): void
    {
        try {
            $service = app()->make(WebSocketService::class);

            // 每 30 秒执行一次心跳检测
            Timer::tick(30000, function () use ($service) {
                try {
                    $service->heartbeatCheck();
                } catch (\Throwable $e) {
                    Log::warning('[WebSocket] 心跳检测异常：' . $e->getMessage());
                }
            });

            Log::info('[WebSocket] 心跳检测定时器已启动，间隔 30s');
        } catch (\Throwable $e) {
            Log::error('[WebSocket] 启动心跳检测定时器失败：' . $e->getMessage());
        }
    }
}
