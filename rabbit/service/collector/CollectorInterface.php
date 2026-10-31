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

namespace Rabbit\service\collector;

use Rabbit\interfaces\ConfigInterface;

/**
 * 商品采集驱动接口。
 */
interface CollectorInterface
{
    /**
     * 设置配置服务。
     *
     * @param ConfigInterface $configService 配置服务。
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static;

    /**
     * 获取服务商标识。
     *
     * @return string
     */
    public function getProvider(): string;

    /**
     * 采集商品详情。
     *
     * @param string $numIid 第三方商品 ID。
     * @return array<string,mixed> 第三方商品详情。
     */
    public function collect(string $numIid): array;

    /**
     * 按平台和商品 ID 采集商品详情。
     *
     * @param string $platform 平台标识，例如 taobao、jd。
     * @param string $numIid 第三方商品 ID。
     * @return array<string,mixed> 第三方商品详情。
     */
    public function collectByPlatform(string $platform, string $numIid): array;

    /**
     * 从商品链接识别平台。
     *
     * @param string $url 第三方商品链接或商品 ID。
     * @return string 平台标识。
     */
    public function getPlatformByUrl(string $url): string;

    /**
     * 采集商品详情。
     *
     * @param string $url 第三方商品链接或 ID。
     * @return array<string,mixed> 第三方商品详情。
     */
    public function collectByUrl(string $url): array;

    /**
     * 调用供应商接口。
     *
     * @param string $platform 平台标识，例如 taobao。
     * @param string $apiName 接口名，例如 item_get。
     * @param array<string,mixed> $query 业务查询参数。
     * @return array<string,mixed> 供应商原始响应。
     */
    public function request(string $platform, string $apiName, array $query = []): array;
}
