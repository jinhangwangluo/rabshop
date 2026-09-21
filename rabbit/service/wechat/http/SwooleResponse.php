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

use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Swoole HTTP 响应对象，适配 Symfony ResponseInterface。
 *
 * EasyWeChat 内部按 Symfony 响应接口读取状态码、响应头、文本内容和 JSON 数组，
 * 该类负责把 Swoole Client 的结果转换为一致的读取方式。
 */
class SwooleResponse implements ResponseInterface
{
    /**
     * 响应是否已被取消。
     */
    protected bool $canceled = false;

    /**
     * 构造响应对象。
     *
     * @param int $statusCode HTTP 状态码。
     * @param array<string,array<int,string>> $headers 响应头，按 Symfony 约定使用小写 header 名和多值数组。
     * @param string $content 响应体文本。
     * @param array<string,mixed> $info 传输层信息，用于 getInfo()。
     * @param string|null $error 传输错误信息，无错误时为 null。
     */
    public function __construct(
        protected int $statusCode,
        protected array $headers,
        protected string $content,
        protected array $info = [],
        protected ?string $error = null
    ) {
    }

    /**
     * 获取 HTTP 状态码。
     *
     * @return int HTTP 状态码。
     */
    public function getStatusCode(): int
    {
        $this->throwTransportError();

        return $this->statusCode;
    }

    /**
     * 获取响应头。
     *
     * @param bool $throw 是否在错误响应时抛异常；当前仅传输错误会抛出。
     * @return array<string,array<int,string>> 响应头数组。
     */
    public function getHeaders(bool $throw = true): array
    {
        $this->throwTransportError();

        return $this->headers;
    }

    /**
     * 获取响应体文本。
     *
     * @param bool $throw 是否在错误响应时抛异常；当前仅传输错误会抛出。
     * @return string 响应体。
     */
    public function getContent(bool $throw = true): string
    {
        $this->throwTransportError();

        return $this->content;
    }

    /**
     * 将 JSON 响应体解析为数组。
     *
     * @param bool $throw 是否在错误响应时抛异常；当前仅传输错误和 JSON 解析错误会抛出。
     * @return array<string,mixed>|array<int,mixed> 解析后的数组，空响应返回空数组。
     */
    public function toArray(bool $throw = true): array
    {
        $content = $this->getContent($throw);
        if ($content === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonException(json_last_error_msg(), json_last_error());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * 取消响应读取。
     *
     * @return void
     */
    public function cancel(): void
    {
        $this->canceled = true;
    }

    /**
     * 获取传输层信息。
     *
     * @param string|null $type 指定信息键名，为 null 时返回完整信息。
     * @return mixed 指定信息值或完整信息数组。
     */
    public function getInfo(?string $type = null): mixed
    {
        $info = array_merge([
            'canceled' => $this->canceled,
            'error' => $this->error,
            'http_code' => $this->statusCode,
            'response_headers' => $this->flattenHeaders(),
        ], $this->info);

        return $type === null ? $info : ($info[$type] ?? null);
    }

    /**
     * 若存在传输错误则抛出 Symfony 兼容异常。
     *
     * @return void
     */
    protected function throwTransportError(): void
    {
        if ($this->error !== null && $this->error !== '') {
            throw new TransportException($this->error);
        }
    }

    /**
     * 将多值响应头展开为 `name: value` 字符串列表。
     *
     * @return array<int,string> 展开后的响应头列表。
     */
    protected function flattenHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        }

        return $headers;
    }
}
