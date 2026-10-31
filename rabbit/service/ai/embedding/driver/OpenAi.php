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
 * OpenAI向量驱动
 */
class OpenAi extends BaseDriver
{
    /**
     * @return string
     */
    public function getProvider(): string
    {
        return \Rabbit\service\ai\embedding\EmbeddingService::PROVIDER_OPENAI;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return (string)$this->cfg('model', 'text-embedding-3-small');
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
            Log::error('OpenAI Embedding未配置API Key');
            return null;
        }

        $baseUrl = $this->normalizeBaseUrl((string)$this->cfg('base_url', 'https://api.openai.com'));
        $url = $baseUrl . '/v1/embeddings';
        $model = $this->getModel();
        $params = $this->cfg('params', []);
        $params = is_array($params) ? $params : [];
        $timeout = (int)$this->cfg('timeout', 30);

        $body = array_merge($params, [
            'model' => $model,
            'input' => $texts,
        ]);

        try {
            $response = $this->postJson($url, [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ], $body, $timeout);
        } catch (\Throwable $exception) {
            Log::error('OpenAI Embedding请求失败: ' . $exception->getMessage());
            return null;
        }

        $data = $response['data'] ?? [];
        if (!is_array($data) || $data === []) {
            Log::error('OpenAI Embedding响应异常', ['provider' => $this->getProvider()]);
            return null;
        }

        usort($data, static fn($a, $b) => ((int)($a['index'] ?? 0)) <=> ((int)($b['index'] ?? 0)));
        $vectors = [];
        foreach ($data as $item) {
            if (isset($item['embedding']) && is_array($item['embedding'])) {
                $vectors[] = $item['embedding'];
            }
        }

        return $vectors === [] ? null : $vectors;
    }
}
