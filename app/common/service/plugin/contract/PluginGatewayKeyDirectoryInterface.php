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
 * 官网安全网关公钥目录读取端口。
 */
interface PluginGatewayKeyDirectoryInterface
{
    /**
     * 读取官网公开的网关密钥目录。
     *
     * @param string $url HTTPS 密钥目录地址。
     * @param array<string,mixed> $options 可覆盖请求超时和 TLS 校验选项。
     * @return array<string,mixed> 已解析的 JSON 对象。
     */
    public function fetch(string $url, array $options = []): array;
}
