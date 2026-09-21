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

namespace app\common\service\plugin\contract;

/**
 * 加密网关 HTTPS 传输端口。
 */
interface PluginGatewayTransportInterface
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed> $options
     * @return string 网关返回的 application/jose 密文
     */
    public function request(
        string $url,
        string $body,
        array $headers = [],
        array $options = []
    ): string;
}
