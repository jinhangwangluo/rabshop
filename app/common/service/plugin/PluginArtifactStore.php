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
use ZipArchive;

/**
 * 按内容摘要保存受信制品，只有已验证下载流程可以创建持久引用。
 */
final class PluginArtifactStore
{
    /**
     * 指定受信制品的服务器存储目录。
     *
     * @param string $directory 服务器配置的制品存储目录。
     * @return void 无返回值。
     */
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * 保存已验证制品并重新校验内容，不接受任意路径作为持久引用。
     *
     * @param string $verifiedPath 已验证下载文件的临时路径。
     * @param string $sha256 已验证制品的 SHA-256 摘要。
     * @return string 不暴露文件系统路径的本地制品引用。
     */
    public function retain(string $verifiedPath, string $sha256): string
    {
        $this->assertHash($sha256);
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new PluginLifecycleException('无法保存已验证制品', 'ARTIFACT_STORE_UNAVAILABLE');
        }
        $target = $this->directory . '/' . $sha256 . '.zip';
        if (is_link($target) || is_link($this->directory)) {
            throw new PluginLifecycleException('制品存储路径不安全', 'ARTIFACT_STORE_UNSAFE');
        }
        if (!is_file($target)) {
            $temporary = tempnam($this->directory, 'verified-');
            if ($temporary === false || !copy($verifiedPath, $temporary)) {
                throw new PluginLifecycleException('无法保存已验证制品', 'ARTIFACT_STORE_UNAVAILABLE');
            }
            if (!hash_equals($sha256, (string)hash_file('sha256', $temporary)) || !rename($temporary, $target)) {
                @unlink($temporary);
                throw new PluginLifecycleException('制品持久化校验失败', 'ARTIFACT_STORE_HASH_MISMATCH');
            }
            chmod($target, 0600);
        }
        $this->resolve($sha256);
        return $sha256;
    }

    /**
     * 加载可执行资源前重新校验持久引用对应的文件摘要。
     *
     * @param string $reference 内容摘要形式的本地制品引用。
     * @return string 重新验证通过的制品实际路径。
     */
    public function resolve(string $reference): string
    {
        $this->assertHash($reference);
        $path = $this->directory . '/' . $reference . '.zip';
        if (is_link($path) || !is_file($path) || !hash_equals($reference, (string)hash_file('sha256', $path))) {
            throw new PluginLifecycleException('已验证制品不存在或已变更', 'ARTIFACT_STORE_HASH_MISMATCH');
        }
        return $path;
    }

    /**
     * 核对签名后端和资源已精确部署，不部署任意代码且前端仍由构建流程发布。
     *
     * @param string $reference 已验证制品的持久引用。
     * @param string $pluginRoot 插件实际部署根目录。
     * @param string $code 任务对应的插件编码。
     * @param string $version 任务对应的目标版本。
     * @return array<string,mixed> 与部署内容一致的受信签名清单。
     */
    public function assertDeployed(string $reference, string $pluginRoot, string $code, string $version): array
    {
        $zip = new ZipArchive();
        if ($zip->open($this->resolve($reference)) !== true) {
            throw new PluginLifecycleException('制品不是有效 ZIP', 'ARTIFACT_ZIP_INVALID');
        }
        try {
            if ($zip->numFiles > 10000) {
                throw new PluginLifecycleException('制品文件数超限', 'ARTIFACT_ZIP_INVALID');
            }
            $seen = [];
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string)($stat['name'] ?? '');
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '\\')
                    || preg_match('#(?:^|/)\.\.?(?:/|$)|^[A-Za-z]:|\x00#', $name)) {
                    throw new PluginLifecycleException('制品包含不安全路径', 'ARTIFACT_ZIP_UNSAFE_PATH');
                }
                $zip->getExternalAttributesIndex($index, $opsys, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000) {
                    throw new PluginLifecycleException('制品不允许软链接', 'ARTIFACT_ZIP_UNSAFE_PATH');
                }
                $key = strtolower(rtrim($name, '/'));
                if (isset($seen[$key])) {
                    throw new PluginLifecycleException('制品存在重复路径', 'ARTIFACT_ZIP_UNSAFE_PATH');
                }
                $seen[$key] = true;
                $total += (int)($stat['size'] ?? 0);
                if ($total > 1024 * 1024 * 1024 || (int)($stat['size'] ?? 0) > 64 * 1024 * 1024) {
                    throw new PluginLifecycleException('制品展开大小超限', 'ARTIFACT_ZIP_INVALID');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if ($name === 'plugin.json' || str_starts_with($name, 'backend/') || str_starts_with($name, 'resources/')) {
                    $path = (new PluginInstallManifest())->safeFile($pluginRoot, $name);
                    $contents = $zip->getFromIndex($index);
                    if ($contents === false || !hash_equals(hash('sha256', $contents), (string)hash_file('sha256', $path))) {
                        throw new PluginLifecycleException('签名制品与已部署源码不一致，请先完成受信部署', 'ARTIFACT_DEPLOYMENT_REQUIRED');
                    }
                }
            }
            // 有效清单也不能夹带额外可执行文件，部署目录必须完全属于签名制品。
            foreach (['backend', 'resources'] as $directory) {
                $root = $pluginRoot . '/' . $directory;
                if (!is_dir($root)) {
                    continue;
                }
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($pluginRoot) + 1));
                    if ($file->isLink() || !$file->isFile() || !isset($seen[strtolower($relative)])) {
                        throw new PluginLifecycleException('已部署源码存在制品外文件', 'ARTIFACT_DEPLOYMENT_REQUIRED');
                    }
                }
            }
            $manifest = json_decode((string)$zip->getFromName('plugin.json'), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['code'] ?? '') !== $code || ($manifest['version'] ?? '') !== $version
                || !empty($manifest['overrides'])) {
                throw new PluginLifecycleException('制品清单与任务不一致或包含核心覆盖', 'ARTIFACT_MANIFEST_MISMATCH');
            }
            return $manifest;
        } finally {
            $zip->close();
        }
    }

    /**
     * 验证制品引用为小写 SHA-256 摘要。
     *
     * @param string $hash 待验证的内容摘要。
     * @return void 无返回值；摘要格式非法时抛出异常。
     */
    private function assertHash(string $hash): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new PluginLifecycleException('制品引用无效', 'INVALID_ARTIFACT_REFERENCE');
        }
    }
}
