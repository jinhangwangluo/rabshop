#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/plugin_tool.php';

try {
    $options = PluginTool::parseArgs($argv);
    $allowed = ['root', 'code', 'package', 'json'];
    foreach (array_keys($options) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new PluginToolException('未知参数 --' . $key);
        }
    }

    $package = PluginTool::option($options, 'package');
    if (is_string($package) && trim($package) !== '') {
        $packagePath = $package;
        if (!str_starts_with($packagePath, DIRECTORY_SEPARATOR) && !preg_match('~^[A-Za-z]:[\\\\/]~', $packagePath)) {
            $packagePath = getcwd() . DIRECTORY_SEPARATOR . $packagePath;
        }
        $packagePath = realpath($packagePath);
        if ($packagePath === false) {
            throw new PluginToolException('源码包不存在：' . (string) $package);
        }
        $result = PluginValidator::packageArchive($packagePath);
        $mode = 'package';
    } else {
        $root = PluginTool::rootFromOptions($options);
        $code = PluginTool::validateCode(PluginTool::requireOption($options, 'code'));
        $pluginPath = PluginTool::resolveRelative($root, 'plugins/' . $code, true);
        $result = PluginValidator::development($root, $pluginPath);
        $mode = 'development';
    }

    $files = $result['files'];
    $summary = [
        'ok' => true,
        'mode' => $mode,
        'plugin_code' => $result['manifest']['code'],
        'version' => $result['manifest']['version'],
        'surfaces' => $result['manifest']['surfaces'],
        'file_count' => count($files),
        'warnings' => $result['warnings'],
    ];
    echo PluginTool::jsonEncode($summary);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
