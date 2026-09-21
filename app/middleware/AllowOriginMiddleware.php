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

namespace app\middleware;

use app\Request;
use think\facade\Config;
use think\Response;

class AllowOriginMiddleware
{
    /**
     * 统一处理接口跨域响应头。
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next)
    {
        if (!$this->shouldHandle($request)) {
            return $next($request);
        }

        $request->filter([fn($value) => $this->sanitizeInputValue($value)]);
        $headers = $this->buildCorsHeaders($request);

        if ($request->method(true) === 'OPTIONS') {
            return Response::create('ok')->code(200)->header($headers);
        }

        return $next($request)->header($headers);
    }

    /**
     * 判断当前请求是否需要执行跨域处理。
     *
     * 仅对 `api/*` 接口启用，避免影响普通页面请求。
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldHandle(Request $request): bool
    {
        $path = trim($request->pathinfo(), '/');
        return $path === 'api' || strpos($path, 'api/') === 0;
    }

    /**
     * 构建跨域响应头。
     *
     * @param Request $request
     * @return array
     */
    protected function buildCorsHeaders(Request $request): array
    {
        $config = Config::get('cors', []);
        $origin = trim((string)$request->header('origin', ''));
        $allowOrigins = $this->normalizeList($config['allow_origins'] ?? ['*']);
        $allowMethods = $this->normalizeList($config['allow_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        $allowHeaders = $this->normalizeList($config['allow_headers'] ?? []);
        $exposeHeaders = $this->normalizeList($config['expose_headers'] ?? []);
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

        $allowOrigin = $this->resolveAllowOrigin($origin, $allowOrigins, $allowCredentials);
        if ($allowOrigin !== '') {
            $headers['Access-Control-Allow-Origin'] = $allowOrigin;
        }

        return array_filter($headers, static function ($value) {
            return $value !== '';
        });
    }

    /**
     * 清洗接口入参。
     *
     * JSON 字符串会被前端作为普通字符串字段提交，例如配置模板设计器的 content/field_keys；
     * 这类值不能 addslashes 或 strip_tags，否则后续 json_decode 会失败或内容被改写。
     *
     * @param mixed $value 请求字段值
     * @return mixed
     */
    protected function sanitizeInputValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '' || $this->isJsonString($value)) {
            return $value;
        }

        return strip_tags($value);
    }

    /**
     * 判断字符串是否为 JSON 对象或数组。
     *
     * @param string $value 字符串值
     * @return bool
     */
    protected function isJsonString(string $value): bool
    {
        $first = $value[0] ?? '';
        $last = substr($value, -1);
        if (!(($first === '{' && $last === '}') || ($first === '[' && $last === ']'))) {
            return false;
        }

        json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 规范化配置中的列表值。
     *
     * @param mixed $value
     * @return array
     */
    protected function normalizeList($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $items = array_map(static function ($item) {
            return trim((string)$item);
        }, $value);

        $items = array_filter($items, static function ($item) {
            return $item !== '';
        });

        return array_values(array_unique($items));
    }

    /**
     * 解析当前请求应返回的 Origin。
     *
     * @param string $origin
     * @param array $allowOrigins
     * @param bool $allowCredentials
     * @return string
     */
    protected function resolveAllowOrigin(string $origin, array $allowOrigins, bool $allowCredentials): string
    {
        if (!$origin) {
            return in_array('*', $allowOrigins, true) && !$allowCredentials ? '*' : '';
        }

        if (in_array('*', $allowOrigins, true)) {
            return $allowCredentials ? $origin : '*';
        }

        foreach ($allowOrigins as $allowOrigin) {
            if ($this->matchOrigin($origin, $allowOrigin)) {
                return $origin;
            }
        }

        return '';
    }

    /**
     * 判断请求来源是否命中允许规则。
     *
     * 支持精确匹配与 `*` 通配符匹配。
     *
     * @param string $origin
     * @param string $allowOrigin
     * @return bool
     */
    protected function matchOrigin(string $origin, string $allowOrigin): bool
    {
        if ($origin === $allowOrigin) {
            return true;
        }

        $pattern = str_replace('\\*', '[^.]+', preg_quote($allowOrigin, '#'));
        return (bool)preg_match('#^' . $pattern . '$#i', $origin);
    }
}
