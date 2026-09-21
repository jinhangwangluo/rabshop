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
use app\common\model\plugin\Plugin;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * 只读发现服务器固定开发目录，并验证实际执行源码的路径、清单与内容身份。
 */
class DeveloperPluginSourceService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const MAX_PLUGINS = 200;
    private const MAX_FILES = 10000;
    private const MAX_FILE_BYTES = 8 * 1024 * 1024;
    private const MAX_SOURCE_BYTES = 128 * 1024 * 1024;
    private const MAX_JSON_BYTES = 1024 * 1024;
    private const PACKAGE_TTL_SECONDS = 3600;
    private const MAX_PACKAGES = 20;

    private readonly string $projectRoot;
    private readonly string $packageRoot;

    /**
     * 解析服务器或测试指定的仓库根目录，并拒绝不安全路径。
     *
     * @param string|null $projectRoot 仅由服务器配置或隔离测试提供的仓库根目录。
     * @param string|null $packageRoot 开发插件压缩包暂存目录；缺省使用仓库 runtime 目录。
     * @return void 无返回值。
     */
    public function __construct(?string $projectRoot = null, ?string $packageRoot = null)
    {
        $root = rtrim($projectRoot ?? root_path(), '/\\');
        $real = realpath($root);
        if ($real === false || !is_dir($real) || is_link($root)) {
            throw new PluginLifecycleException('开发源码根目录无效', 'DEVELOPER_SOURCE_UNAVAILABLE');
        }
        // macOS 的临时目录别名先规范化，围栏以内的每级路径仍禁止软链接。
        $this->projectRoot = $real;
        // 压缩包只落在 runtime 等非 Web 目录，文件名带编码与源码摘要避免互相覆盖。
        $this->packageRoot = rtrim($packageRoot ?? $this->projectRoot . '/runtime/plugin_packages', '/\\');
    }

    /**
     * 指定开发来源服务对应的宿主插件模型。
     *
     * @return string 插件模型完整类名。
     */
    protected function setModel(): string
    {
        return Plugin::class;
    }

    /**
     * 发现固定开发映射，无效源码以不可操作原因展示且不执行插件代码。
     *
     * @return list<array<string,mixed>> 可安全展示的开发插件及验证结果列表。
     */
    public function discover(): array
    {
        $directory = $this->projectRoot . '/plugins';
        if (!file_exists($directory) && !is_link($directory)) {
            return [];
        }
        $this->safePath('plugins', true);
        $items = [];
        $scanned = 0;
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (++$scanned > self::MAX_PLUGINS) {
                throw new PluginLifecycleException('开发插件目录超过扫描上限', 'DEVELOPER_SOURCE_LIMIT');
            }
            if (!$entry->isDir() && !$entry->isLink()) {
                continue;
            }
            $devFile = $entry->getPathname() . '/plugin.dev.json';
            if (!file_exists($devFile) && !is_link($devFile)) {
                continue;
            }
            $items[] = $this->inspect($entry->getFilename());
        }
        usort($items, static fn (array $left, array $right): int => strcmp($left['code'], $right['code']));
        return $items;
    }

    /**
     * 重新验证固定编码的源码，生成独立于市场制品的内容证明。
     *
     * @param string $code 插件目录编码，不接受路径。
     * @return array{manifest:array<string,mixed>,declaration:array<string,mixed>,source_sha256:string,root:string} 清单、安装声明、源码摘要及插件实际根目录。
     */
    public function resolve(string $code): array
    {
        try {
            $this->assertCode($code);
            $manifest = $this->readJson('plugins/' . $code . '/plugin.json');
            return $this->validate($code, $manifest);
        } catch (PluginLifecycleException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginLifecycleException('开发源码无法验证，请检查清单与部署文件', 'DEVELOPER_SOURCE_INVALID', 0, $exception);
        }
    }

    /**
     * 核对开发安装证明与当前源码一致，防止执行被替换的卸载脚本。
     *
     * @param string $code 插件编码。
     * @param array<string,mixed> $installedManifest 服务器保存的已安装清单。
     * @return array{manifest:array<string,mixed>,declaration:array<string,mixed>,source_sha256:string,root:string} 已核对安装身份的源码验证结果。
     */
    public function assertInstalled(string $code, array $installedManifest): array
    {
        $this->assertDeveloperInstallation($installedManifest);
        $proof = (string)$installedManifest['developer_source_sha256'];
        $source = $this->resolve($code);
        if (!hash_equals($proof, $source['source_sha256'])) {
            throw new PluginLifecycleException('开发源码与安装时不一致，须先恢复受信源码再卸载', 'DEVELOPER_SOURCE_CHANGED');
        }
        return $source;
    }

    /**
     * 重新安装用当前受信源码替换已安装源码，只要求记录确为开发来源安装。
     *
     * 与普通卸载的差别仅是跳过源码摘要比对：重装的语义就是让当前源码生效，
     * 因此源码改动由调用方在确认后显式授权，源码本身仍须通过全部清单与入口校验。
     *
     * @param string $code 插件编码。
     * @param array<string,mixed> $installedManifest 服务器保存的已安装清单。
     * @return array{manifest:array<string,mixed>,declaration:array<string,mixed>,source_sha256:string,root:string} 当前源码验证结果。
     */
    public function assertReinstallable(string $code, array $installedManifest): array
    {
        $this->assertDeveloperInstallation($installedManifest);
        return $this->resolve($code);
    }

    /**
     * 打包整个开发插件源码，包内保留仓库相对路径便于直接覆盖还原。
     *
     * @param string $code 插件编码。
     * @return array{path:string,name:string,bytes:int,sha256:string,source_sha256:string,files:int} 压缩包路径、下载文件名、包字节数、包摘要、源码摘要及文件数量。
     */
    public function package(string $code): array
    {
        $source = $this->resolve($code);
        $dev = $this->readJson('plugins/' . $code . '/plugin.dev.json');
        $mapping = is_array($dev['source_roots'] ?? null) ? array_filter($dev['source_roots'], 'is_string') : [];
        $files = $this->collectFiles($code, $mapping);
        $digest = $this->sourceDigest($files);
        // 打包前重新比对摘要，压缩包与安装校验必须基于同一份源码。
        if (!hash_equals($source['source_sha256'], $digest)) {
            throw new PluginLifecycleException('开发源码在校验后发生变化，请重试', 'DEVELOPER_SOURCE_CHANGED');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new PluginLifecycleException('服务器未启用压缩扩展，无法导出开发插件', 'DEVELOPER_PACKAGE_UNAVAILABLE');
        }

        $directory = $this->packageDirectory();
        $this->prunePackages($directory);
        $name = $code . '-' . (string)$source['manifest']['version'] . '-' . substr($digest, 0, 8) . '.zip';
        // 落盘名加随机前缀，避免并发导出同一插件时互相覆盖。
        $path = $directory . '/' . bin2hex(random_bytes(6)) . '-' . $name;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PluginLifecycleException('开发插件压缩包创建失败', 'DEVELOPER_PACKAGE_FAILED');
        }
        try {
            foreach ($files as $relative => $absolute) {
                if ($zip->addFile($absolute, $relative) !== true) {
                    throw new PluginLifecycleException('开发插件源码写入压缩包失败', 'DEVELOPER_PACKAGE_FAILED');
                }
            }
            if ($zip->close() !== true) {
                throw new PluginLifecycleException('开发插件压缩包写入失败', 'DEVELOPER_PACKAGE_FAILED');
            }
        } catch (Throwable $failure) {
            $zip->close();
            if (is_file($path)) {
                @unlink($path);
            }
            throw $failure;
        }

        $bytes = filesize($path);
        $sha256 = hash_file('sha256', $path);
        if ($bytes === false || $sha256 === false) {
            @unlink($path);
            throw new PluginLifecycleException('开发插件压缩包不可读', 'DEVELOPER_PACKAGE_FAILED');
        }
        return [
            'path' => $path, 'name' => $name, 'bytes' => (int)$bytes, 'sha256' => strtolower($sha256),
            'source_sha256' => $digest, 'files' => count($files),
        ];
    }

    /**
     * 提取插件安全展示字段，并保留验证失败原因。
     *
     * @param string $code 发现的插件目录编码。
     * @return array<string,mixed> 开发插件展示信息及服务器验证摘要。
     */
    private function inspect(string $code): array
    {
        $item = [
            'code' => $this->text($code, 64), 'name' => $this->text($code, 100), 'version' => '',
            'description' => '', 'publisher' => '', 'surfaces' => [], 'source' => 'developer',
            'blocked_reason' => '', 'requires_operational_cleanup' => false, 'source_sha256' => '',
        ];
        try {
            $this->assertCode($code);
            $manifest = $this->readJson('plugins/' . $code . '/plugin.json');
            foreach (['name' => 100, 'version' => 32, 'description' => 500, 'publisher' => 100] as $key => $max) {
                $item[$key] = $this->text($manifest[$key] ?? '', $max);
            }
            $item['surfaces'] = array_values(array_intersect(
                ['backend', 'admin', 'merchant', 'mobile'],
                is_array($manifest['surfaces'] ?? null) ? array_filter($manifest['surfaces'], 'is_string') : []
            ));
            $source = $this->validate($code, $manifest);
            $item['source_sha256'] = $source['source_sha256'];
            $item['requires_operational_cleanup'] = (bool)($source['declaration']['requires_operational_cleanup'] ?? false);
        } catch (PluginLifecycleException $exception) {
            $item['blocked_reason'] = $exception->getMessage();
        } catch (Throwable) {
            $item['blocked_reason'] = '开发源码无法验证，请检查清单与部署文件';
        }
        return $item;
    }

    /**
     * 校验两份清单、四端映射和文件边界，再生成确定性的源码摘要。
     *
     * @param string $code 插件编码。
     * @param array<string,mixed> $manifest 从固定开发目录读取的插件清单。
     * @return array{manifest:array<string,mixed>,declaration:array<string,mixed>,source_sha256:string,root:string} 经过校验的清单、声明、摘要及实际根目录。
     */
    private function validate(string $code, array $manifest): array
    {
        if (($manifest['schema_version'] ?? '') !== '1.0' || ($manifest['code'] ?? '') !== $code
            || !is_string($manifest['name'] ?? null) || trim($manifest['name']) === ''
            || !is_string($manifest['version'] ?? null)
            || preg_match('/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,31}$/', $manifest['version']) !== 1) {
            throw new PluginLifecycleException('开发插件清单或目录编码不一致', 'DEVELOPER_MANIFEST_INVALID');
        }
        $surfaces = $manifest['surfaces'] ?? [];
        if (!is_array($surfaces) || !array_is_list($surfaces) || !in_array('backend', $surfaces, true)
            || array_filter($surfaces, 'is_string') !== $surfaces
            || array_diff($surfaces, ['backend', 'admin', 'merchant', 'mobile']) !== []
            || count(array_unique($surfaces)) !== count($surfaces) || !empty($manifest['overrides'])) {
            throw new PluginLifecycleException('开发插件端声明无效或包含宿主覆盖', 'DEVELOPER_MANIFEST_INVALID');
        }
        $root = $this->safePath('plugins/' . $code, true);
        if (!is_file($root . '/resources/lifecycle.json')) {
            throw new PluginLifecycleException('缺少安装声明 resources/lifecycle.json', 'DEVELOPER_LIFECYCLE_REQUIRED');
        }
        $lifecycle = $this->readJson('plugins/' . $code . '/resources/lifecycle.json');
        foreach (['migrations', 'mobile_pages', 'diy_components', 'config_keys'] as $key) {
            if (isset($lifecycle[$key]) && (!is_array($lifecycle[$key]) || !array_is_list($lifecycle[$key]))) {
                throw new PluginLifecycleException('开发插件安装资源必须为列表', 'DEVELOPER_RESOURCE_INVALID');
            }
        }
        $declaration = (new PluginInstallManifest())->read($root, $code);
        $dev = $this->readJson('plugins/' . $code . '/plugin.dev.json');
        if (($dev['schema_version'] ?? '') !== '1.0' || ($dev['plugin_code'] ?? '') !== $code
            || !is_array($dev['source_roots'] ?? null)) {
            throw new PluginLifecycleException('开发映射清单与插件编码不一致', 'DEVELOPER_MAPPING_INVALID');
        }
        $expected = ['resources' => 'plugins/' . $code . '/resources', 'tests' => 'plugins/' . $code . '/tests', 'backend' => 'plugins/' . $code . '/backend'];
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (in_array($surface, $surfaces, true)) {
                $expected[$surface] = $surface === 'mobile' ? 'template/uniapp/src/plugins/' . $code : 'template/' . $surface . '/src/plugins/' . $code;
            }
        }
        $mapping = $dev['source_roots'];
        ksort($mapping);
        ksort($expected);
        if ($mapping !== $expected) {
            throw new PluginLifecycleException('开发源码必须使用声明端的标准映射路径', 'DEVELOPER_MAPPING_INVALID');
        }
        $this->assertEntrypoints($code, $manifest, $mapping, $declaration);
        $files = $this->collectFiles($code, $mapping);
        return ['manifest' => $manifest, 'declaration' => $declaration, 'source_sha256' => $this->sourceDigest($files), 'root' => $root];
    }

    /**
     * 收集清单与标准映射内的实际源码文件，保持路径排序确定性。
     *
     * @param string $code 插件编码。
     * @param array<string,string> $mapping 固定开发端源码映射。
     * @return array<string,string> 按仓库相对路径排序的文件映射。
     */
    private function collectFiles(string $code, array $mapping): array
    {
        $files = [
            'plugins/' . $code . '/plugin.json' => $this->safePath('plugins/' . $code . '/plugin.json'),
            'plugins/' . $code . '/plugin.dev.json' => $this->safePath('plugins/' . $code . '/plugin.dev.json'),
        ];
        $entries = 0;
        foreach ($mapping as $relative) {
            $directory = $this->safePath($relative, true);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            $foundFile = false;
            foreach ($iterator as $entry) {
                if (++$entries > self::MAX_FILES || $iterator->getDepth() > 64) {
                    throw new PluginLifecycleException('开发源码目录超过扫描上限', 'DEVELOPER_SOURCE_LIMIT');
                }
                if ($entry->isLink()) {
                    throw new PluginLifecycleException('开发源码树不允许软链接', 'DEVELOPER_PATH_UNSAFE');
                }
                if (!$entry->isFile()) {
                    if (!$entry->isDir()) {
                        throw new PluginLifecycleException('开发源码不允许特殊文件', 'DEVELOPER_PATH_UNSAFE');
                    }
                    continue;
                }
                $foundFile = true;
                $absolute = $entry->getPathname();
                $key = substr($absolute, strlen($this->projectRoot) + 1);
                $files[$key] = $this->safePath($key);
                if (count($files) > self::MAX_FILES) {
                    throw new PluginLifecycleException('开发源码文件超过数量上限', 'DEVELOPER_SOURCE_LIMIT');
                }
            }
            if (!$foundFile) {
                throw new PluginLifecycleException('开发源码映射目录不能为空', 'DEVELOPER_MAPPING_INVALID');
            }
        }
        ksort($files);
        return $files;
    }

    /**
     * 按路径顺序校验源码大小并生成确定性摘要。
     *
     * @param array<string,string> $files 已收集的仓库相对路径文件映射。
     * @return string 开发源码内容摘要。
     */
    private function sourceDigest(array $files): string
    {
        $hash = hash_init('sha256');
        $total = 0;
        foreach ($files as $relative => $absolute) {
            $size = filesize($absolute);
            if ($size === false || $size > self::MAX_FILE_BYTES || ($total += $size) > self::MAX_SOURCE_BYTES) {
                throw new PluginLifecycleException('开发源码大小超过限制', 'DEVELOPER_SOURCE_LIMIT');
            }
            $digest = hash_file('sha256', $absolute);
            if ($digest === false) {
                throw new PluginLifecycleException('开发源码文件无法读取', 'DEVELOPER_SOURCE_INVALID');
            }
            hash_update($hash, $relative . "\0" . $size . "\0" . $digest . "\n");
        }
        return hash_final($hash);
    }

    /**
     * 核对已安装记录确为开发来源安装，且未混入市场制品引用。
     *
     * @param array<string,mixed> $installedManifest 服务器保存的已安装清单。
     * @return void 无返回值；不是开发来源安装时抛出异常。
     */
    private function assertDeveloperInstallation(array $installedManifest): void
    {
        $proof = $installedManifest['developer_source_sha256'] ?? '';
        if (($installedManifest['installation_source'] ?? '') !== 'developer' || !is_string($proof)
            || preg_match('/^[a-f0-9]{64}$/', $proof) !== 1 || !empty($installedManifest['artifact_sha256'])) {
            throw new PluginLifecycleException('插件不是已验证的开发来源安装', 'DEVELOPER_INSTALLATION_REQUIRED');
        }
    }

    /**
     * 确保压缩包暂存目录存在且可写，避免导出文件落入 Web 可访问目录。
     *
     * @return string 可写的压缩包暂存目录实际路径。
     */
    private function packageDirectory(): string
    {
        if (!is_dir($this->packageRoot) && !@mkdir($this->packageRoot, 0700, true) && !is_dir($this->packageRoot)) {
            throw new PluginLifecycleException('开发插件导出目录创建失败', 'DEVELOPER_PACKAGE_UNAVAILABLE');
        }
        if (!is_writable($this->packageRoot)) {
            throw new PluginLifecycleException('开发插件导出目录不可写', 'DEVELOPER_PACKAGE_UNAVAILABLE');
        }
        return realpath($this->packageRoot) ?: $this->packageRoot;
    }

    /**
     * 清理过期或超量的历史压缩包，避免暂存目录无限增长。
     *
     * @param string $directory 已完成存在性校验的压缩包暂存目录。
     * @return void 无返回值；清理失败不影响本次导出。
     */
    private function prunePackages(string $directory): void
    {
        $packages = [];
        foreach (glob($directory . '/*.zip') ?: [] as $file) {
            if (is_file($file)) {
                $packages[$file] = (int)(filemtime($file) ?: 0);
            }
        }
        arsort($packages);
        $expire = time() - self::PACKAGE_TTL_SECONDS;
        $kept = 0;
        foreach ($packages as $file => $modified) {
            if ($kept < self::MAX_PACKAGES && $modified >= $expire) {
                $kept++;
                continue;
            }
            @unlink($file);
        }
    }

    /**
     * 核对标准打包入口与固定开发映射，仅检查声明和文件而不执行插件代码。
     *
     * @param string $code 插件编码。
     * @param array<string,mixed> $manifest 插件清单。
     * @param array<string,string> $mapping 固定开发端源码映射。
     * @param array<string,mixed> $declaration 插件安装资源声明。
     * @return void 无返回值；入口不一致时抛出异常。
     */
    private function assertEntrypoints(string $code, array $manifest, array $mapping, array $declaration): void
    {
        $namespace = $manifest['namespace'] ?? '';
        $entrypoints = $manifest['entrypoints'] ?? [];
        $provider = is_array($entrypoints) ? ($entrypoints['provider'] ?? '') : '';
        $studly = implode('', array_map('ucfirst', explode('_', $code)));
        if ($namespace !== 'Plugin\\' . $studly || $provider !== $namespace . '\\' . $studly . 'Plugin') {
            throw new PluginLifecycleException('开发插件 Provider 声明无效', 'DEVELOPER_ENTRYPOINT_INVALID');
        }
        $providerFile = $this->safePath($mapping['backend'] . '/src/' . $studly . 'Plugin.php');
        $this->assertProviderDeclaration($providerFile, $provider);
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (!isset($mapping[$surface])) {
                continue;
            }
            if (($entrypoints[$surface . '_routes'] ?? '') !== 'backend/routes/' . $surface . '.php'
                || ($entrypoints[$surface . '_frontend'] ?? '') !== 'frontend/' . $surface . '/index.js') {
                throw new PluginLifecycleException('开发插件端入口必须使用固定路径', 'DEVELOPER_ENTRYPOINT_INVALID');
            }
            $this->safePath($mapping['backend'] . '/routes/' . $surface . '.php');
            $this->safePath($mapping[$surface] . '/index.js');
        }
        foreach ($declaration['mobile_pages'] ?? [] as $page) {
            if (!isset($mapping['mobile'])) {
                throw new PluginLifecycleException('移动页面缺少开发端映射', 'DEVELOPER_ENTRYPOINT_INVALID');
            }
            $this->safePath('template/uniapp/src/' . $page['path'] . '.vue');
        }
        foreach (['menus', 'permissions'] as $resource) {
            $data = $this->readJson('plugins/' . $code . '/resources/' . $resource . '.json');
            if (($data['plugin_code'] ?? '') !== $code || !is_array($data['items'] ?? null)) {
                throw new PluginLifecycleException('开发插件菜单权限声明无效', 'DEVELOPER_RESOURCE_INVALID');
            }
            foreach ($data['items'] as $item) {
                $surface = is_array($item) ? ($item['surface'] ?? '') : '';
                $permission = is_array($item) ? ($item['permission'] ?? '') : '';
                if (!in_array($surface, ['admin', 'merchant'], true) || !isset($mapping[$surface])
                    || !is_string($permission) || !str_starts_with($permission, 'plugin:' . $code . ':') || strlen($permission) > 50) {
                    throw new PluginLifecycleException('开发插件菜单权限归属越界', 'DEVELOPER_RESOURCE_INVALID');
                }
                if ($resource === 'menus') {
                    $route = $item['route'] ?? '';
                    if (!is_string($route) || !str_starts_with($route, '/' . $code . '/') || strlen($route) > 100
                        || !is_string($item['title'] ?? null) || trim($item['title']) === ''
                        || !is_int($item['parent_id'] ?? 0) || ($item['parent_id'] ?? 0) < 0) {
                        throw new PluginLifecycleException('开发插件菜单路径或标题无效', 'DEVELOPER_RESOURCE_INVALID');
                    }
                } else {
                    $path = $item['path'] ?? '';
                    $method = $item['method'] ?? '';
                    if (!is_string($path) || !str_starts_with(ltrim($path, '/'), 'api/' . $surface . '/plugins/' . $code . '/')
                        || strlen($path) > 100 || !is_string($method) || !in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                        throw new PluginLifecycleException('开发插件权限接口路径或方法无效', 'DEVELOPER_RESOURCE_INVALID');
                    }
                }
            }
        }
    }

    /**
     * 仅解析 Provider 声明，防止命名空间不一致或撞用宿主类。
     *
     * @param string $file 已验证路径边界内的 Provider 文件。
     * @param string $provider 清单声明的固定完整类名。
     * @return void 无返回值；未找到匹配声明时抛出异常。
     */
    private function assertProviderDeclaration(string $file, string $provider): void
    {
        $size = filesize($file);
        if ($size === false || $size > self::MAX_FILE_BYTES) {
            throw new PluginLifecycleException('开发 Provider 文件超过大小限制', 'DEVELOPER_SOURCE_LIMIT');
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw new PluginLifecycleException('开发 Provider 文件无法读取', 'DEVELOPER_ENTRYPOINT_INVALID');
        }
        $tokens = token_get_all($content, TOKEN_PARSE);
        $namespace = '';
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($index++; $index < $count; $index++) {
                    $part = $tokens[$index];
                    if ($part === ';' || $part === '{') {
                        break;
                    }
                    if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespace .= $part[1];
                    }
                }
            } elseif ($token[0] === T_CLASS) {
                for ($next = $index + 1; $next < $count; $next++) {
                    $name = $tokens[$next];
                    if (is_array($name) && in_array($name[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    if (is_array($name) && $name[0] === T_STRING && $namespace . '\\' . $name[1] === $provider) {
                        return;
                    }
                    break;
                }
            }
        }
        throw new PluginLifecycleException('开发 Provider 文件声明与清单不一致', 'DEVELOPER_ENTRYPOINT_INVALID');
    }

    /**
     * 读取大小受限的 JSON 对象清单，不接受列表或非法内容。
     *
     * @param string $relative 服务器生成的仓库相对文件路径。
     * @return array<string,mixed> 解析后的 JSON 对象字段。
     */
    private function readJson(string $relative): array
    {
        $file = $this->safePath($relative);
        $size = filesize($file);
        if ($size === false || $size > self::MAX_JSON_BYTES) {
            throw new PluginLifecycleException('开发清单超过大小限制', 'DEVELOPER_SOURCE_LIMIT');
        }
        $content = file_get_contents($file);
        $data = $content === false ? null : json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new PluginLifecycleException('开发清单必须为 JSON 对象', 'DEVELOPER_MANIFEST_INVALID');
        }
        return $data;
    }

    /**
     * 验证路径各级无软链接，并限制实际文件在仓库边界内。
     *
     * @param string $relative 服务器生成的仓库相对路径。
     * @param bool $directory 是否要求目标为目录。
     * @return string 边界内可读文件或目录的实际路径。
     */
    private function safePath(string $relative, bool $directory = false): string
    {
        if ($relative === '' || str_contains($relative, '\\') || str_starts_with($relative, '/')
            || preg_match('#(?:^|/)\.\.?(?:/|$)|^[A-Za-z]:|[\x00-\x1F\x7F]#', $relative)) {
            throw new PluginLifecycleException('开发源码路径不安全', 'DEVELOPER_PATH_UNSAFE');
        }
        $candidate = $this->projectRoot;
        foreach (explode('/', $relative) as $segment) {
            $candidate .= '/' . $segment;
            if ($segment === '' || is_link($candidate)) {
                throw new PluginLifecycleException('开发源码路径不允许软链接或空层级', 'DEVELOPER_PATH_UNSAFE');
            }
        }
        $real = realpath($candidate);
        if ($real === false || !str_starts_with($real, $this->projectRoot . DIRECTORY_SEPARATOR)
            || !is_readable($real) || ($directory ? !is_dir($real) : !is_file($real))) {
            throw new PluginLifecycleException('开发源码文件未部署或路径越界', 'DEVELOPER_PATH_UNSAFE');
        }
        return $real;
    }

    /**
     * 验证开发插件目录编码的安全格式。
     *
     * @param string $code 待验证的插件编码。
     * @return void 无返回值；格式非法时抛出异常。
     */
    private function assertCode(string $code): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $code) !== 1) {
            throw new PluginLifecycleException('开发插件目录编码无效', 'INVALID_PLUGIN_CODE');
        }
    }

    /**
     * 裁剪安全展示文本并移除控制字符。
     *
     * @param mixed $value 待展示的原始字段值。
     * @param int $max 允许的最大字符数。
     * @return string 清理后的文本；非字符串返回空字符串。
     */
    private function text(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]/', '', $value)), 0, $max) : '';
    }
}
