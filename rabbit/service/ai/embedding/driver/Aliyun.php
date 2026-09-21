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

namespace Rabbit\service\ai\embedding\driver;

use Rabbit\service\ai\embedding\BaseDriver;
use think\facade\Log;

/**
 * 阿里云向量驱动
 */
class Aliyun extends BaseDriver
{
    /**
     * @return string
     */
    public function getProvider(): string
    {
        return \Rabbit\service\ai\embedding\EmbeddingService::PROVIDER_ALIYUN;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return (string)$this->cfg('model', 'text-embedding-v2');
    }

    /**
     * @param string $text
     * @return array|null
     */
    public function embed(string $text): ?array
    {
        $result = $this->batchEmbed([$text]);
        return is_array($result) && isset($result[0]) && is_array($result[0]) ? $result[0] : null;
    }

    /**
     * @param array $texts
     * @return array|null
     */
    public function batchEmbed(array $texts): ?array
    {
        $texts = array_values(array_filter(array_map(static fn($item) => trim((string)$item), $texts), static fn($item) => $item !== ''));
        if ($texts === []) {
            return [];
        }

        $apiKey = (string)$this->cfg('api_key', '');
        if ($apiKey === '') {
            Log::error('阿里云Embedding未配置API Key');
            return null;
        }

        $baseUrl = $this->normalizeBaseUrl((string)$this->cfg('base_url', 'https://dashscope.aliyuncs.com'));
        $url = $baseUrl . '/api/v1/services/embeddings/text-embedding/text-embedding';
        $model = $this->getModel();
        $params = $this->cfg('params', []);
        $params = is_array($params) ? $params : [];
        $timeout = (int)$this->cfg('timeout', 30);

        $body = [
            'model' => $model,
            'input' => [
                'texts' => $texts,
            ],
        ];
        // parameters 为空时不传，避免阿里云将空数组 [] 识别为非法参数
        if (!empty($params)) {
            $body['parameters'] = (object)$params;
        }

        try {
            $response = $this->postJson($url, [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ], $body, $timeout);
        } catch (\Throwable $exception) {
            Log::error('阿里云Embedding请求失败: ' . $exception->getMessage());
            return null;
        }

        $embeddings = $response['output']['embeddings'] ?? [];
        if (!is_array($embeddings) || $embeddings === []) {
            Log::error('阿里云Embedding响应异常', ['provider' => $this->getProvider()]);
            return null;
        }

        $vectors = [];
        foreach ($embeddings as $item) {
            if (isset($item['embedding']) && is_array($item['embedding'])) {
                $vectors[] = $item['embedding'];
            }
        }

        return $vectors === [] ? null : $vectors;
    }
}
