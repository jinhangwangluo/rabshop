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

namespace app\common\service\websocket;

use think\Request;

/**
 * WebSocket 地址服务。
 */
class WebSocketUrlService
{
    /**
     * 获取客户端连接地址。
     *
     * @param Request|null $request 当前请求，配置为空时用于兜底生成地址。
     * @return string WebSocket 连接地址。
     */
    public function getClientUrl(?Request $request = null): string
    {
        $configuredUrl = trim((string)config('websocket.server_url', ''));
        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        if ($request === null) {
            return '';
        }

        $protocol = $request->isSsl() ? 'wss://' : 'ws://';
        return $protocol . $request->host() . '/ws';
    }
}
