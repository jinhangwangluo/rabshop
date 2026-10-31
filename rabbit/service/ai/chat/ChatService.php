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

namespace Rabbit\service\ai\chat;

use Rabbit\interfaces\ConfigInterface;
use Rabbit\service\ai\chat\driver\Aliyun;
use Rabbit\service\ai\chat\driver\OpenAi;
use Rabbit\service\ai\chat\driver\OpenAiCompatible;

/**
 * 对话服务入口
 */
class ChatService
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
     * @param ConfigInterface $configService
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    /**
     * @param string|null $provider
     * @param array $options
     * @return ChatInterface
     */
    public function driver(?string $provider = null, array $options = []): ChatInterface
    {
        $provider = $provider ?: $this->resolveProvider();

        return match ($provider) {
            self::PROVIDER_ALIYUN => $this->makeAliyun($options),
            self::PROVIDER_OPENAI => $this->makeOpenAi($options),
            self::PROVIDER_OPENAI_COMPATIBLE => $this->makeOpenAiCompatible($options),
            default => throw new ChatException('不支持的对话服务商：' . $provider),
        };
    }

    /**
     * 获取回复
     * @param string $prompt
     * @param array $messages
     * @param array $options
     * @param string|null $provider
     * @param array $providerOptions
     * @return string|null
     */
    public function reply(
        string $prompt,
        array $messages = [],
        array $options = [],
        ?string $provider = null,
        array $providerOptions = []
    ): ?string {
        return $this->driver($provider, $providerOptions)->chat($prompt, $messages, $options);
    }

    /**
     * 获取流式回复（兼容形式）
     * @param string $prompt
     * @param array $messages
     * @param array $options
     * @param string|null $provider
     * @param array $providerOptions
     * @return array
     */
    public function replyStream(
        string $prompt,
        array $messages = [],
        array $options = [],
        ?string $provider = null,
        array $providerOptions = []
    ): array {
        return $this->driver($provider, $providerOptions)->chatStream($prompt, $messages, $options);
    }

    /**
     * 获取工具调用结果。
     *
     * @param string $prompt
     * @param array $messages
     * @param array $tools
     * @param array $options
     * @param string|null $provider
     * @param array $providerOptions
     * @return array
     */
    public function replyWithTools(
        string $prompt,
        array $messages = [],
        array $tools = [],
        array $options = [],
        ?string $provider = null,
        array $providerOptions = []
    ): array {
        return $this->driver($provider, $providerOptions)
            ->chatWithTools($prompt, $messages, $tools, $options);
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
     * @return string
     */
    private function resolveProvider(): string
    {
        if ($this->configServe === null) {
            return self::PROVIDER_ALIYUN;
        }

        $provider = (string)$this->configServe->getValue('cs_chat_provider');
        if (in_array($provider, [self::PROVIDER_ALIYUN, self::PROVIDER_OPENAI, self::PROVIDER_OPENAI_COMPATIBLE], true)) {
            return $provider;
        }

        if (!empty($this->configServe->getValue('cs_aliyun_api_key'))) {
            return self::PROVIDER_ALIYUN;
        }

        if (!empty($this->configServe->getValue('cs_chat_api_key'))) {
            return self::PROVIDER_OPENAI;
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
