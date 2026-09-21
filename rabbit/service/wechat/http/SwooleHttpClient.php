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

namespace Rabbit\service\wechat\http;

use Swoole\Coroutine\Http\Client;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use think\facade\Log;

/**
 * EasyWeChat 专用的 Swoole HTTP 客户端适配器。
 *
 * 作用是在 Swoole Runtime 环境下替换 Symfony 默认 CurlHttpClient，避免触发
 * Swoole cURL hook 不支持的 CURLOPT_SHARE 等选项。该客户端应仅在 Swoole 协程
 * 环境下注入；内部保留非 Swoole 回退，避免被误用时影响 CLI 和普通 FPM 场景。
 */
class SwooleHttpClient implements HttpClientInterface
{
    /**
     * 默认请求超时时间，单位秒。
     */
    protected float $timeout = 8.0;

    /**
     * 默认连接超时时间，单位秒。
     */
    protected float $connectTimeout = 3.0;

    /**
     * 构造 Swoole HTTP 客户端。
     *
     * @param array<string,mixed> $defaultOptions 默认请求选项，常用 `base_uri`、`headers`、`timeout`。
     */
    public function __construct(protected array $defaultOptions = [])
    {
        $this->defaultOptions = array_replace([
            'timeout' => $this->timeout,
            'max_duration' => $this->timeout,
        ], $this->defaultOptions);
    }

    /**
     * 发起 HTTP 请求。
     *
     * @param string $method HTTP 方法，如 GET、POST、PUT。
     * @param string $url 请求地址，支持绝对地址或基于 `base_uri` 的相对地址。
     * @param array<string,mixed> $options Symfony HttpClient 风格请求选项。
     * @return ResponseInterface 返回兼容 Symfony ResponseInterface 的响应对象。
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!class_exists(Client::class)) {
            return HttpClient::create($this->defaultOptions)->request($method, $url, $options);
        }

        $options = $this->mergeOptions($options);
        $url = $this->buildUrl($url, (array) ($options['query'] ?? []), (string) ($options['base_uri'] ?? ''));
        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new TransportException('Invalid request URL: ' . $url);
        }

        $ssl = strtolower((string) $parts['scheme']) === 'https';
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($ssl ? 443 : 80));
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $body = $this->normalizeBody($options);
        $headers = $this->normalizeHeaders((array) ($options['headers'] ?? []), $host, $body);
        $clientOptions = $this->buildClientOptions($options, $host);
        $client = new Client($host, $port, $ssl);

        $client->set($clientOptions);
        $client->setMethod(strtoupper($method));
        $client->setHeaders($headers);

        if ($body !== '') {
            $client->setData($body);
        }

        $startTime = microtime(true);
        $executed = $client->execute($path);

        $statusCode = (int) ($client->statusCode > 0 ? $client->statusCode : 0);
        $content = (string) ($client->body ?? '');
        $error = $client->errMsg ?: null;
        $errCode = (int) ($client->errCode ?? 0);
        if ($executed === false && ($error === null || $error === '')) {
            $error = 'Swoole HTTP request failed';
        }
        $responseHeaders = $this->normalizeResponseHeaders((array) ($client->headers ?? []));
        $client->close();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        if ($error !== null || $statusCode <= 0) {
            $this->logTransportFailure(strtoupper($method), $url, [
                'scheme' => (string) $parts['scheme'],
                'host' => $host,
                'port' => $port,
                'path' => $this->sanitizeUrlForLog($path),
                'ssl' => $ssl,
                'status_code' => $statusCode,
                'executed' => $executed,
                'error' => $error,
                'err_code' => $errCode,
                'duration_ms' => $durationMs,
                'base_uri' => (string) ($options['base_uri'] ?? ''),
                'timeout' => $options['timeout'] ?? null,
                'max_duration' => $options['max_duration'] ?? null,
                'connect_timeout' => $options['connect_timeout'] ?? null,
                'query_keys' => array_keys((array) ($options['query'] ?? [])),
                'header_names' => array_keys($headers),
                'body_length' => strlen($body),
                'body_sha256' => $body !== '' ? hash('sha256', $body) : '',
                'client_options' => $this->summarizeClientOptionsForLog($clientOptions),
                'swoole_coroutine_id' => class_exists(\Swoole\Coroutine::class) ? \Swoole\Coroutine::getCid() : 0,
            ]);
        }

        return new SwooleResponse($statusCode, $responseHeaders, $content, [
            'http_code' => $statusCode,
            'http_method' => strtoupper($method),
            'redirect_count' => 0,
            'redirect_url' => null,
            'start_time' => $startTime,
            'total_time' => $durationMs / 1000,
            'url' => $url,
            'user_data' => $options['user_data'] ?? null,
        ], $error);
    }

    /**
     * 返回响应流迭代器。
     *
     * 当前 EasyWeChat 调用链只依赖同步响应读取，这里提供最小流式适配以满足接口约束。
     *
     * @param ResponseInterface|iterable<ResponseInterface> $responses 一个或多个响应对象。
     * @param float|null $timeout 流读取超时时间，当前适配器不使用。
     * @return ResponseStreamInterface 响应流迭代器。
     */
    public function stream($responses, ?float $timeout = null): ResponseStreamInterface
    {
        return new SwooleResponseStream($responses);
    }

    /**
     * 创建带有新默认选项的客户端副本。
     *
     * @param array<string,mixed> $options 要合并到默认配置中的选项。
     * @return static 新客户端实例。
     */
    public function withOptions(array $options): static
    {
        return new static(array_replace_recursive($this->defaultOptions, $options));
    }

    /**
     * 合并默认选项与单次请求选项。
     *
     * @param array<string,mixed> $options 单次请求选项。
     * @return array<string,mixed> 合并后的请求选项。
     */
    protected function mergeOptions(array $options): array
    {
        return array_replace_recursive($this->defaultOptions, $options);
    }

    /**
     * 构建最终请求 URL。
     *
     * @param string $url 原始请求地址。
     * @param array<string,mixed> $query 查询参数。
     * @param string $baseUri 相对地址使用的基础地址。
     * @return string 完整请求地址。
     */
    protected function buildUrl(string $url, array $query = [], string $baseUri = ''): string
    {
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = rtrim($baseUri, '/') . '/' . ltrim($url, '/');
        }

        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }

    /**
     * 规范化请求体。
     *
     * 支持 Symfony HttpClient 常见的 `body` 和 `json` 选项，resource body 会被读取为字符串。
     *
     * @param array<string,mixed> $options 请求选项。
     * @return string 请求体字符串，无请求体时返回空字符串。
     */
    protected function normalizeBody(array $options): string
    {
        if (array_key_exists('body', $options)) {
            if (is_resource($options['body'])) {
                return (string) stream_get_contents($options['body']);
            }

            return is_array($options['body']) ? http_build_query($options['body']) : (string) $options['body'];
        }

        if (array_key_exists('json', $options)) {
            return is_string($options['json'])
                ? $options['json']
                : (string) json_encode($options['json'], JSON_UNESCAPED_UNICODE);
        }

        return '';
    }

    /**
     * 规范化请求头。
     *
     * @param array<string,mixed> $headers 原始请求头，可为键值形式或 `Name: value` 列表形式。
     * @param string $host 请求目标 Host。
     * @param string $body 请求体，用于自动补充 Content-Length。
     * @return array<string,string> Swoole Client 可消费的请求头。
     */
    protected function normalizeHeaders(array $headers, string $host, string $body): array
    {
        $normalized = ['Host' => $host];

        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                [$headerName, $headerValue] = array_pad(explode(':', (string) $value, 2), 2, '');
                $normalized[trim($headerName)] = trim($headerValue);
                continue;
            }

            $normalized[(string) $name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        if ($body !== '' && !$this->hasHeader($normalized, 'Content-Length')) {
            $normalized['Content-Length'] = (string) strlen($body);
        }

        return $normalized;
    }

    /**
     * 判断请求头中是否已包含指定 header。
     *
     * @param array<string,string> $headers 已规范化的请求头。
     * @param string $name 要查找的 header 名称。
     * @return bool 存在返回 true。
     */
    protected function hasHeader(array $headers, string $name): bool
    {
        $target = strtolower($name);
        foreach ($headers as $headerName => $value) {
            if (strtolower((string) $headerName) === $target) {
                return true;
            }
        }

        return false;
    }

    /**
     * 构建 Swoole Client 配置。
     *
     * @param array<string,mixed> $options 请求选项，支持 `timeout`、`max_duration`、`local_cert`、`local_pk` 等。
     * @param string $host 请求域名，用于 TLS SNI。
     * @return array<string,mixed> Swoole Client 的 set() 配置。
     */
    protected function buildClientOptions(array $options, string $host = ''): array
    {
        $timeout = (float) ($options['timeout'] ?? $options['max_duration'] ?? $this->timeout);
        $timeout = $timeout > 0 ? $timeout : $this->timeout;
        $connectTimeout = (float) ($options['connect_timeout'] ?? min($timeout, $this->connectTimeout));
        $connectTimeout = $connectTimeout > 0 ? $connectTimeout : $this->connectTimeout;
        $clientOptions = [
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'read_timeout' => $timeout,
            'write_timeout' => $timeout,
        ];

        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false) {
            $clientOptions['ssl_host_name'] = $host;
        }

        $certFile = $options['local_cert'] ?? $options['cert'] ?? null;
        $keyFile = $options['local_pk'] ?? $options['ssl_key'] ?? null;

        if (!empty($certFile)) {
            $clientOptions['ssl_cert_file'] = $certFile;
        }

        if (!empty($keyFile)) {
            $clientOptions['ssl_key_file'] = $keyFile;
        }

        return $clientOptions;
    }

    /**
     * 规范化响应头为 Symfony ResponseInterface 约定的结构。
     *
     * @param array<string,mixed> $headers Swoole 返回的响应头。
     * @return array<string,array<int,string>> 小写 header 名到多值数组的映射。
     */
    protected function normalizeResponseHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = is_array($value) ? array_map('strval', $value) : [(string) $value];
        }

        return $normalized;
    }

    /**
     * 记录 Swoole HTTP 传输失败诊断信息。
     *
     * @param string $method HTTP 方法。
     * @param string $url 请求 URL。
     * @param array<string,mixed> $context 传输上下文。
     * @return void
     */
    protected function logTransportFailure(string $method, string $url, array $context): void
    {
        $context = array_merge([
            'method' => $method,
            'url' => $this->sanitizeUrlForLog($url),
            'php_version' => PHP_VERSION,
            'default_socket_timeout' => ini_get('default_socket_timeout'),
        ], $context);

        Log::error($this->formatLogMessage('[wechat-http] request transport failed', $context));
    }

    /**
     * 仅保留排查连接与证书所需的 Swoole Client 配置。
     *
     * @param array<string,mixed> $clientOptions Swoole Client 配置。
     * @return array<string,mixed> 可写入日志的配置摘要。
     */
    protected function summarizeClientOptionsForLog(array $clientOptions): array
    {
        $allowedKeys = [
            'timeout',
            'connect_timeout',
            'read_timeout',
            'write_timeout',
            'ssl_host_name',
            'ssl_cert_file',
            'ssl_key_file',
        ];
        $result = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $clientOptions)) {
                $result[$key] = $clientOptions[$key];
            }
        }

        return $result;
    }

    /**
     * 脱敏 URL query 值后写入日志。
     *
     * @param string $url 原始 URL 或 path。
     * @return string 脱敏后的 URL 或 path。
     */
    protected function sanitizeUrlForLog(string $url): string
    {
        $questionPos = strpos($url, '?');
        if ($questionPos === false) {
            return $url;
        }

        $prefix = substr($url, 0, $questionPos);
        $query = substr($url, $questionPos + 1);
        parse_str($query, $params);
        foreach ($params as $key => $value) {
            $params[$key] = preg_match('/secret|token|key|sign|openid|code/i', (string) $key) === 1
                ? '[masked]'
                : $value;
        }

        return $prefix . '?' . http_build_query($params);
    }

    /**
     * 格式化日志消息，避免 ThinkPHP 文件日志丢失 context 数组。
     *
     * @param string $message 日志消息。
     * @param array<string,mixed> $context 日志上下文。
     * @return string 可直接写入文件日志的消息。
     */
    protected function formatLogMessage(string $message, array $context = []): string
    {
        if ($context === []) {
            return $message;
        }

        $payload = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $message . ': ' . ($payload !== false ? $payload : var_export($context, true));
    }
}
