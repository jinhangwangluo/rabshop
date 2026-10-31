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

namespace Rabbit\service\ai\chat\driver;

use Rabbit\service\ai\chat\BaseDriver;
use Rabbit\service\ai\chat\ChatException;
use think\facade\Log;

/**
 * OpenAI兼容对话驱动
 */
class OpenAiCompatible extends BaseDriver
{
    /**
     * @return string
     */
    public function getProvider(): string
    {
        return \Rabbit\service\ai\chat\ChatService::PROVIDER_OPENAI_COMPATIBLE;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return (string)$this->cfg('model', 'gpt-4o-mini');
    }

    /**
     * @param string $prompt
     * @param array $messages
     * @param array $options
     * @return string|null
     */
    public function chat(string $prompt, array $messages = [], array $options = []): ?string
    {
        $apiKey = (string)$this->cfg('api_key', '');
        if ($apiKey === '') {
            Log::error('OpenAI兼容Chat未配置API Key');
            return null;
        }

        $model = $this->getModel();
        $url = $this->buildChatCompletionsUrl((string)$this->cfg('base_url', ''), $model);
        if ($url === '') {
            Log::error('OpenAI兼容Chat未配置base_url');
            return null;
        }

        $params = $this->cfg('params', []);
        $params = is_array($params) ? $params : [];
        $runtimeOptions = $options;
        unset($runtimeOptions['stream']);
        $params = array_merge([
            'max_tokens' => 500,
            'temperature' => 0.7,
        ], $params, $runtimeOptions);
        $timeout = (int)$this->cfg('timeout', 30);

        $body = array_merge($params, [
            'model' => $model,
            'messages' => $this->buildMessages($prompt, $messages),
        ]);

        try {
            $response = $this->postJson($url, [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ], $body, $timeout);
        } catch (\Throwable $exception) {
            Log::error('OpenAI兼容Chat请求失败: ' . $exception->getMessage());
            return null;
        }

        $content = $response['choices'][0]['message']['content'] ?? null;
        return is_string($content) && trim($content) !== '' ? $content : null;
    }

    /**
     * @param string $prompt
     * @param array $messages
     * @param array $options
     * @return array
     */
    public function chatStream(string $prompt, array $messages = [], array $options = []): array
    {
        $apiKey = (string)$this->cfg('api_key', '');
        if ($apiKey === '') {
            Log::error('OpenAI兼容Chat未配置API Key');
            if (($options['stream_fallback'] ?? true) === false) {
                throw new ChatException('OpenAI兼容Chat未配置API Key');
            }
            return $this->chunkStreamFromText(null);
        }

        $model = $this->getModel();
        $url = $this->buildChatCompletionsUrl((string)$this->cfg('base_url', ''), $model);
        if ($url === '') {
            Log::error('OpenAI兼容Chat未配置base_url');
            if (($options['stream_fallback'] ?? true) === false) {
                throw new ChatException('OpenAI兼容Chat未配置base_url');
            }
            return $this->chunkStreamFromText(null);
        }

        $params = $this->cfg('params', []);
        $params = is_array($params) ? $params : [];
        $runtimeOptions = $options;
        unset(
            $runtimeOptions['stream'],
            $runtimeOptions['chunk_length'],
            $runtimeOptions['direct_output'],
            $runtimeOptions['output_handler'],
            $runtimeOptions['swoole_response'],
            $runtimeOptions['swoole_auto_end'],
            $runtimeOptions['stream_fallback']
        );
        $params = array_merge([
            'max_tokens' => 500,
            'temperature' => 0.7,
        ], $params, $runtimeOptions);
        $timeout = (int)$this->cfg('timeout', 30);
        $directOutput = array_key_exists('direct_output', $options) ? (bool)$options['direct_output'] : true;
        $outputHandler = isset($options['output_handler']) && is_callable($options['output_handler'])
            ? $options['output_handler']
            : null;
        $swooleResponse = $options['swoole_response'] ?? null;
        $swooleAutoEnd = array_key_exists('swoole_auto_end', $options) ? (bool)$options['swoole_auto_end'] : false;

        $body = array_merge($params, [
            'model' => $model,
            'messages' => $this->buildMessages($prompt, $messages),
            'stream' => true,
        ]);

        try {
            return $this->postSseChunks($url, [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ], $body, $timeout, $directOutput, $outputHandler, $swooleResponse, $swooleAutoEnd);
        } catch (\Throwable $exception) {
            if (($options['stream_fallback'] ?? true) === false) {
                Log::warning('OpenAI兼容Chat流式请求失败: ' . $exception->getMessage());
                throw $exception;
            }
            Log::warning('OpenAI兼容Chat流式请求失败，降级为分片输出: ' . $exception->getMessage());
            $content = $this->chat($prompt, $messages, $options);
            $chunkLength = (int)($options['chunk_length'] ?? 32);
            return $this->chunkStreamFromText($content, $chunkLength);
        }
    }

    /**
     * 调用 OpenAI 兼容工具调用接口。
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
    ): array {
        $apiKey = (string)$this->cfg('api_key', '');
        if ($apiKey === '') {
            return ['content' => '', 'tool_calls' => [], 'error' => 'OpenAI兼容Chat未配置API Key'];
        }

        $model = $this->getModel();
        $url = $this->buildChatCompletionsUrl((string)$this->cfg('base_url', ''), $model);
        if ($url === '') {
            return ['content' => '', 'tool_calls' => [], 'error' => 'OpenAI兼容Chat未配置base_url'];
        }

        $params = $this->cfg('params', []);
        $params = is_array($params) ? $params : [];
        $runtimeOptions = $options;
        $toolChoice = $runtimeOptions['tool_choice'] ?? 'auto';
        unset($runtimeOptions['tool_choice'], $runtimeOptions['tools']);
        $params = array_merge([
            'max_tokens' => 800,
            'temperature' => 0.2,
        ], $params, $runtimeOptions);

        $body = array_merge($params, [
            'model' => $model,
            'messages' => $this->buildMessages($prompt, $messages),
        ]);
        if ($tools !== []) {
            $body['tools'] = $tools;
            $body['tool_choice'] = $toolChoice;
        }

        try {
            $response = $this->postJson($url, [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ], $body, (int)$this->cfg('timeout', 30));
        } catch (\Throwable $exception) {
            Log::error('OpenAI兼容Chat工具调用失败: ' . $exception->getMessage());
            return [
                'content' => '',
                'tool_calls' => [],
                'error' => $exception->getMessage(),
            ];
        }

        $message = $response['choices'][0]['message'] ?? [];
        return is_array($message)
            ? $this->buildToolChatResult($message, $response)
            : ['content' => '', 'tool_calls' => [], 'raw' => $response];
    }
}
