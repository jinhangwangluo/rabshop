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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Rabbit\interfaces\ConfigInterface;

/**
 * 向量驱动基类
 */
abstract class BaseDriver implements EmbeddingInterface
{
    /**
     * 配置服务
     * @var ConfigInterface|null
     */
    protected ?ConfigInterface $configServe = null;

    /**
     * 直接配置
     * @var array
     */
    protected array $config = [];

    /**
     * 是否启用配置服务兜底
     * @var bool
     */
    protected bool $enableConfigFallback = true;

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        if (array_key_exists('enable_config_fallback', $config)) {
            $this->enableConfigFallback = (bool)$config['enable_config_fallback'];
        }
    }

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
     * 读取配置
     * @param string $key
     * @param mixed $default
     * @param array $fallbackKeys
     * @return mixed
     */
    protected function cfg(string $key, mixed $default = null, array $fallbackKeys = []): mixed
    {
        if (array_key_exists($key, $this->config)) {
            $value = $this->config[$key];
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        foreach ($fallbackKeys as $fallbackKey) {
            if (array_key_exists($fallbackKey, $this->config)) {
                $value = $this->config[$fallbackKey];
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return $default;
    }

    /**
     * 归一化 base_url
     * @param string $baseUrl
     * @return string
     */
    protected function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl !== '' && !str_contains($baseUrl, '://')) {
            $baseUrl = 'https://' . $baseUrl;
        }
        return rtrim($baseUrl, '/');
    }

    /**
     * POST JSON
     * @param string $url
     * @param array $headers
     * @param array $body
     * @param int $timeout
     * @return array
     * @throws EmbeddingException
     */
    protected function postJson(string $url, array $headers, array $body, int $timeout = 30): array
    {
        $client = new Client([
            'timeout' => max(1, $timeout),
        ]);

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $body,
            ]);
        } catch (GuzzleException $exception) {
            throw new EmbeddingException('HTTP请求失败：' . $exception->getMessage(), 0, $exception);
        }

        $content = (string)$response->getBody()->getContents();
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new EmbeddingException('响应解析失败');
        }

        return $decoded;
    }
}
