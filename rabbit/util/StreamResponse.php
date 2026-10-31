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

declare(strict_types=1);

namespace Rabbit\util;

use think\Container;
use think\facade\Config;
use think\Request;
use think\swoole\response\Iterator as IteratorResponse;
use Throwable;

/**
 * 通用流式响应。
 */
class StreamResponse extends IteratorResponse
{
    /**
     * @param callable $producer 流式输出闭包，内部可直接 echo 内容
     */
    public function __construct(callable $producer)
    {
        parent::__construct($this->makeIterator($producer));
    }

    /**
     * 创建流式响应。
     *
     * @param callable $producer 流式输出闭包，内部可直接 echo 内容
     * @param array<string, string|null> $headers 响应头
     * @param string $contentType 响应 Content-Type
     * @param int $code HTTP 状态码
     * @param string $charset 响应字符集
     * @return self
     */
    public static function streamResponse(
        callable $producer,
        array $headers = [],
        string $contentType = 'text/plain',
        int $code = 200,
        string $charset = 'utf-8'
    ): self {
        return (new self($producer))
            ->code($code)
            ->contentType($contentType, $charset)
            ->header($headers);
    }

    /**
     * 创建 SSE 流式响应。
     *
     * @param callable $producer 事件生产器，接收 callable(array $payload): void
     * @param Request $request 当前请求，用于补齐 CORS 响应头
     * @param array<string, string|null> $headers 额外响应头
     * @param int $code HTTP 状态码
     * @return self
     */
    public static function eventStreamResponse(
        callable $producer,
        Request $request,
        array $headers = [],
        int $code = 200
    ): self {
        return self::streamResponse(
            static function () use ($producer): void {
                $producer(static function (array $payload): void {
                    echo self::formatEvent($payload);
                });
            },
            array_merge(self::buildEventStreamHeaders($request), $headers),
            'text/event-stream',
            $code
        );
    }

    /**
     * 格式化 SSE data 事件。
     *
     * @param array $payload 事件数据
     * @return string
     */
    public static function formatEvent(array $payload): string
    {
        return 'data: ' . json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) . "\n\n";
    }

    /**
     * 普通 FPM 响应路径逐段发送内容。
     *
     * @return void
     */
    public function send(): void
    {
        try {
            if (!headers_sent()) {
                http_response_code($this->code);

                foreach ($this->header as $name => $val) {
                    header($name . (!is_null($val) ? ':' . $val : ''));
                }

                if ($this->cookie) {
                    $this->cookie->save();
                }
            }

            foreach ($this->getIterator() as $chunk) {
                if (!is_string($chunk) && !is_numeric($chunk) && !is_callable([$chunk, '__toString'])) {
                    continue;
                }

                $this->sendData((string)$chunk);
                $this->flushOutput();
            }

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        } catch (Throwable $exception) {
            $this->reportException($exception);
        }
    }

    /**
     * 将闭包 echo 的内容转换为可被 ThinkSwoole write() 消费的迭代器。
     *
     * Swoole 协程环境走 Fiber+ob 分片（响应由 sendIterator 逐块 write）；
     * php-fpm 等同步 SAPI 下 ob 回调运行于 Fiber 之外，挂起必然失败，改走直出路径。
     */
    private function makeIterator(callable $producer): \Generator
    {
        if (class_exists(\Swoole\Coroutine::class) && \Swoole\Coroutine::getCid() >= 0) {
            yield from $this->makeFiberIterator($producer);
            return;
        }

        yield from $this->makeDirectIterator($producer);
    }

    /**
     * FPM 同步路径：清空输出缓冲后直接执行 producer，echo 经 flush 直达客户端。
     *
     * @return \Generator<string>
     */
    private function makeDirectIterator(callable $producer): \Generator
    {
        // 空分片驱动 send() 先发出响应头，再清理缓冲，保证 echo 不再被缓存吞掉。
        yield '';
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        flush();

        $producer();
    }

    /**
     * PHP 8 Fiber 模式下按 echo 分片输出。
     *
     * @param callable $producer 流式输出闭包
     * @return \Generator<string>
     */
    private function makeFiberIterator(callable $producer): \Generator
    {
        $fiber = new \Fiber(function () use ($producer): void {
            $level = ob_get_level();

            ob_start(function (string $buffer): string {
                if ($buffer !== '') {
                    \Fiber::suspend($buffer);
                }

                return '';
            }, 1);

            try {
                $producer();

                while (ob_get_level() > $level) {
                    ob_end_flush();
                }
            } catch (Throwable $exception) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }

                $this->reportException($exception);
            }
        });

        try {
            $chunk = $fiber->start();

            while (true) {
                if ((is_string($chunk) || is_numeric($chunk)) && (string)$chunk !== '') {
                    yield (string)$chunk;
                    $this->yieldToSwoole();
                }

                if (!$fiber->isSuspended()) {
                    break;
                }

                $chunk = $fiber->resume();
            }
        } catch (Throwable $exception) {
            $this->reportException($exception);
        }
    }

    /**
     * 无 Fiber 时退化为一次性缓冲输出。
     *
     * @param callable $producer 流式输出闭包
     * @return \Generator<string>
     */
    private function makeBufferedIterator(callable $producer): \Generator
    {
        $level = ob_get_level();
        ob_start();

        try {
            $producer();
            $content = (string)ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            $this->reportException($exception);
            return;
        }

        if ($content !== '') {
            yield $content;
            $this->yieldToSwoole();
        }
    }

    /**
     * 在 Swoole 协程中让出当前协程，给 response write 刷出分片的机会。
     *
     * @return void
     */
    private function yieldToSwoole(): void
    {
        if (!class_exists(\Swoole\Coroutine::class)) {
            return;
        }

        if (\Swoole\Coroutine::getCid() <= 0) {
            return;
        }

        \Swoole\Coroutine::sleep(0.001);
    }

    /**
     * 刷出当前输出缓冲。
     *
     * @return void
     */
    private function flushOutput(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    /**
     * 记录流式响应异常。
     *
     * @param Throwable $exception 异常
     * @return void
     */
    private function reportException(Throwable $exception): void
    {
        try {
            Container::getInstance()->log->error($exception->getMessage());
        } catch (Throwable) {
            // 日志组件不可用时不影响响应结束。
        }
    }

    /**
     * 构建 SSE 与跨域响应头。
     *
     * @param Request $request 当前请求
     * @return array<string, string>
     */
    private static function buildEventStreamHeaders(Request $request): array
    {
        return array_merge([
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ], self::buildCorsHeaders($request));
    }

    /**
     * 按项目 CORS 配置构建流式响应头。
     *
     * @param Request $request 当前请求
     * @return array<string, string>
     */
    private static function buildCorsHeaders(Request $request): array
    {
        $config = Config::get('cors', []);
        $origin = trim((string)$request->header('origin', ''));
        $allowOrigins = self::normalizeList($config['allow_origins'] ?? ['*']);
        $allowMethods = self::normalizeList(
            $config['allow_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        );
        $allowHeaders = self::normalizeList($config['allow_headers'] ?? []);
        $exposeHeaders = self::normalizeList($config['expose_headers'] ?? []);
        $allowCredentials = (bool)($config['allow_credentials'] ?? true);
        $maxAge = (string)($config['max_age'] ?? 1728000);

        $headers = [
            'Access-Control-Allow-Methods' => implode(',', $allowMethods),
            'Access-Control-Allow-Headers' => implode(',', $allowHeaders),
            'Access-Control-Allow-Credentials' => $allowCredentials ? 'true' : 'false',
            'Access-Control-Max-Age' => $maxAge,
            'Access-Control-Expose-Headers' => implode(',', $exposeHeaders),
            'Vary' => 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
        ];

        $allowOrigin = self::resolveAllowOrigin($origin, $allowOrigins, $allowCredentials);
        if ($allowOrigin !== '') {
            $headers['Access-Control-Allow-Origin'] = $allowOrigin;
        }

        return array_filter($headers, static fn(string $value): bool => $value !== '');
    }

    /**
     * 规范化 CORS 列表配置。
     *
     * @param mixed $value 字符串或数组形式的配置值
     * @return string[]
     */
    private static function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $items = array_map(static fn($item): string => trim((string)$item), $value);
        $items = array_filter($items, static fn(string $item): bool => $item !== '');

        return array_values(array_unique($items));
    }

    /**
     * 解析当前请求可返回的 Access-Control-Allow-Origin。
     *
     * @param string $origin 请求来源
     * @param string[] $allowOrigins 允许来源规则
     * @param bool $allowCredentials 是否允许携带凭证
     * @return string
     */
    private static function resolveAllowOrigin(string $origin, array $allowOrigins, bool $allowCredentials): string
    {
        if ($origin === '') {
            return in_array('*', $allowOrigins, true) && !$allowCredentials ? '*' : '';
        }

        if (in_array('*', $allowOrigins, true)) {
            return $allowCredentials ? $origin : '*';
        }

        foreach ($allowOrigins as $allowOrigin) {
            if (self::matchOrigin($origin, $allowOrigin)) {
                return $origin;
            }
        }

        return '';
    }

    /**
     * 判断请求来源是否命中允许规则。
     *
     * @param string $origin 请求来源
     * @param string $allowOrigin 允许来源规则，支持 * 通配符
     * @return bool
     */
    private static function matchOrigin(string $origin, string $allowOrigin): bool
    {
        if ($origin === $allowOrigin) {
            return true;
        }

        $pattern = str_replace('\\*', '[^.]+', preg_quote($allowOrigin, '#'));
        return (bool)preg_match('#^' . $pattern . '$#i', $origin);
    }
}
