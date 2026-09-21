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
use InvalidArgumentException;
use think\facade\Route;
use app\middleware\PluginEnabledMiddleware;

/**
 * 加载本地插件声明的原生路由文件，并验证固定入口与插件目录边界。
 */
final class PluginRouteRegistrar
{
    /**
     * 三端标识与清单路由入口键的固定对应关系。
     *
     * @var array<string, string>
     */
    private const SURFACE_ENTRYPOINTS = [
        'admin' => 'admin_routes',
        'merchant' => 'merchant_routes',
        'mobile' => 'mobile_routes',
    ];

    /**
     * 当前工作进程已加载的插件端入口，避免重复路由注册。
     *
     * @var array<string, bool>
     */
    private array $loadedEntrypoints = [];

    /**
     * 注册当前工作进程中的本地插件路由，不解析自定义路由数组。
     * 路由可用性由 PluginEnabledMiddleware 在每次请求时复查持久化状态。
     *
     * @param PluginRegistry $registry 插件运行时注册表。
     * @param string $manifestRoot 插件清单根目录。
     * @return int 本次实际加载的原生路由文件数量。
     */
    public function register(PluginRegistry $registry, string $manifestRoot): int
    {
        $root = realpath($manifestRoot);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException('插件清单根目录不存在');
        }

        $loaded = 0;
        foreach ($registry->allPlugins() as $pluginCode => $plugin) {
            $code = $this->normalisePluginCode((string)$pluginCode);
            $pluginRoot = realpath($root . DIRECTORY_SEPARATOR . $code);
            if ($pluginRoot === false || !$this->isWithin($root, $pluginRoot)) {
                throw new InvalidArgumentException('插件源码目录不存在：' . $code);
            }

            $surfaces = is_array($plugin['surfaces'] ?? null) ? $plugin['surfaces'] : [];
            $entrypoints = is_array($plugin['entrypoints'] ?? null) ? $plugin['entrypoints'] : [];
            foreach (self::SURFACE_ENTRYPOINTS as $surface => $entrypointKey) {
                if (!in_array($surface, $surfaces, true)) {
                    continue;
                }

                $relative = (string)($entrypoints[$entrypointKey] ?? '');
                $expected = 'backend/routes/' . $surface . '.php';
                if ($relative !== $expected) {
                    throw new InvalidArgumentException('插件路由入口不符合固定目录契约：' . $code . '/' . $surface);
                }

                $routeFile = realpath($pluginRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
                if ($routeFile === false || !$this->isWithin($pluginRoot, $routeFile) || !is_file($routeFile)) {
                    throw new InvalidArgumentException('插件路由文件不存在或越过插件目录：' . $code . '/' . $surface);
                }

                $entrypoint = $code . ':' . $surface;
                if (isset($this->loadedEntrypoints[$entrypoint])) {
                    continue;
                }

                Route::group('', function () use ($routeFile): void {
                    require $routeFile;
                })->middleware(PluginEnabledMiddleware::class);

                $this->loadedEntrypoints[$entrypoint] = true;
                $loaded++;
            }
        }

        return $loaded;
    }

    /**
     * 规范化并验证插件编码，避免将任意输入拼入源码路径。
     *
     * @param string $pluginCode 注册表中的插件编码。
     * @return string 已验证的小写插件编码。
     */
    private function normalisePluginCode(string $pluginCode): string
    {
        $pluginCode = strtolower(trim($pluginCode));
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $pluginCode)) {
            throw new InvalidArgumentException('插件编码无效');
        }
        return $pluginCode;
    }

    /**
     * 按目录分隔符边界判断目标路径是否属于根目录。
     *
     * @param string $root 允许的根目录。
     * @param string $path 待检查的实际文件或目录路径。
     * @return bool 目标是否位于指定根目录内。
     */
    private function isWithin(string $root, string $path): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path . (is_dir($path) ? DIRECTORY_SEPARATOR : ''), $root);
    }
}
