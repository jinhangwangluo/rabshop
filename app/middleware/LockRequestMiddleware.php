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
use Rabbit\util\Lock;
use RuntimeException;
use think\facade\Log;
use think\Response;
use Throwable;

/**
 * 后台写请求防重复提交锁.
 */
class LockRequestMiddleware
{
    private const LOCK_EXPIRE = 8;

    private const SKIP_METHODS = ['get', 'head', 'options'];

    /**
     * 请求锁中间件
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->shouldSkipLock($request)) {
            return $next($request);
        }

        $key = $this->buildLockKey($request);
        $value = bin2hex(random_bytes(16));

        /** @var Lock $lock */
        $lock = app()->make(Lock::class);

        try {
            if (!$lock->tryLock($key, $value, self::LOCK_EXPIRE)) {
                return d_json()->fail('请求处理中，请勿重复提交', [], 429);
            }
        } catch (RuntimeException $exception) {
            Log::warning('[request-lock] lock unavailable, pass without lock: ' . $exception->getMessage(), [
                'key' => $key,
                'path' => $request->pathinfo(),
                'method' => $request->method(),
            ]);

            return $next($request);
        }

        try {
            return $next($request);
        } finally {
            $this->releaseLock($lock, $key, $value, $request);
        }
    }

    /**
     * 判断当前请求是否跳过锁.
     */
    private function shouldSkipLock(Request $request): bool
    {
        if (in_array(strtolower($request->method()), self::SKIP_METHODS, true)) {
            return true;
        }

        $rule = $request->rule();
        if ($rule && $rule->getOption('_lock', true) === false) {
            return true;
        }

        return $this->isMultipartRequest($request);
    }

    /**
     * 上传和分片上传请求不走重复锁，避免同一管理员并发上传被误拦截.
     */
    private function isMultipartRequest(Request $request): bool
    {
        $contentType = strtolower((string)$request->header('content-type', ''));

        return str_contains($contentType, 'multipart/form-data');
    }

    /**
     * 构建请求锁 key.
     */
    private function buildLockKey(Request $request): string
    {
        $rule = $request->rule();
        $routeName = $rule ? (string)$rule->getName() : '';
        $routeKey = $routeName !== '' ? $routeName : $request->pathinfo();
        $routeParams = $this->normalizeValue($request->route());
        $requestParams = $this->normalizeValue($request->param());
        $rawContent = trim($request->getContent());

        $fingerprint = [
            'admin_id' => (int)($request->adminId ?? 0),
            'method' => strtoupper($request->method()),
            'route' => $routeKey,
            'route_params' => $routeParams,
            'params' => $requestParams,
            'content_hash' => $rawContent === '' ? '' : sha1($rawContent),
        ];

        return 're:' . sha1(json_encode($fingerprint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 递归规范化数组，保证同一组参数生成稳定摘要.
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeValue($item);
        }

        return $value;
    }

    /**
     * 释放锁。释放异常只记录日志，不覆盖业务响应.
     */
    private function releaseLock(Lock $lock, string $key, string $value, Request $request): void
    {
        try {
            $lock->unlock($key, $value);
        } catch (Throwable $throwable) {
            Log::warning('[request-lock] release failed: ' . $throwable->getMessage(), [
                'key' => $key,
                'path' => $request->pathinfo(),
                'method' => $request->method(),
            ]);
        }
    }
}
