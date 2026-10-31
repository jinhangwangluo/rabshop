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

namespace app\common\service\cs;

use Rabbit\interfaces\ConfigInterface;

/**
 * Embedding运行时配置桥接（来源：AI模型配置表）
 */
class AiEmbeddingRuntimeConfig implements ConfigInterface
{
    /**
     * 运行时缓存
     * @var array|null
     */
    protected ?array $runtime = null;

    /**
     * 获取单个配置
     */
    public function getValue(string $key): mixed
    {
        $runtime = $this->getRuntime();
        if ($runtime === null) {
            return null;
        }

        if ($key === 'provider') {
            return (string)($runtime['provider'] ?? '');
        }

        $options = (array)($runtime['options'] ?? []);

        return $options[$key] ?? null;
    }

    /**
     * 批量获取配置
     */
    public function getMultiValue(array $key): array
    {
        $result = [];
        foreach ($key as $item) {
            $name = (string)$item;
            $result[$name] = $this->getValue($name);
        }

        return $result;
    }

    /**
     * 获取embedding默认模型运行时
     */
    protected function getRuntime(): ?array
    {
        if ($this->runtime !== null) {
            return $this->runtime;
        }

        /** @var AiModelConfigService $aiModelConfigService */
        $aiModelConfigService = app()->make(AiModelConfigService::class);
        $runtime = $aiModelConfigService->resolveRuntime(AiModelConfigService::TYPE_EMBEDDING);

        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            return null;
        }

        $this->runtime = [
            'provider' => (string)$runtime['provider'],
            'options' => (array)$runtime['options'],
        ];

        return $this->runtime;
    }
}
