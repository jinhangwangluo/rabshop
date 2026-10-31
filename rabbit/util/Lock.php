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

namespace Rabbit\util;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Cache;
use think\facade\Log;
use Throwable;

class Lock
{
    // 默认锁前缀
    private string $prefix = 'lock_';

    // 默认最大重试次数
    private int $maxRetries = 3;

    // 默认重试间隔(毫秒)
    private int $retryDelay = 1000;

    /**
     * 执行带锁的操作
     *
     * @param string $key 锁标识
     * @param callable $fn 要执行的回调函数
     * @param int $expire 锁过期时间(秒)
     * @param int|null $maxRetries 最大重试次数(为null时使用默认值)
     * @param int|null $retryDelay 重试间隔(毫秒，为null时使用默认值)
     * @return mixed 回调函数的返回值
     * @throws RuntimeException 当获取锁失败或执行回调异常时抛出
     */
    public function exec(
        string   $key,
        callable $fn,
        int      $expire = 6,
        ?int     $maxRetries = null,
        ?int     $retryDelay = null
    )
    {
        $this->validateParams($key, $expire, $maxRetries, $retryDelay);

        $maxRetries = $maxRetries ?? $this->maxRetries;
        $retryDelay = $retryDelay ?? $this->retryDelay;
        $lockValue = $this->generateRandomValue();
        $success = false;

        try {
            // 获取锁
            $success = $this->lock($key, $lockValue, $expire, $maxRetries, $retryDelay);
            if (!$success) {
                throw new RuntimeException("获取锁失败: {$key}");
            }

            // 执行回调并返回结果
            return $fn();
        } finally {
            // 确保锁被释放
            if ($success) {
                try {
                    $this->unlock($key, $lockValue);
                } catch (Throwable $throwable) {
                    Log::warning('[lock] release failed: ' . $throwable->getMessage(), [
                        'key' => $key,
                    ]);
                }
            }
        }
    }

    /**
     * 尝试获取锁
     *
     * @param string $key 锁标识
     * @param string $value 锁值(用于安全释放)
     * @param int $expire 过期时间(秒)
     * @return bool 是否获取成功
     */
    public function tryLock(string $key, string $value, int $expire): bool
    {
        $this->validateLockKey($key);
        $this->validateLockValue($value);
        $this->validateExpire($expire);

        try {
            $redis = Cache::store('redis')->handler();
            $result = $redis->set(
                $this->getLockKey($key),
                $value,
                ['NX', 'EX' => $expire]
            );

            return $this->isSetSuccessful($result);
        } catch (Throwable $e) {
            throw new RuntimeException("尝试获取锁失败: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 获取锁(带重试机制)
     *
     * @param string $key 锁标识
     * @param string $value 锁值
     * @param int $expire 过期时间(秒)
     * @param int $maxRetries 最大重试次数
     * @param int $retryDelay 重试间隔(毫秒)
     * @return bool 是否获取成功
     */
    public function lock(
        string $key,
        string $value,
        int    $expire,
        int    $maxRetries,
        int    $retryDelay
    ): bool
    {
        $this->validateLockKey($key);
        $this->validateLockValue($value);
        $this->validateExpire($expire);
        $this->validateRetryOptions($maxRetries, $retryDelay);

        $attempts = 0;

        do {
            if ($this->tryLock($key, $value, $expire)) {
                return true;
            }

            $attempts++;
            if ($attempts < $maxRetries) {
                usleep($retryDelay * 1000); // 转换为微秒
            }
        } while ($attempts < $maxRetries);

        return false;
    }

    /**
     * 释放锁
     *
     * @param string $key 锁标识
     * @param string $value 锁值(用于验证所有权)
     * @return bool 是否释放成功
     */
    public function unlock(string $key, string $value): bool
    {
        $this->validateLockKey($key);
        $this->validateLockValue($value);

        try {
            $script = <<<EOF
if (redis.call('get', KEYS[1]) == ARGV[1]) then
    return redis.call('del', KEYS[1])
else
    return 0
end
EOF;

            $redis = Cache::store('redis')->handler();
            $result = $redis->eval($script, [
                $this->getLockKey($key),
                $value
            ], 1);

            return (int)$result > 0;
        } catch (Throwable $e) {
            throw new RuntimeException("释放锁失败: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 生成随机锁值
     *
     * @return string 随机字符串
     */
    private function generateRandomValue(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 生成完整的锁键名
     *
     * @param string $key 原始键名
     * @return string 带前缀的键名
     */
    private function getLockKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * 验证参数有效性
     *
     * @param string $key
     * @param int $expire
     * @param int|null $maxRetries
     * @param int|null $retryDelay
     * @throws InvalidArgumentException
     */
    private function validateParams(
        string $key,
        int    $expire,
        ?int   $maxRetries,
        ?int   $retryDelay
    ): void
    {
        $this->validateLockKey($key);
        $this->validateExpire($expire);
        $this->validateRetryOptions($maxRetries, $retryDelay);
    }

    /**
     * 验证锁键名。
     *
     * @param string $key
     * @return void
     */
    private function validateLockKey(string $key): void
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException("锁标识不能为空");
        }
    }

    /**
     * 验证锁值。
     *
     * @param string $value
     * @return void
     */
    private function validateLockValue(string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException("锁值不能为空");
        }
    }

    /**
     * 验证锁过期时间。
     *
     * @param int $expire
     * @return void
     */
    private function validateExpire(int $expire): void
    {
        if ($expire <= 0) {
            throw new InvalidArgumentException("过期时间必须大于0");
        }
    }

    /**
     * 验证重试配置。
     *
     * @param int|null $maxRetries
     * @param int|null $retryDelay
     * @return void
     */
    private function validateRetryOptions(?int $maxRetries, ?int $retryDelay): void
    {
        if ($maxRetries !== null && $maxRetries < 1) {
            throw new InvalidArgumentException("最大重试次数必须大于0");
        }

        if ($retryDelay !== null && $retryDelay < 1) {
            throw new InvalidArgumentException("重试间隔必须大于0");
        }
    }

    /**
     * 判断 Redis SET 命令是否成功。
     *
     * @param mixed $result
     * @return bool
     */
    private function isSetSuccessful(mixed $result): bool
    {
        if ($result === true) {
            return true;
        }

        if (is_string($result)) {
            return strtoupper($result) === 'OK';
        }

        return is_object($result)
            && method_exists($result, '__toString')
            && strtoupper((string)$result) === 'OK';
    }

    /**
     * 设置默认最大重试次数
     *
     * @param int $maxRetries
     * @return $this
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->validateRetryOptions($maxRetries, null);
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * 设置默认重试间隔(毫秒)
     *
     * @param int $retryDelay
     * @return $this
     */
    public function setRetryDelay(int $retryDelay): self
    {
        $this->validateRetryOptions(null, $retryDelay);
        $this->retryDelay = $retryDelay;
        return $this;
    }
}
