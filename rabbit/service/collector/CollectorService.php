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
use Rabbit\service\collector\driver\OneBound;

/**
 * 商品采集服务入口。
 *
 * 通过 provider 驱动隔离不同电商平台，后续可扩展京东、拼多多等采集实现。
 */
use Rabbit\traits\DriverRegistryTrait;

class CollectorService
{
    use DriverRegistryTrait;

    /** 插件 manifest 中的声明字段 */
    private const MANIFEST_KEY = 'collector_drivers';

    /** 万邦采集服务商。 */
    public const PROVIDER_WANBANG = 'wanbang';

    /** OneBound 采集服务商。 */
    public const PROVIDER_ONEBOUND = 'onebound';

    /** 淘宝采集别名。 */
    public const PROVIDER_TAOBAO = 'taobao';

    /**
     * 配置服务。
     */
    protected ?ConfigInterface $configServe = null;

    /**
     * 设置配置服务。
     *
     * @param ConfigInterface $configService 配置服务。
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    /**
     * 获取采集驱动。
     *
     * @param string|null $provider 服务商标识。
     * @return CollectorInterface
     * @throws CollectorException 服务商不支持时抛出。
     */
    public function driver(?string $provider = null): CollectorInterface
    {
        $key = $provider ?: self::PROVIDER_WANBANG;
        $registered = self::resolveRegistered($key);
        if ($registered !== null) {
            if (!$registered instanceof CollectorInterface) {
                throw new CollectorException('采集驱动实现无效');
            }

            return $registered;
        }

        return match ($key) {
            self::PROVIDER_WANBANG,
            self::PROVIDER_ONEBOUND,
            self::PROVIDER_TAOBAO => $this->makeWanBang(),
            default => throw new CollectorException('不支持的商品采集服务商'),
        };
    }

    /**
     * 采集商品详情。
     *
     * @param string $numIid 第三方商品 ID。
     * @param string|null $provider 服务商标识。
     * @return array<string,mixed>
     * @throws CollectorException 采集失败时抛出。
     */
    public function collect(string $numIid, ?string $provider = null): array
    {
        return $this->driver($provider)->collect($numIid);
    }

    /**
     * 按平台和商品 ID 采集商品详情。
     *
     * @param string $platform 平台标识，例如 taobao、jd。
     * @param string $numIid 第三方商品 ID。
     * @param string|null $provider 服务商标识。
     * @return array<string,mixed>
     */
    public function collectByPlatform(
        string $platform,
        string $numIid,
        ?string $provider = null
    ): array {
        return $this->driver($provider)->collectByPlatform($platform, $numIid);
    }

    /**
     * 通过链接采集商品详情。
     *
     * @param string $url 第三方商品链接或 ID。
     * @param string|null $provider 服务商标识。
     * @return array<string,mixed>
     * @throws CollectorException 采集失败时抛出。
     */
    public function collectByUrl(string $url, ?string $provider = null): array
    {
        return $this->driver($provider)->collectByUrl($url);
    }

    /**
     * 根据商品链接识别平台。
     *
     * @param string $url 第三方商品链接或商品 ID。
     * @param string|null $provider 服务商标识。
     * @return string 平台标识。
     */
    public function getPlatformByUrl(string $url, ?string $provider = null): string
    {
        return $this->driver($provider)->getPlatformByUrl($url);
    }

    /**
     * 调用万邦接口。
     *
     * @param string $platform 平台标识。
     * @param string $apiName 接口名。
     * @param array<string,mixed> $query 业务参数。
     * @param string|null $provider 服务商标识。
     * @return array<string,mixed>
     */
    public function request(
        string $platform,
        string $apiName,
        array $query = [],
        ?string $provider = null
    ): array
    {
        return $this->driver($provider)->request($platform, $apiName, $query);
    }

    /**
     * 兼容旧调用名，统一转发到通用供应商请求。
     *
     * @param string $platform 平台标识。
     * @param string $apiName 接口名。
     * @param array<string,mixed> $query 业务参数。
     * @param string|null $provider 服务商标识。
     * @return array<string,mixed>
     */
    public function collectByApi(
        string $platform,
        string $apiName,
        array $query = [],
        ?string $provider = null
    ): array {
        return $this->request($platform, $apiName, $query, $provider);
    }

    /**
     * 创建万邦采集驱动。
     *
     * @return OneBound
     */
    protected function makeWanBang(): OneBound
    {
        $driver = new OneBound();
        if ($this->configServe !== null) {
            $driver->setConfigServe($this->configServe);
        }
        return $driver;
    }
}
