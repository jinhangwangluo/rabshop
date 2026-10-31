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

/**
 * 对话驱动统一接口
 */
interface ChatInterface
{
    /**
     * 获取文本回复
     * @param string $prompt
     * @param array $messages
     * @param array $options
     * @return string|null
     */
    public function chat(string $prompt, array $messages = [], array $options = []): ?string;

    /**
     * 获取流式回复（兼容形式）
     * @param string $prompt
     * @param array $messages
     * @param array $options
     * @return array
     */
    public function chatStream(string $prompt, array $messages = [], array $options = []): array;

    /**
     * 获取工具调用结果。
     *
     * @param string $prompt
     * @param array $messages
     * @param array $tools
     * @param array $options
     * @return array
     */
    public function chatWithTools(
        string $prompt,
        array $messages = [],
        array $tools = [],
        array $options = []
    ): array;

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
