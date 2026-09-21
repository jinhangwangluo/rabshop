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

namespace Rabbit\service\ai\embedding;

use Rabbit\interfaces\ConfigInterface;
use Rabbit\service\ai\embedding\driver\Aliyun;
use Rabbit\service\ai\embedding\driver\OpenAi;
use Rabbit\service\ai\embedding\driver\OpenAiCompatible;

/**
 * 向量服务入口
 */
class EmbeddingService
{
    /** 阿里云 */
    public const PROVIDER_ALIYUN = 'aliyun';

    /** OpenAI */
    public const PROVIDER_OPENAI = 'openai';

    /** OpenAI兼容 */
    public const PROVIDER_OPENAI_COMPATIBLE = 'openai_compatible';

    /**
     * 配置服务
     * @var ConfigInterface|null
     */
    protected ?ConfigInterface $configServe = null;

    /**
     * 设置配置服务
     * @param ConfigInterface $configService
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    /**
     * 获取驱动
     * @param string|null $provider
     * @param array $options
     * @return EmbeddingInterface
     */
    public function driver(?string $provider = null, array $options = []): EmbeddingInterface
    {
        $provider = $provider ?: $this->resolveProvider();

        return match ($provider) {
            self::PROVIDER_ALIYUN => $this->makeAliyun($options),
            self::PROVIDER_OPENAI => $this->makeOpenAi($options),
            self::PROVIDER_OPENAI_COMPATIBLE => $this->makeOpenAiCompatible($options),
            default => throw new EmbeddingException('不支持的向量服务商：' . $provider),
        };
    }

    /**
     * 单条向量化
     * @param string $text
     * @param string|null $provider
     * @param array $options
     * @return array|null
     */
    public function embed(string $text, ?string $provider = null, array $options = []): ?array
    {
        return $this->driver($provider, $options)->embed($text);
    }

    /**
     * 批量向量化
     * @param array $texts
     * @param string|null $provider
     * @param array $options
     * @return array|null
     */
    public function batchEmbed(array $texts, ?string $provider = null, array $options = []): ?array
    {
        return $this->driver($provider, $options)->batchEmbed($texts);
    }

    /**
     * 获取当前服务商
     * @return string
     */
    public function getProvider(): string
    {
        return $this->resolveProvider();
    }

    /**
     * 获取当前模型标记 provider:model
     * @param string|null $provider
     * @param array $options
     * @return string
     */
    public function modelTag(?string $provider = null, array $options = []): string
    {
        $driver = $this->driver($provider, $options);
        return $driver->getProvider() . ':' . $driver->getModel();
    }

    /**
     * 解析服务商
     * @return string
     */
    private function resolveProvider(): string
    {
        if ($this->configServe === null) {
            return self::PROVIDER_ALIYUN;
        }

        $provider = (string)$this->configServe->getValue('provider');
        if (in_array($provider, [self::PROVIDER_ALIYUN, self::PROVIDER_OPENAI, self::PROVIDER_OPENAI_COMPATIBLE], true)) {
            return $provider;
        }

        return self::PROVIDER_ALIYUN;
    }

    /**
     * @param array $options
     * @return Aliyun
     */
    private function makeAliyun(array $options = []): Aliyun
    {
        $driver = new Aliyun($options);
        if ($this->configServe !== null) {
            $driver->setConfigServe($this->configServe);
        }
        return $driver;
    }

    /**
     * @param array $options
     * @return OpenAi
     */
    private function makeOpenAi(array $options = []): OpenAi
    {
        $driver = new OpenAi($options);
        if ($this->configServe !== null) {
            $driver->setConfigServe($this->configServe);
        }
        return $driver;
    }

    /**
     * @param array $options
     * @return OpenAiCompatible
     */
    private function makeOpenAiCompatible(array $options = []): OpenAiCompatible
    {
        $driver = new OpenAiCompatible($options);
        if ($this->configServe !== null) {
            $driver->setConfigServe($this->configServe);
        }
        return $driver;
    }
}
