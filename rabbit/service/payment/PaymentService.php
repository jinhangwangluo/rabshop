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

namespace Rabbit\service\payment;

use Rabbit\interfaces\ConfigInterface;
use Rabbit\service\payment\driver\Alipay;
use Rabbit\service\payment\driver\Wechat;
use Rabbit\traits\DriverRegistryTrait;

/**
 * 支付扩展入口服务。
 */
class PaymentService
{
    use DriverRegistryTrait;

    public const CHANNEL_WECHAT = 'wechat';
    public const CHANNEL_ALIPAY = 'alipay';

    /** 插件 manifest 中的声明字段 */
    private const MANIFEST_KEY = 'payment_channels';

    protected ?ConfigInterface $serve = null;

    /**
     * 获取配置。
     *
     * @param string $key 配置键。
     * @return mixed
     */
    public function getConfig(string $key): mixed
    {
        if (!$this->serve) {
            throw new PaymentException('支付配置服务未设置');
        }

        return $this->serve->getValue($key);
    }

    /**
     * 批量获取配置。
     *
     * @param array<int, string> $keys 配置键列表。
     * @return array<string, mixed>
     */
    public function getConfigs(array $keys): array
    {
        if (!$this->serve) {
            throw new PaymentException('支付配置服务未设置');
        }

        return $this->serve->getMultiValue($keys);
    }

    /**
     * 设置配置服务。
     *
     * @param ConfigInterface $service 配置服务。
     * @return self
     */
    public function setConfigServe(ConfigInterface $service): self
    {
        $this->serve = $service;

        return $this;
    }

    /**
     * 获取支付渠道驱动。
     *
     * @param string $channel 支付渠道。
     * @param array<string, mixed> $config 渠道配置。
     * @return PaymentInterface 支付驱动。
     */
    public function channel(string $channel, array $config = []): PaymentInterface
    {
        $key = strtolower(trim($channel));
        $instance = self::resolveRegistered($key, $config);
        if ($instance !== null) {
            if (!$instance instanceof PaymentInterface) {
                throw new PaymentException('支付渠道实现无效');
            }

            return $instance;
        }

        return match ($key) {
            self::CHANNEL_WECHAT => new Wechat($config),
            self::CHANNEL_ALIPAY => new Alipay($config),
            default => throw new PaymentException('支付渠道不存在'),
        };
    }
}
