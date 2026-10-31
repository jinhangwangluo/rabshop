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

namespace app\common\service\plugin;

use app\common\exception\PluginLifecycleException;
use app\common\service\plugin\contract\PluginLifecycleLockInterface;
use PDO;
use Throwable;

/**
 * 通过独立 MySQL 连接提供跨工作进程的插件生命周期锁，避免 DDL 提交释放锁。
 */
final class DatabasePluginLifecycleLock implements PluginLifecycleLockInterface
{
    /** 生命周期锁连接最多尝试次数；只覆盖可恢复的网络断线。 */
    private const MAX_ACQUIRE_ATTEMPTS = 3;

    /** 两次锁连接尝试之间的等待时间，避免远端连接瞬时抖动时立即重撞。 */
    private const RETRY_DELAY_MICROSECONDS = 200000;

    /**
     * 已持有锁的独立连接与锁名，按随机句柄索引。
     *
     * @var array<string,array{pdo:PDO,key:string}>
     */
    private array $handles = [];

    /**
     * 获取指定插件的独占生命周期锁。
     *
     * @param string $pluginCode 待执行生命周期操作的插件编码。
     * @return string 用于释放锁的随机句柄。
     */
    public function acquire(string $pluginCode): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $pluginCode)) {
            throw new PluginLifecycleException('插件编码无效', 'INVALID_PLUGIN_CODE');
        }
        $settings = config('database.connections.' . config('database.default'));
        if (($settings['type'] ?? '') !== 'mysql' || !empty($settings['deploy'])) {
            throw new PluginLifecycleException('插件生命周期锁要求单主 MySQL 连接', 'PLUGIN_LOCK_UNAVAILABLE');
        }
        $key = 'kp-plugin:' . substr(hash('sha256', $settings['database'] . ':' . $pluginCode), 0, 48);
        $lastFailure = null;
        for ($attempt = 1; $attempt <= self::MAX_ACQUIRE_ATTEMPTS; $attempt++) {
            try {
                $pdo = new PDO(
                    'mysql:host=' . $settings['hostname'] . ';port=' . ($settings['hostport'] ?: '3306') . ';dbname=' . $settings['database'] . ';charset=utf8mb4',
                    $settings['username'],
                    $settings['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 10,
                    ]
                );
                $statement = $pdo->prepare('SELECT GET_LOCK(?, 0)');
                $statement->execute([$key]);
                if ((int)$statement->fetchColumn() !== 1) {
                    throw new PluginLifecycleException('插件正在执行其他生命周期任务', 'PLUGIN_LOCKED');
                }

                $handle = bin2hex(random_bytes(16));
                $this->handles[$handle] = ['pdo' => $pdo, 'key' => $key];
                return $handle;
            } catch (PluginLifecycleException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $lastFailure = $exception;
                if (!$this->isTransientConnectionFailure($exception) || $attempt === self::MAX_ACQUIRE_ATTEMPTS) {
                    break;
                }
                usleep(self::RETRY_DELAY_MICROSECONDS);
            }
        }

        throw new PluginLifecycleException('插件生命周期锁连接失败，请稍后重试', 'PLUGIN_LOCK_UNAVAILABLE', 0, $lastFailure);
    }

    /**
     * 判断数据库异常是否属于可以重新建立连接后恢复的断线。
     *
     * @param Throwable $exception 数据库连接异常。
     * @return bool 仅对 MySQL 2006、2013 及等价断线文本返回 true。
     */
    private function isTransientConnectionFailure(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, '2006')
            || str_contains($message, '2013')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection');
    }

    /**
     * 释放句柄对应的生命周期锁，并移除连接引用。
     *
     * @param string $handle 获取锁时返回的句柄。
     * @return void 无返回值；未知句柄不执行操作。
     */
    public function release(string $handle): void
    {
        $entry = $this->handles[$handle] ?? null;
        if ($entry === null) {
            return;
        }
        try {
            $statement = $entry['pdo']->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$entry['key']]);
        } catch (Throwable $exception) {
            // 连接已被 MySQL 回收时锁会随会话自动释放，不能覆盖生命周期操作结果。
            if (!$this->isTransientConnectionFailure($exception)) {
                throw $exception;
            }
        } finally {
            unset($this->handles[$handle]);
        }
    }
}
