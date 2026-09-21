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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Rabbit\interfaces\ConfigInterface;

/**
 * 对话驱动基类
 */
abstract class BaseDriver implements ChatInterface
{
    private const DEEPSEEK_BASE_URL = 'https://api.deepseek.com';

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
     * 构建 Chat Completions 接口地址。
     *
     * @param string $baseUrl 服务商基础地址
     * @param string $model 模型ID
     * @return string
     */
    protected function buildChatCompletionsUrl(string $baseUrl, string $model = ''): string
    {
        $baseUrl = $this->normalizeBaseUrl($baseUrl);
        if ($baseUrl === '') {
            return '';
        }

        if ($this->isDeepSeekEndpoint($baseUrl, $model)) {
            return $this->buildDeepSeekChatCompletionsUrl($this->resolveDeepSeekBaseUrl($baseUrl, $model));
        }

        if ($this->isChatCompletionsEndpoint($baseUrl)) {
            return $baseUrl;
        }

        if (preg_match('#/v1$#i', $baseUrl) === 1) {
            return $baseUrl . '/chat/completions';
        }

        return $baseUrl . '/v1/chat/completions';
    }

    /**
     * @param string $url
     * @param array $headers
     * @param array $body
     * @param int $timeout
     * @return array
     * @throws ChatException
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
            throw new ChatException('HTTP请求失败：' . $exception->getMessage(), 0, $exception);
        }

        $content = (string)$response->getBody()->getContents();
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new ChatException('响应解析失败');
        }

        return $decoded;
    }

    /**
     * 发起SSE流式请求并解析为统一分片结构
     * @param string $url
     * @param array $headers
     * @param array $body
     * @param int $timeout
     * @return array
     * @throws ChatException
     */
    protected function postSseChunks(
        string $url,
        array $headers,
        array $body,
        int $timeout = 30,
        bool $directOutput = false,
        ?callable $outputHandler = null,
        mixed $swooleResponse = null,
        bool $swooleAutoEnd = false
    ): array {
        $client = $this->createStreamingClient($timeout);
        $chunks = [];
        $fullContent = '';
        $lineBuffer = '';
        $rawBody = '';

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $body,
                'http_errors' => false,
                'curl' => [
                    CURLOPT_CONNECTTIMEOUT => min(10, max(1, $timeout)),
                    CURLOPT_WRITEFUNCTION => function ($handle, string $buffer) use (
                        &$chunks,
                        &$fullContent,
                        &$lineBuffer,
                        &$rawBody,
                        $directOutput,
                        $outputHandler,
                        $swooleResponse,
                        $swooleAutoEnd
                    ): int {
                        if (strlen($rawBody) < 4096) {
                            $rawBody .= substr($buffer, 0, 4096 - strlen($rawBody));
                        }

                        $this->consumeSseBuffer(
                            $buffer,
                            $lineBuffer,
                            $chunks,
                            $fullContent,
                            $directOutput,
                            $outputHandler,
                            $swooleResponse,
                            $swooleAutoEnd
                        );

                        return strlen($buffer);
                    },
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new ChatException('SSE请求失败：' . $exception->getMessage(), 0, $exception);
        }

        $responseBody = (string)$response->getBody();
        if ($rawBody === '' && $responseBody !== '') {
            $rawBody = substr($responseBody, 0, 4096);
            $this->consumeSseBuffer(
                $responseBody,
                $lineBuffer,
                $chunks,
                $fullContent,
                $directOutput,
                $outputHandler,
                $swooleResponse,
                $swooleAutoEnd
            );
        }

        if ($lineBuffer !== '') {
            $this->appendSseLineChunk(
                $lineBuffer,
                $chunks,
                $fullContent,
                $directOutput,
                $outputHandler,
                $swooleResponse,
                $swooleAutoEnd
            );
        }

        $httpCode = $response->getStatusCode();
        if ($httpCode >= 400) {
            $message = 'SSE请求失败：HTTP ' . $httpCode;
            if (trim($rawBody) !== '') {
                $message .= ' ' . mb_substr(trim($rawBody), 0, 200);
            }

            throw new ChatException($message);
        }

        if ($chunks === []) {
            $doneChunk = [
                'delta' => '',
                'done' => true,
                'content' => '',
            ];
            $this->emitStreamChunk('', true, '', $directOutput, $outputHandler, $swooleResponse, $swooleAutoEnd);
            return [$doneChunk];
        }

        $doneChunk = [
            'delta' => '',
            'done' => true,
            'content' => $fullContent,
        ];
        $chunks[] = $doneChunk;
        $this->emitStreamChunk('', true, $fullContent, $directOutput, $outputHandler, $swooleResponse, $swooleAutoEnd);

        return $chunks;
    }

    /**
     * 创建使用 cURL transport 的流式客户端。
     *
     * @param int $timeout 超时时间
     * @return Client
     */
    protected function createStreamingClient(int $timeout): Client
    {
        if (!function_exists('curl_init')) {
            throw new ChatException('SSE请求失败：当前PHP环境未启用curl扩展');
        }

        return new Client([
            'timeout' => max(1, $timeout),
            'handler' => HandlerStack::create(new CurlHandler()),
            'http_errors' => false,
        ]);
    }

    /**
     * 消费 cURL 回调返回的 SSE 数据。
     *
     * @param string $buffer 响应分片
     * @param string $lineBuffer 未完成行缓冲
     * @param array $chunks 分片列表引用
     * @param string $fullContent 完整内容引用
     * @param bool $directOutput 是否直接输出
     * @param callable|null $outputHandler 输出回调
     * @param mixed $swooleResponse Swoole响应对象
     * @param bool $swooleAutoEnd 是否自动结束Swoole响应
     * @return void
     */
    private function consumeSseBuffer(
        string $buffer,
        string &$lineBuffer,
        array &$chunks,
        string &$fullContent,
        bool $directOutput,
        ?callable $outputHandler,
        mixed $swooleResponse,
        bool $swooleAutoEnd
    ): void {
        $lineBuffer .= $buffer;
        while (($position = strpos($lineBuffer, "\n")) !== false) {
            $line = substr($lineBuffer, 0, $position);
            $lineBuffer = substr($lineBuffer, $position + 1);
            $this->appendSseLineChunk(
                $line,
                $chunks,
                $fullContent,
                $directOutput,
                $outputHandler,
                $swooleResponse,
                $swooleAutoEnd
            );
        }
    }

    /**
     * 解析并追加一行 SSE data 事件。
     *
     * @param string $line SSE 行
     * @param array $chunks 分片列表引用
     * @param string $fullContent 完整内容引用
     * @param bool $directOutput 是否直接输出
     * @param callable|null $outputHandler 输出回调
     * @param mixed $swooleResponse Swoole响应对象
     * @param bool $swooleAutoEnd 是否自动结束Swoole响应
     * @return void
     */
    private function appendSseLineChunk(
        string $line,
        array &$chunks,
        string &$fullContent,
        bool $directOutput,
        ?callable $outputHandler,
        mixed $swooleResponse,
        bool $swooleAutoEnd
    ): void {
        $line = trim($line);
        if ($line === '' || !str_starts_with($line, 'data:')) {
            return;
        }

        $payload = trim((string)substr($line, 5));
        if ($payload === '' || $payload === '[DONE]') {
            return;
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return;
        }

        $delta = $this->extractDeltaFromSsePayload($decoded, $fullContent);
        if ($delta === '') {
            return;
        }

        $chunks[] = [
            'delta' => $delta,
            'done' => false,
        ];
        $this->emitStreamChunk(
            $delta,
            false,
            null,
            $directOutput,
            $outputHandler,
            $swooleResponse,
            $swooleAutoEnd
        );
    }

    /**
     * 判断是否为 DeepSeek 请求。
     *
     * @param string $baseUrl 服务商基础地址
     * @param string $model 模型ID
     * @return bool
     */
    private function isDeepSeekEndpoint(string $baseUrl, string $model): bool
    {
        $host = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?: ''));
        if ($host !== '' && str_contains($host, 'deepseek.com')) {
            return true;
        }

        return $this->isDeepSeekModel($model);
    }

    /**
     * DeepSeek 模型使用默认 OpenAI 模板时，切换为 DeepSeek 官方地址。
     *
     * @param string $baseUrl 服务商基础地址
     * @param string $model 模型ID
     * @return string
     */
    private function resolveDeepSeekBaseUrl(string $baseUrl, string $model): string
    {
        if ($this->isDeepSeekModel($model) && $this->isOpenAiBaseUrl($baseUrl)) {
            return self::DEEPSEEK_BASE_URL;
        }

        return $baseUrl;
    }

    /**
     * 判断是否为 DeepSeek 模型。
     *
     * @param string $model 模型ID
     * @return bool
     */
    private function isDeepSeekModel(string $model): bool
    {
        return str_contains(strtolower(trim($model)), 'deepseek');
    }

    /**
     * 判断是否为 OpenAI 默认地址。
     *
     * @param string $baseUrl 服务商基础地址
     * @return bool
     */
    private function isOpenAiBaseUrl(string $baseUrl): bool
    {
        $host = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?: ''));

        return $host === 'api.openai.com';
    }

    /**
     * 构建 DeepSeek Chat Completions 接口地址。
     *
     * @param string $baseUrl 服务商基础地址
     * @return string
     */
    private function buildDeepSeekChatCompletionsUrl(string $baseUrl): string
    {
        if (preg_match('#/chat/completions$#i', $baseUrl) === 1) {
            return preg_replace('#/v1/chat/completions$#i', '/chat/completions', $baseUrl) ?: $baseUrl;
        }

        $baseUrl = preg_replace('#/v1$#i', '', $baseUrl) ?: $baseUrl;

        return rtrim($baseUrl, '/') . '/chat/completions';
    }

    /**
     * 判断基础地址是否已经是 Chat Completions 完整地址。
     *
     * @param string $baseUrl 服务商基础地址
     * @return bool
     */
    private function isChatCompletionsEndpoint(string $baseUrl): bool
    {
        return preg_match('#/chat/completions$#i', $baseUrl) === 1;
    }

    /**
     * 从SSE事件体提取增量文本
     * @param array $payload
     * @param string $fullContent
     * @return string
     */
    protected function extractDeltaFromSsePayload(array $payload, string &$fullContent): string
    {
        $delta = '';

        $openAiDelta = $payload['choices'][0]['delta']['content'] ?? null;
        if (is_string($openAiDelta) && $openAiDelta !== '') {
            $delta = $openAiDelta;
        }

        if ($delta === '') {
            $openAiText = $payload['choices'][0]['text'] ?? null;
            if (is_string($openAiText) && $openAiText !== '') {
                $delta = $openAiText;
            }
        }

        if ($delta === '') {
            $dashscopeText = $payload['output']['text'] ?? null;
            if (is_string($dashscopeText) && $dashscopeText !== '') {
                if ($fullContent !== '' && str_starts_with($dashscopeText, $fullContent)) {
                    $delta = (string)substr($dashscopeText, strlen($fullContent));
                } else {
                    $delta = $dashscopeText;
                }
            }
        }

        if ($delta !== '') {
            $fullContent .= $delta;
        }

        return $delta;
    }

    /**
     * 中途直接输出当前流式分片
     * @param string $delta
     * @param bool $done
     * @param string|null $content
     * @param bool $directOutput
     * @return void
     */
    protected function emitStreamChunk(
        string $delta,
        bool $done,
        ?string $content,
        bool $directOutput,
        ?callable $outputHandler = null,
        mixed $swooleResponse = null,
        bool $swooleAutoEnd = false
    ): void {
        $payload = [
            'delta' => $delta,
            'done' => $done,
        ];

        if ($done) {
            $payload['content'] = (string)$content;
        }

        $event = 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

        if ($outputHandler !== null) {
            $outputHandler($payload, $event);
            return;
        }

        if ($this->emitBySwooleResponse($swooleResponse, $event, $done, $swooleAutoEnd)) {
            return;
        }

        if (!$directOutput || PHP_SAPI === 'cli') {
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
        }

        echo $event;

        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }

    /**
     * 优先兼容Swoole响应流式输出
     * @param mixed $swooleResponse
     * @param string $event
     * @param bool $done
     * @param bool $swooleAutoEnd
     * @return bool
     */
    protected function emitBySwooleResponse(mixed $swooleResponse, string $event, bool $done, bool $swooleAutoEnd): bool
    {
        if (!is_object($swooleResponse) || !method_exists($swooleResponse, 'write')) {
            return false;
        }

        if (method_exists($swooleResponse, 'header')) {
            $swooleResponse->header('Content-Type', 'text/event-stream; charset=utf-8');
            $swooleResponse->header('Cache-Control', 'no-cache');
            $swooleResponse->header('X-Accel-Buffering', 'no');
        }

        $swooleResponse->write($event);

        if ($done && $swooleAutoEnd && method_exists($swooleResponse, 'end')) {
            $swooleResponse->end();
        }

        return true;
    }

    /**
     * 构建messages
     * @param string $prompt
     * @param array $messages
     * @return array
     */
    protected function buildMessages(string $prompt, array $messages = []): array
    {
        if ($messages !== []) {
            return $messages;
        }

        return [[
            'role' => 'user',
            'content' => $prompt,
        ]];
    }

    /**
     * 规范化 OpenAI 兼容工具调用响应。
     *
     * @param array $message 助手消息
     * @param array $response 原始响应
     * @return array
     */
    protected function buildToolChatResult(array $message, array $response): array
    {
        $content = $message['content'] ?? '';
        if (is_array($content)) {
            $content = $this->extractMessageText($content);
        }

        return [
            'content' => is_string($content) ? $content : '',
            'tool_calls' => $this->normalizeToolCalls($message),
            'assistant_message' => $message,
            'raw' => $response,
        ];
    }

    /**
     * 规范化工具调用结构。
     *
     * @param array $message 助手消息
     * @return array
     */
    protected function normalizeToolCalls(array $message): array
    {
        $toolCalls = $message['tool_calls'] ?? [];
        if (!is_array($toolCalls) || $toolCalls === []) {
            $functionCall = $message['function_call'] ?? null;
            if (is_array($functionCall)) {
                $toolCalls = [[
                    'id' => 'legacy_call_' . substr(md5(json_encode($functionCall)), 0, 12),
                    'type' => 'function',
                    'function' => $functionCall,
                ]];
            }
        }

        $result = [];
        foreach ($toolCalls as $index => $toolCall) {
            if (!is_array($toolCall)) {
                continue;
            }

            $function = $toolCall['function'] ?? $toolCall;
            if (!is_array($function)) {
                continue;
            }

            $name = trim((string)($function['name'] ?? $toolCall['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $arguments = $function['arguments'] ?? $toolCall['arguments'] ?? [];
            if (is_array($arguments)) {
                $arguments = json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $result[] = [
                'id' => (string)($toolCall['id'] ?? ('tool_call_' . $index)),
                'name' => $name,
                'arguments' => is_string($arguments) ? $arguments : '',
                'raw' => $toolCall,
            ];
        }

        return $result;
    }

    /**
     * 提取多模态消息中的文本内容。
     *
     * @param array $content 内容片段
     * @return string
     */
    private function extractMessageText(array $content): string
    {
        $text = '';
        foreach ($content as $item) {
            if (is_string($item)) {
                $text .= $item;
                continue;
            }

            if (is_array($item)) {
                $text .= (string)($item['text'] ?? $item['content'] ?? '');
            }
        }

        return $text;
    }

    /**
     * 将普通文本转换为流式分片（兼容形式）
     * @param string|null $content
     * @param int $chunkLength
     * @return array
     */
    protected function chunkStreamFromText(?string $content, int $chunkLength = 32): array
    {
        $content = is_string($content) ? $content : '';
        if ($content === '') {
            return [
                [
                    'delta' => '',
                    'done' => true,
                    'content' => '',
                    'fallback' => true,
                ],
            ];
        }

        $chunkLength = max(1, $chunkLength);
        $chunks = [];

        $length = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        for ($offset = 0; $offset < $length; $offset += $chunkLength) {
            $piece = function_exists('mb_substr')
                ? mb_substr($content, $offset, $chunkLength, 'UTF-8')
                : substr($content, $offset, $chunkLength);
            $chunks[] = [
                'delta' => (string)$piece,
                'done' => false,
                'fallback' => true,
            ];
        }

        $chunks[] = [
            'delta' => '',
            'done' => true,
            'content' => $content,
            'fallback' => true,
        ];

        return $chunks;
    }
}
