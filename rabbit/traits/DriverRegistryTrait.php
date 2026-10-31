<?php

declare(strict_types=1);

namespace Rabbit\traits;

/**
 * 扩展点运行期注册表，与框架分页的静态 maker（`think\Paginator`）同一形态。
 *
 * 使用方约定（三类扩展点已按此实现：支付、上传、采集、直播、物流查询）：
 * - 在自己类里定义 `private const MANIFEST_KEY = '插件 manifest 中的声明字段名'`；
 * - 工厂方法先 `resolveRegistered()`，未命中再走内置 `match` 分支；
 * - `AppService::register()` 每个请求调用 `syncFromPlugins()` 重建。
 *
 * Swoole 常驻进程下静态属性是 worker 级：注册表以标识为键、条目数只取决于当前声明的实现，
 * 且每请求清空重建，因此不会随请求次数增长（等价于框架的 ResetPaginator）。
 */
trait DriverRegistryTrait
{
    /**
     * 运行期注册的实现：标识 => 驱动类名或构造闭包。
     *
     * @var array<string, string|\Closure>
     */
    private static array $drivers = [];

    /**
     * 注册或覆盖一个实现（宿主、插件或业务方在启动阶段调用）。
     *
     * @param string $key 实现标识。
     * @param string|\Closure $driver 驱动类名，或接收配置数组返回驱动的闭包。
     * @return void 无返回值。
     */
    public static function extend(string $key, string|\Closure $driver): void
    {
        self::$drivers[strtolower(trim($key))] = $driver;
    }

    /**
     * 判断指定标识是否已注册。
     *
     * @param string $key 实现标识。
     * @return bool 已注册返回 true。
     */
    public static function hasDriver(string $key): bool
    {
        return isset(self::$drivers[strtolower(trim($key))]);
    }

    /**
     * 按插件运行态重建注册表：先清空，再登记已启用插件声明的实现。
     *
     * 每个请求都会执行，因此安装或停用插件后立即生效，停用插件的实现不会残留。
     *
     * @param array<string, array<string, mixed>> $plugins 插件运行态列表（PluginRegistry::allPlugins）。
     * @return void 无返回值。
     */
    public static function syncFromPlugins(array $plugins): void
    {
        self::$drivers = [];
        $manifestKey = static::MANIFEST_KEY;
        foreach ($plugins as $plugin) {
            if (empty($plugin['enabled']) || !is_array($plugin[$manifestKey] ?? null)) {
                continue;
            }
            foreach ($plugin[$manifestKey] as $key => $driver) {
                if (is_string($key) && $key !== '' && is_string($driver) && $driver !== '') {
                    static::extend($key, $driver);
                }
            }
        }
    }

    /**
     * 解析运行期注册的实现，未注册时返回 null，由各服务工厂继续走内置分支。
     *
     * @param string $key 实现标识。
     * @param array<string, mixed> $config 传给驱动构造函数的配置。
     * @return object|null 注册的实现实例；未注册或构造结果非法时返回 null。
     */
    private static function resolveRegistered(string $key, array $config = []): ?object
    {
        $driver = self::$drivers[strtolower(trim($key))] ?? null;
        if ($driver === null) {
            return null;
        }
        $instance = $driver instanceof \Closure ? $driver($config) : new $driver($config);

        return is_object($instance) ? $instance : null;
    }
}
