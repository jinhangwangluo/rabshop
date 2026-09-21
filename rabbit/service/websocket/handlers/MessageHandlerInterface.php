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

/**
 * 消息处理器接口
 */
interface MessageHandlerInterface
{
    /**
     * 处理消息
     * @param string|int $fd 连接描述符
     * @param array $data 消息数据
     * @return void
     */
    public function handle(string|int $fd, array $data): void;

    /**
     * 获取支持的事件类型列表
     * @return array
     */
    public static function getEvents(): array;
}
