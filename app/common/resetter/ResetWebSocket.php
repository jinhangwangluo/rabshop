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

namespace app\common\resetter;

use think\App;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;
use Rabbit\service\websocket\WebSocketService;

/**
 * 每次 Swoole 请求后重置 WebSocket 处理器。
 */
class ResetWebSocket implements ResetterInterface
{
    public function handle(App $app, Sandbox $sandbox): void
    {
        WebSocketService::resetHandlers();
    }
}
