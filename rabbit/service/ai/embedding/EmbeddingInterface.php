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

/**
 * 向量化驱动统一接口
 */
interface EmbeddingInterface
{
    /**
     * 生成单条向量
     * @param string $text
     * @return array|null
     */
    public function embed(string $text): ?array;

    /**
     * 生成批量向量
     * @param array $texts
     * @return array|null
     */
    public function batchEmbed(array $texts): ?array;

    /**
     * 获取服务商标识
     * @return string
     */
    public function getProvider(): string;

    /**
     * 获取模型ID
     * @return string
     */
    public function getModel(): string;

    /**
     * 设置配置服务
     * @param ConfigInterface $configService
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static;
}
