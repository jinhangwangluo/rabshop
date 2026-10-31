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

namespace Rabbit\service\express;

use Rabbit\interfaces\ConfigInterface;
use Rabbit\service\express\driver\AliyunMarket;
use think\facade\Cache;
use Throwable;

/**
 * 物流查询服务入口
 */
class ExpressService
{
    use \Rabbit\traits\DriverRegistryTrait;

    /** 插件 manifest 中的声明字段 */
    private const MANIFEST_KEY = 'express_providers';

    public const PROVIDER_ALIYUN_MARKET = 'aliyun_market';
    private const QUERY_BUFFER_SECONDS = 120;

    protected ?ConfigInterface $configServe = null;

    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    /**
     * 获取物流查询驱动
     *
     * @throws ExpressException
     */
    public function driver(?string $provider = null, array $config = []): ExpressInterface
    {
        $provider = $provider ?: self::PROVIDER_ALIYUN_MARKET;
        // 运行期注册的物流查询实现优先（插件声明或宿主 extend），未命中再走内置实现
        $driver = self::resolveRegistered($provider, $config);
        if ($driver === null) {
            $driver = match ($provider) {
                self::PROVIDER_ALIYUN_MARKET => new AliyunMarket($config),
                default => throw new ExpressException("不支持的物流查询服务商：[{$provider}]"),
            };
        } elseif (!$driver instanceof ExpressInterface) {
            throw new ExpressException('物流查询驱动实现无效');
        }

        if ($this->configServe !== null) {
            $driver->setConfigServe($this->configServe);
        }

        return $driver;
    }

    /**
     * 查询物流轨迹
     *
     * @throws ExpressException
     */
    public function query(string $expressNo, string $cpCode, string $mobile = '', ?string $provider = null): array
    {
        $provider = $provider ?: self::PROVIDER_ALIYUN_MARKET;
        $cacheKey = $this->buildQueryCacheKey($provider, $expressNo, $cpCode, $mobile);
        $cached = $this->getCachedQuery($cacheKey);
        if ($cached !== null) {
            $cached['cached'] = true;
            $cached['buffer_seconds'] = self::QUERY_BUFFER_SECONDS;
            return $cached;
        }

        $result = $this->driver($provider)->query($expressNo, $cpCode, $mobile);
        $result['cached'] = false;
        $result['buffer_seconds'] = self::QUERY_BUFFER_SECONDS;
        $this->setCachedQuery($cacheKey, $result);

        return $result;
    }

    private function buildQueryCacheKey(string $provider, string $expressNo, string $cpCode, string $mobile): string
    {
        return 'express_query:' . sha1(implode('|', [
            $provider,
            strtoupper(trim($cpCode)),
            trim($expressNo),
            trim($mobile),
        ]));
    }

    private function getCachedQuery(string $cacheKey): array|null
    {
        try {
            $cached = Cache::get($cacheKey);
        } catch (Throwable) {
            return null;
        }

        return is_array($cached) ? $cached : null;
    }

    private function setCachedQuery(string $cacheKey, array $result): void
    {
        try {
            Cache::set($cacheKey, $result, self::QUERY_BUFFER_SECONDS);
        } catch (Throwable) {
        }
    }
}
