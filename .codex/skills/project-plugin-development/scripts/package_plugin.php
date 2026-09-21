#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 归集并校验未签名的插件源码包；不生成正式安装制品，也不执行发布或安装。
 */
require_once __DIR__ . '/lib/plugin_tool.php';

$stageRoot = null;

try {
    $options = PluginTool::parseArgs($argv);
    $allowed = ['root', 'code', 'mode', 'output', 'dry_run', 'json'];
    foreach (array_keys($options) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new PluginToolException('未知参数 --' . $key);
        }
    }
    $root = PluginTool::rootFromOptions($options);
    $code = PluginTool::validateCode(PluginTool::requireOption($options, 'code'));
    $mode = strtolower((string) PluginTool::option($options, 'mode', 'source'));
    if ($mode !== 'source') {
        throw new PluginToolException('当前仅支持 --mode=source');
    }
    $pluginPath = PluginTool::resolveRelative($root, 'plugins/' . $code, true);
    $validation = PluginValidator::development($root, $pluginPath);
    $plan = buildPackagePlan($validation);
    $dryRun = optionIsTrue($options, 'dry_run');
    if ($dryRun) {
        echo PluginTool::jsonEncode([
            'ok' => true,
            'dry_run' => true,
            'mode' => $mode,
            'plugin_code' => $validation['manifest']['code'],
            'version' => $validation['manifest']['version'],
            'included' => array_keys($plan['entries']),
            'excluded' => $plan['excluded'],
            'file_count' => count($plan['entries']),
            'total_bytes' => $plan['total_bytes'],
            'warnings' => $validation['warnings'],
        ]);
        exit(0);
    }

    $output = PluginTool::option($options, 'output');
    if (!is_string($output) || trim($output) === '') {
        $output = 'runtime/plugin_packages/' . $code . '/' . $code . '-' . $validation['manifest']['version'] . '-source.zip';
    }
    $outputPath = resolveOutputPath($root, $output);
    if (file_exists($outputPath) || is_link($outputPath)) {
        throw new PluginToolException('输出文件已存在，拒绝覆盖：' . $outputPath);
    }
    $outputParent = dirname($outputPath);
    if (!is_dir($outputParent) && !mkdir($outputParent, 0775, true) && !is_dir($outputParent)) {
        throw new PluginToolException('无法创建输出目录：' . $outputParent);
    }
    PluginTool::assertNoSymlinkPath($outputParent, $root);

    $stageRoot = $root . DIRECTORY_SEPARATOR . '.plugin-package-stage-' . bin2hex(random_bytes(8));
    if (!mkdir($stageRoot, 0700, true)) {
        throw new PluginToolException('无法创建打包暂存目录');
    }
    $packageRoot = $stageRoot . DIRECTORY_SEPARATOR . 'source';
    materializePackage($packageRoot, $plan);
    $temporaryZip = $outputPath . '.tmp-' . bin2hex(random_bytes(8));
    createDeterministicZip($packageRoot, $temporaryZip);
    // 将新生成的 ZIP 再按不可信输入校验并隔离解包，通过后才写入请求的目标位置。
    PluginValidator::packageArchive($temporaryZip);
    if (!rename($temporaryZip, $outputPath)) {
        @unlink($temporaryZip);
        throw new PluginToolException('无法原子写入源码包：' . $outputPath);
    }
    $packageHash = hash_file('sha256', $outputPath);
    $result = [
        'ok' => true,
        'dry_run' => false,
        'mode' => $mode,
        'plugin_code' => $validation['manifest']['code'],
        'version' => $validation['manifest']['version'],
        'output' => PluginTool::relativePath($outputPath, $root),
        'file_count' => count($plan['entries']),
        'total_bytes' => $plan['total_bytes'],
        'package_bytes' => filesize($outputPath),
        'package_sha256' => $packageHash,
        'included' => array_keys($plan['entries']),
        'excluded' => $plan['excluded'],
        'warnings' => $validation['warnings'],
        'signed' => false,
    ];
    echo PluginTool::jsonEncode($result);
    PluginTool::removeTree($stageRoot, $root);
    $stageRoot = null;
    exit(0);
} catch (Throwable $exception) {
    if (is_string($stageRoot) && is_dir($stageRoot)) {
        try {
            PluginTool::removeTree($stageRoot, dirname($stageRoot));
        } catch (Throwable) {
            // 清理失败不能掩盖原始错误；暂存目录仅位于本仓库内，不含用户原始源码。
        }
    }
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * 将布尔选项和工具允许的文本真值统一为布尔值。
 *
 * @param array $options 经长选项解析后的参数映射。
 * @param string $name 待解释为布尔值的选项名。
 * @return bool 选项是否显式指定为允许的真值。
 */
function optionIsTrue(array $options, string $name): bool
{
    $value = PluginTool::option($options, $name, false);
    if ($value === true) {
        return true;
    }
    if (is_string($value)) {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
    return false;
}

/**
 * 解析仓库内输出路径，拒绝越界目标和经过软链接的父目录。
 *
 * @param string $root 已经确认的仓库或操作根目录。
 * @param string $configured 用户配置的相对或绝对输出路径。
 * @return string 仓库根目录内且父目录未经过软链接的输出文件路径。
 */
function resolveOutputPath(string $root, string $configured): string
{
    $configured = trim($configured);
    if ($configured === '') {
        throw new PluginToolException('输出路径不能为空');
    }
    if (preg_match('~^[A-Za-z]:[\\\\/]~', $configured) || str_starts_with($configured, DIRECTORY_SEPARATOR)) {
        $candidate = $configured;
        if (!PluginTool::isWithin($candidate, $root)) {
            throw new PluginToolException('输出路径必须位于仓库根目录内');
        }
        PluginTool::assertNoSymlinkPath(dirname($candidate), $root);
        return $candidate;
    }
    $relative = PluginTool::normalizeRelative($configured);
    $candidate = PluginTool::resolveRelative($root, $relative, false);
    PluginTool::assertNoSymlinkPath(dirname($candidate), $root);
    return $candidate;
}

/**
 * 归集非敏感源码并生成稳定排序的文件摘要清单，只准备未签名源码包。
 *
 * @param array{manifest:array<string,mixed>,dev:array<string,mixed>,files:array<int,array{source_root:string,package_root:string,absolute:string,relative:string,source_relative:string,size:int}>,warnings:array<int,string>} $validation 开发源码校验通过后的清单、文件和警告集合。
 * @return array{entries:array<string,array{source:string|null,contents:string|null,size:int}>,excluded:array<int,string>,total_bytes:int} 稳定排序的包内条目、被排除文件和含清单的总字节数。
 */
function buildPackagePlan(array $validation): array
{
    $manifest = $validation['manifest'];
    $entries = [
        'plugin.json' => [
            'source' => null,
            'contents' => PluginTool::jsonEncode($manifest),
            'size' => 0,
        ],
    ];
    $excluded = [];
    foreach ($validation['files'] as $file) {
        $inside = $file['source_relative'];
        if (PluginValidator::isExcludedPath($inside)) {
            $excluded[] = $file['relative'];
            continue;
        }
        $packagePath = PluginTool::PACKAGE_ROOTS[$file['source_root']] . '/' . $inside;
        try {
            $packagePath = PluginTool::normalizeRelative($packagePath);
        } catch (PluginToolException $exception) {
            throw new PluginToolException('无法生成包路径：' . $file['relative'], 0, $exception);
        }
        $lower = strtolower($packagePath);
        foreach (array_keys($entries) as $existing) {
            if (strtolower($existing) === $lower) {
                throw new PluginToolException('打包路径重复或大小写冲突：' . $packagePath);
            }
        }
        $entries[$packagePath] = ['source' => $file['absolute'], 'contents' => null, 'size' => $file['size']];
    }
    if (!isset($entries['resources/initial-state.json'])) {
        throw new PluginToolException('归集后缺少 resources/initial-state.json');
    }
    $hasTests = false;
    foreach (array_keys($entries) as $path) {
        if (str_starts_with($path, 'tests/')) {
            $hasTests = true;
            break;
        }
    }
    if (!$hasTests) {
        throw new PluginToolException('归集后必须保留至少一个 tests 文件');
    }
    // 固定文件与摘要顺序，使相同源码在同一打包工具链下生成稳定内容。
    ksort($entries, SORT_STRING);
    $checksums = [];
    $total = 0;
    foreach ($entries as $path => &$entry) {
        if ($path === 'plugin.json') {
            $entry['size'] = strlen((string) $entry['contents']);
            $total += $entry['size'];
            $checksums[$path] = hash('sha256', (string) $entry['contents']);
            continue;
        }
        $total += $entry['size'];
        $checksums[$path] = hash_file('sha256', (string) $entry['source']);
    }
    unset($entry);
    ksort($checksums, SORT_STRING);
    $checksumContents = PluginTool::jsonEncode([
        'schema_version' => PluginTool::SCHEMA_VERSION,
        'plugin_code' => $manifest['code'],
        'plugin_version' => $manifest['version'],
        'mode' => 'source',
        'files' => $checksums,
    ]);
    $entries['checksums.json'] = ['source' => null, 'contents' => $checksumContents, 'size' => strlen($checksumContents)];
    ksort($entries, SORT_STRING);
    return ['entries' => $entries, 'excluded' => array_values($excluded), 'total_bytes' => $total + strlen($checksumContents)];
}

/**
 * 将归集计划中的源码和生成清单写入独立暂存目录，不修改原始源码。
 *
 * @param string $packageRoot 待生成或待校验的独立源码包目录。
 * @param array{entries:array<string,array{source:string|null,contents:string|null,size:int}>} $plan 已按包内路径归集的源码和生成清单写入计划。
 * @return void 无返回值。
 */
function materializePackage(string $packageRoot, array $plan): void
{
    if (!mkdir($packageRoot, 0700, true)) {
        throw new PluginToolException('无法创建源码包暂存目录');
    }
    foreach ($plan['entries'] as $relative => $entry) {
        $destination = $packageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $parent = dirname($destination);
        if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new PluginToolException('无法创建源码包目录：' . $parent);
        }
        if ($entry['source'] !== null) {
            if (!copy($entry['source'], $destination)) {
                throw new PluginToolException('无法复制源码：' . $relative);
            }
        } else {
            if (file_put_contents($destination, (string) $entry['contents'], LOCK_EX) === false) {
                throw new PluginToolException('无法写入源码包元文件：' . $relative);
            }
        }
        chmod($destination, 0644);
    }
}

/**
 * 以稳定条目顺序、固定时间戳和权限生成 ZIP，失败时清理本次临时文件。
 *
 * @param string $packageRoot 待生成或待校验的独立源码包目录。
 * @param string $zipPath 本次创建的临时 ZIP 文件路径。
 * @return void 无返回值。
 */
function createDeterministicZip(string $packageRoot, string $zipPath): void
{
    $files = PluginTool::listFiles($packageRoot, $packageRoot);
    $directories = [];
    foreach ($files as $file) {
        $parts = explode('/', dirname($file['relative']));
        $current = '';
        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            $current = $current === '' ? $part : $current . '/' . $part;
            $directories[$current] = true;
        }
    }
    $directoryNames = array_keys($directories);
    sort($directoryNames, SORT_STRING);
    $zip = new ZipArchive();
    $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        throw new PluginToolException('无法创建 ZIP（错误码 ' . (string) $opened . '）');
    }
    try {
        // 固定时间戳及权限，避免机器的文件时间和权限差异进入制品。
        foreach ($directoryNames as $directory) {
            $zip->addEmptyDir($directory . '/');
            $zip->setMtimeName($directory . '/', PluginTool::FIXED_ZIP_TIME);
            if (method_exists($zip, 'setExternalAttributesName')) {
                $zip->setExternalAttributesName($directory . '/', ZipArchive::OPSYS_UNIX, 040755 << 16);
            }
        }
        foreach ($files as $file) {
            $relative = $file['relative'];
            if (!$zip->addFile($file['absolute'], $relative)) {
                throw new PluginToolException('无法添加 ZIP 条目：' . $relative);
            }
            $zip->setMtimeName($relative, PluginTool::FIXED_ZIP_TIME);
            if (method_exists($zip, 'setCompressionName')) {
                $zip->setCompressionName($relative, ZipArchive::CM_DEFLATE, 9);
            }
            if (method_exists($zip, 'setExternalAttributesName')) {
                $zip->setExternalAttributesName($relative, ZipArchive::OPSYS_UNIX, 0100644 << 16);
            }
        }
        $zip->setArchiveComment('');
        if (!$zip->close()) {
            throw new PluginToolException('无法完成 ZIP 写入');
        }
    } catch (Throwable $exception) {
        $zip->close();
        @unlink($zipPath);
        throw $exception;
    }
}
