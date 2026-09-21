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

use app\common\plugin\PluginRegistry;
use app\common\service\plugin\DatabasePluginLifecycleRepository;
use Throwable;
use think\facade\Log;

/**
 * 在工作进程启动时加载本地清单和已安装状态，不在请求处理中动态变更监听器。
 */
final class PluginRegistryBootstrap
{
    /**
     * 已注册的插件自动加载命名空间，避免同一进程重复注册。
     *
     * @var array<string, bool>
     */
    private static array $autoloadPrefixes = [];

    /**
     * 载入插件清单及持久化安装状态，只有已安装且启用的插件具有运行能力。
     *
     * @param PluginRegistry $registry 当前工作进程的插件注册表。
     * @param ?string $manifestRoot 插件源码根目录；为空时读取服务器配置。
     * @return int 实际加载到注册表的记录数量。
     */
    public function load(PluginRegistry $registry, ?string $manifestRoot = null): int
    {
        $registry->clear();

        $manifestRoot = $this->resolveManifestRoot($manifestRoot);
        $loaded = 0;

        foreach ($this->scanManifests($manifestRoot) as $manifestFile) {
            $manifest = $this->readManifest($manifestFile);
            if ($manifest === null) {
                continue;
            }
            try {
                $this->registerSourceAutoloader($manifest, dirname($manifestFile) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'src');
                $registry->registerManifest(array_merge($manifest, [
                    'enabled' => false,
                    'lifecycle_status' => 'uninstalled',
                ]));
                $loaded++;
            } catch (Throwable $exception) {
                Log::warning('插件清单加载失败', [
                    'file' => $manifestFile,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($this->loadInstalledPlugins() as $plugin) {
            $code = (string)($plugin['code'] ?? '');
            if ($code === '') {
                continue;
            }
            try {
                $plugin['manifest'] = is_array($plugin['manifest_json'] ?? null) ? $plugin['manifest_json'] : [];
                $plugin['enabled'] = (int)($plugin['enabled'] ?? 0) === 1 && ($plugin['lifecycle_status'] ?? '') === 'active';
                $registry->registerPlugin($code, $plugin);
                $loaded++;
            } catch (Throwable $exception) {
                Log::warning('已安装插件状态加载失败', [
                    'plugin_code' => $code,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $loaded;
    }

    /**
     * 按清单命名空间和固定 backend/src 目录注册受限 PSR-4 加载器。
     *
     * @param array<string, mixed> $manifest 本地插件清单，不能来自任意客户端输入。
     * @param string $sourceRoot 服务器确定的插件后端源码根目录。
     * @return void 无返回值；缺少有效声明或源码目录时跳过。
     */
    private function registerSourceAutoloader(array $manifest, string $sourceRoot): void
    {
        $namespace = trim((string)($manifest['namespace'] ?? ''), "\\ ");
        if ($namespace === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace) || !is_dir($sourceRoot)) {
            return;
        }
        $prefix = $namespace . '\\';
        if (isset(self::$autoloadPrefixes[$prefix])) {
            return;
        }
        self::$autoloadPrefixes[$prefix] = true;
        spl_autoload_register(static function (string $class) use ($prefix, $sourceRoot): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            if ($relative === '' || str_contains($relative, '..') || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $relative)) {
                return;
            }
            $file = rtrim($sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }, true, true);
    }

    /**
     * 解析调用方或服务器配置的插件清单目录。
     *
     * @param ?string $manifestRoot 可选插件清单根目录。
     * @return string 规范化后的清单根目录。
     */
    private function resolveManifestRoot(?string $manifestRoot): string
    {
        $root = trim((string)($manifestRoot ?? ''));
        if ($root === '' && function_exists('config')) {
            try {
                $root = (string)config('plugin.registry.manifest_path', root_path('plugins'));
            } catch (Throwable) {
                $root = root_path('plugins');
            }
        }
        return $root !== '' ? rtrim($root, DIRECTORY_SEPARATOR) : root_path('plugins');
    }

    /**
     * 按稳定顺序扫描插件根目录下的固定清单文件。
     *
     * @param string $manifestRoot 插件清单根目录。
     * @return list<string> 已排序的清单文件路径列表。
     */
    private function scanManifests(string $manifestRoot): array
    {
        if (!is_dir($manifestRoot)) {
            return [];
        }
        $files = glob($manifestRoot . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'plugin.json');
        if ($files === false) {
            return [];
        }
        sort($files, SORT_STRING);
        return array_values($files);
    }

    /**
     * 解析单份插件清单，失败时记录告警而不阻断其他插件加载。
     *
     * @param string $manifestFile 本地插件清单文件路径。
     * @return array<string,mixed>|null 解析后的清单；无法读取或解析时为 null。
     */
    private function readManifest(string $manifestFile): ?array
    {
        try {
            $json = file_get_contents($manifestFile);
            if ($json === false || trim($json) === '') {
                throw new \RuntimeException('empty manifest');
            }
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest)) {
                throw new \RuntimeException('manifest is not array');
            }
            return $manifest;
        } catch (Throwable $exception) {
            Log::warning('插件清单解析失败', [
                'file' => $manifestFile,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 读取持久化插件安装状态，仓储不可用时保留告警并返回空列表。
     *
     * @return list<array<string,mixed>> 本次查询到的已安装插件记录列表。
     */
    private function loadInstalledPlugins(): array
    {
        try {
            return (new DatabasePluginLifecycleRepository())->listPlugins([
                'page' => 1,
                'limit' => 100,
            ])['list'] ?? [];
        } catch (Throwable $exception) {
            Log::warning('已安装插件状态加载失败', [
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }
}
