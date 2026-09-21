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

/**
 * 解析固定安装声明并限制插件资源边界，制品输入不能选择宿主文件或 Shell 命令。
 */
final class PluginInstallManifest
{
    /**
     * 读取并校验插件迁移、移动页面、配置键与装修组件的安装声明。
     *
     * @param string $pluginRoot 插件实际部署根目录。
     * @param string $code 预期插件编码。
     * @return array<string,mixed> 经过归属与边界校验的安装资源声明。
     */
    public function read(string $pluginRoot, string $code): array
    {
        $file = $this->safeFile($pluginRoot, 'resources/lifecycle.json');
        $data = json_decode((string)file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['plugin_code'] ?? '') !== $code || ($data['schema_version'] ?? '') !== '1.0') {
            throw new PluginLifecycleException('插件安装声明无效', 'PLUGIN_RESOURCE_INVALID');
        }
        if (array_key_exists('requires_operational_cleanup', $data) && !is_bool($data['requires_operational_cleanup'])) {
            throw new PluginLifecycleException('插件运营清理声明必须为布尔值', 'PLUGIN_RESOURCE_INVALID');
        }
        foreach ($data['migrations'] ?? [] as $migration) {
            if (!is_array($migration) || !preg_match('#^backend/database/migrations/[0-9]{14}_[a-z0-9_]+\.php$#', (string)($migration['file'] ?? ''))
                || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', (string)($migration['class'] ?? ''))) {
                throw new PluginLifecycleException('插件迁移声明无效', 'PLUGIN_MIGRATION_INVALID');
            }
            $this->safeFile($pluginRoot, $migration['file']);
        }
        foreach ($data['mobile_pages'] ?? [] as $page) {
            if (!is_array($page) || !preg_match('#^plugins/' . preg_quote($code, '#') . '/pages/[a-z0-9_/]+$#', (string)($page['path'] ?? ''))
                || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string)($page['key'] ?? ''))) {
                throw new PluginLifecycleException('插件移动页面声明无效', 'PLUGIN_RESOURCE_INVALID');
            }
        }
        foreach ($data['config_keys'] ?? [] as $key) {
            if (!is_string($key) || !str_starts_with($key, 'plugin_' . $code . '_') || !preg_match('/^[a-z0-9_]{1,128}$/', $key)) {
                throw new PluginLifecycleException('插件配置声明越界', 'PLUGIN_RESOURCE_INVALID');
            }
        }
        foreach ($data['diy_components'] ?? [] as $type) {
            if (!is_string($type) || ($type !== $code && !str_starts_with($type, $code . '_'))) {
                throw new PluginLifecycleException('插件组件声明越界', 'PLUGIN_RESOURCE_INVALID');
            }
        }
        return $data;
    }

    /**
     * 拒绝路径穿越及所有层级软链接，包括插件根目录自身。
     *
     * @param string $root 插件资源根目录。
     * @param string $relative 声明中受允许的相对文件路径。
     * @return string 插件根目录内可读资源的实际路径。
     */
    public function safeFile(string $root, string $relative): string
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\')
            || preg_match('#(?:^|/)\.\.?(?:/|$)|^[A-Za-z]:|\x00#', $relative) || is_link($root)) {
            throw new PluginLifecycleException('插件资源路径不安全', 'PLUGIN_PATH_UNSAFE');
        }
        $realRoot = realpath($root);
        $path = $root;
        foreach (explode('/', $relative) as $part) {
            $path .= '/' . $part;
            if (is_link($path)) {
                throw new PluginLifecycleException('插件资源不允许软链接', 'PLUGIN_PATH_UNSAFE');
            }
        }
        $real = realpath($path);
        if ($realRoot === false || $real === false || !is_file($real) || !is_readable($real)
            || !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new PluginLifecycleException('插件资源不存在或越界', 'PLUGIN_PATH_UNSAFE');
        }
        return $real;
    }
}
