#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/plugin_tool.php';

$stageRoot = null;

try {
    $options = PluginTool::parseArgs($argv);
    $allowed = ['name', 'code', 'surfaces', 'surface', 'description', 'version', 'publisher', 'core_constraint', 'root', 'json'];
    foreach (array_keys($options) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new PluginToolException('未知参数 --' . $key);
        }
    }

    $root = PluginTool::rootFromOptions($options);
    $name = PluginTool::validateName(PluginTool::requireOption($options, 'name'));
    $code = PluginTool::validateCode(PluginTool::requireOption($options, 'code'));
    $version = PluginTool::validateVersion((string) PluginTool::option($options, 'version', '0.1.0'));
    $publisher = (string) PluginTool::option($options, 'publisher', 'local');
    if ($publisher === '' || strlen($publisher) > 100 || preg_match('/[\x00-\x1F\x7F]/', $publisher)) {
        throw new PluginToolException('--publisher 必须是 1-100 个不含控制字符的字符');
    }
    $description = (string) PluginTool::option($options, 'description', '插件开发骨架');
    if (strlen($description) > 500 || preg_match('/[\x00-\x1F\x7F]/', $description)) {
        throw new PluginToolException('--description 不能超过 500 个字符或包含控制字符');
    }
    $coreConstraint = (string) PluginTool::option($options, 'core_constraint', '>=1.0.0 <2.0.0');
    if ($coreConstraint === '' || strlen($coreConstraint) > 100 || preg_match('/[\x00-\x1F\x7F]/', $coreConstraint)) {
        throw new PluginToolException('--core-constraint 不合法');
    }

    $surfaces = parseSurfaces($options);
    if (!in_array('backend', $surfaces, true) && count($surfaces) > 0) {
        throw new PluginToolException('声明平台端、商户端或移动端时必须同时声明 backend');
    }

    $pluginRelative = 'plugins/' . $code;
    $targets = [PluginTool::resolveRelative($root, $pluginRelative, false)];
    foreach (['admin', 'merchant', 'mobile'] as $surface) {
        if (in_array($surface, $surfaces, true)) {
            $relative = match ($surface) {
                'admin' => 'template/admin/src/plugins/' . $code,
                'merchant' => 'template/merchant/src/plugins/' . $code,
                'mobile' => 'template/uniapp/src/plugins/' . $code,
            };
            $targets[] = PluginTool::resolveRelative($root, $relative, false);
        }
    }
    foreach ($targets as $target) {
        if (file_exists($target) || is_link($target)) {
            throw new PluginToolException('初始化目标已存在，拒绝覆盖或合并：' . $target);
        }
    }

    $stageRoot = $root . DIRECTORY_SEPARATOR . '.plugin-init-stage-' . bin2hex(random_bytes(8));
    if (!mkdir($stageRoot, 0700, true)) {
        throw new PluginToolException('无法创建初始化暂存目录');
    }
    buildScaffold($stageRoot, $code, $name, $description, $publisher, $version, $coreConstraint, $surfaces);

    $stagePlugin = $stageRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $code;
    $validation = PluginValidator::development($stageRoot, $stagePlugin);

    // Re-check targets immediately before moving staged directories. This is
    // the all-or-nothing boundary for concurrent changes in the worktree.
    foreach ($targets as $target) {
        if (file_exists($target) || is_link($target)) {
            throw new PluginToolException('初始化目标在提交前已被创建，操作已取消：' . $target);
        }
        PluginTool::assertNoSymlinkPath(dirname($target), $root);
    }
    $moves = [
        $stagePlugin => PluginTool::resolveRelative($root, $pluginRelative, false),
    ];
    foreach (['admin', 'merchant', 'mobile'] as $surface) {
        if (!in_array($surface, $surfaces, true)) {
            continue;
        }
        $stageRelative = match ($surface) {
            'admin' => 'template/admin/src/plugins/' . $code,
            'merchant' => 'template/merchant/src/plugins/' . $code,
            'mobile' => 'template/uniapp/src/plugins/' . $code,
        };
        $moves[$stageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stageRelative)] = PluginTool::resolveRelative($root, $stageRelative, false);
    }
    $moved = [];
    try {
        foreach ($moves as $source => $destination) {
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new PluginToolException('无法创建初始化目标父目录：' . $parent);
            }
            if (!rename($source, $destination)) {
                throw new PluginToolException('无法提交初始化目录：' . $destination);
            }
            $moved[] = $destination;
        }
    } catch (Throwable $exception) {
        foreach (array_reverse($moved) as $destination) {
            if (file_exists($destination) || is_link($destination)) {
                PluginTool::removeTree($destination, $root);
            }
        }
        throw $exception;
    }
    PluginTool::removeTree($stageRoot, $root);
    $stageRoot = null;

    $result = [
        'ok' => true,
        'plugin_code' => $code,
        'version' => $version,
        'surfaces' => $surfaces,
        'generated' => array_values(array_map(static fn (string $path): string => PluginTool::relativePath($path, $root), $targets)),
        'validation_warnings' => $validation['warnings'],
        'lifecycle' => 'uninstalled',
        'enabled' => false,
    ];
    echo PluginTool::jsonEncode($result);
    exit(0);
} catch (Throwable $exception) {
    if (is_string($stageRoot) && is_dir($stageRoot)) {
        try {
            PluginTool::removeTree($stageRoot, dirname($stageRoot));
        } catch (Throwable) {
            // Preserve the original diagnostic; the temporary directory is
            // confined to the repository and can be removed manually.
        }
    }
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

/** @return array<int,string> */
function parseSurfaces(array $options): array
{
    $raw = [];
    foreach (['surfaces', 'surface'] as $key) {
        if (!array_key_exists($key, $options)) {
            continue;
        }
        $value = $options[$key];
        $values = is_array($value) ? $value : [$value];
        foreach ($values as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new PluginToolException('--' . $key . ' 必须是逗号分隔的端名称');
            }
            foreach (explode(',', $item) as $surface) {
                $surface = strtolower(trim($surface));
                if ($surface !== '') {
                    $raw[] = $surface;
                }
            }
        }
    }
    if ($raw === []) {
        return PluginTool::SURFACES;
    }
    $result = [];
    foreach (PluginTool::SURFACES as $surface) {
        if (in_array($surface, $raw, true)) {
            $result[] = $surface;
        }
    }
    foreach ($raw as $surface) {
        if (!in_array($surface, PluginTool::SURFACES, true)) {
            throw new PluginToolException('不支持的开发端：' . $surface);
        }
        if (count(array_keys($raw, $surface, true)) > 1) {
            throw new PluginToolException('开发端不能重复：' . $surface);
        }
    }
    return $result;
}

function buildScaffold(
    string $stageRoot,
    string $code,
    string $name,
    string $description,
    string $publisher,
    string $version,
    string $coreConstraint,
    array $surfaces
): void {
    $studly = PluginTool::studly($code);
    $namespace = PluginTool::namespaceFor($code);
    $pluginRoot = $stageRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $code;
    $templateRoot = PluginTool::templateRoot();
    $replacements = [
        '__CODE__' => $code,
        '__STUDLY__' => $studly,
        '__NAMESPACE__' => $namespace,
        '__NAME__' => addcslashes($name, "\\'\r\n"),
    ];

    $entrypoints = [];
    if (in_array('backend', $surfaces, true)) {
        $entrypoints['provider'] = $namespace . '\\' . $studly . 'Plugin';
    }
    foreach (['admin', 'merchant', 'mobile'] as $surface) {
        if (in_array($surface, $surfaces, true)) {
            $entrypoints[$surface . '_routes'] = 'backend/routes/' . $surface . '.php';
            $entrypoints[$surface . '_frontend'] = 'frontend/' . $surface . '/index.js';
        }
    }
    $frontendEvents = [];
    if (in_array('admin', $surfaces, true)) {
        $frontendEvents['admin'] = ['admin.product.form.submit.before', 'admin.product.form.submit.after'];
    }
    if (in_array('merchant', $surfaces, true)) {
        $frontendEvents['merchant'] = ['merchant.store.form.submit.before', 'merchant.store.form.submit.after'];
    }
    if (in_array('mobile', $surfaces, true)) {
        $frontendEvents['mobile'] = ['mobile.order.submit.before', 'mobile.order.submit.after'];
    }
    $manifest = [
        'schema_version' => PluginTool::SCHEMA_VERSION,
        'code' => $code,
        'name' => $name,
        'version' => $version,
        'publisher' => $publisher,
        'description' => $description,
        'namespace' => $namespace,
        'core' => ['constraint' => $coreConstraint],
        'surfaces' => $surfaces,
        'initial_state' => 'resources/initial-state.json',
        'entrypoints' => $entrypoints,
        'dependencies' => [],
        'conflicts' => [],
        'events' => [
            'consumes' => ['product.updated', 'product.stock.changed', 'order.created', 'order.paid', 'order.cancelled', 'refund.approved'],
            'publishes' => [
                'plugin.' . $code . '.activity.started',
                'plugin.' . $code . '.stock.locked',
                'plugin.' . $code . '.order.confirmed',
                'plugin.' . $code . '.stock.released',
            ],
            'frontend_consumes' => $frontendEvents,
        ],
        'overrides' => [],
    ];
    $devRoots = [
        'resources' => 'plugins/' . $code . '/resources',
        'tests' => 'plugins/' . $code . '/tests',
    ];
    if (in_array('backend', $surfaces, true)) {
        $devRoots['backend'] = 'plugins/' . $code . '/backend';
    }
    foreach (['admin', 'merchant', 'mobile'] as $surface) {
        if (in_array($surface, $surfaces, true)) {
            $devRoots[$surface] = match ($surface) {
                'admin' => 'template/admin/src/plugins/' . $code,
                'merchant' => 'template/merchant/src/plugins/' . $code,
                'mobile' => 'template/uniapp/src/plugins/' . $code,
            };
        }
    }
    PluginTool::writeFile($pluginRoot . DIRECTORY_SEPARATOR . 'plugin.json', PluginTool::jsonEncode($manifest));
    PluginTool::writeFile($pluginRoot . DIRECTORY_SEPARATOR . 'plugin.dev.json', PluginTool::jsonEncode([
        'schema_version' => PluginTool::SCHEMA_VERSION,
        'plugin_code' => $code,
        'source_roots' => $devRoots,
    ]));
    PluginTool::writeFile($pluginRoot . DIRECTORY_SEPARATOR . 'README.md', '# ' . $name . PHP_EOL . PHP_EOL . $description . PHP_EOL);

    $state = [
        'schema_version' => PluginTool::SCHEMA_VERSION,
        'plugin_code' => $code,
        'lifecycle_status' => 'uninstalled',
        'enabled' => false,
        'installed_version' => null,
        'migration_version' => null,
        'license_status' => 'unknown',
        'build_status' => 'not_built',
        'channels' => ['backend' => 'not_installed', 'admin' => 'not_built', 'merchant' => 'not_built', 'h5' => 'not_built', 'mp_weixin' => 'not_built'],
    ];
    $menus = ['schema_version' => PluginTool::SCHEMA_VERSION, 'plugin_code' => $code, 'items' => []];
    foreach (['admin', 'merchant'] as $surface) {
        if (in_array($surface, $surfaces, true)) {
            $menus['items'][] = ['surface' => $surface, 'title' => $name, 'route' => '/' . $code . '/activity', 'permission' => 'plugin:' . $code . ':view', 'enabled' => false];
        }
    }
    $permissions = ['schema_version' => PluginTool::SCHEMA_VERSION, 'plugin_code' => $code, 'permissions' => ['plugin:' . $code . ':view', 'plugin:' . $code . ':manage']];
    PluginTool::writeFile($pluginRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'initial-state.json', PluginTool::jsonEncode($state));
    PluginTool::writeFile($pluginRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'menus.json', PluginTool::jsonEncode($menus));
    PluginTool::writeFile($pluginRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'permissions.json', PluginTool::jsonEncode($permissions));

    if (in_array('backend', $surfaces, true)) {
        copyTemplate($templateRoot . '/backend/src/Plugin.php.tpl', $pluginRoot . '/backend/src/' . $studly . 'Plugin.php', $replacements);
        copyTemplate($templateRoot . '/backend/src/Service/ActivityService.php.tpl', $pluginRoot . '/backend/src/Service/' . $studly . 'ActivityService.php', $replacements);
        copyTemplate($templateRoot . '/tests/ManifestTest.php.tpl', $pluginRoot . '/tests/' . $studly . 'ManifestTest.php', $replacements);
        copyTemplate($templateRoot . '/tests/ActivityServiceTest.php.tpl', $pluginRoot . '/tests/' . $studly . 'ActivityServiceTest.php', $replacements);
        copyTemplate($templateRoot . '/tests/EventHandlerTest.php.tpl', $pluginRoot . '/tests/' . $studly . 'EventHandlerTest.php', $replacements);
        copyTemplate($templateRoot . '/backend/database/migrations/README.md', $pluginRoot . '/backend/database/migrations/README.md', $replacements);
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (!in_array($surface, $surfaces, true)) {
                continue;
            }
            $controllerTemplate = $surface === 'mobile' ? 'Mobile/Activity.php.tpl' : ucfirst($surface) . '/Activity.php.tpl';
            $controllerFile = $surface === 'mobile' ? $studly . '.php' : $studly . 'Activity.php';
            copyTemplate($templateRoot . '/backend/src/Controller/' . $controllerTemplate, $pluginRoot . '/backend/src/Controller/' . ucfirst($surface) . '/' . $controllerFile, $replacements);
            copyTemplate($templateRoot . '/backend/routes/' . $surface . '.php.tpl', $pluginRoot . '/backend/routes/' . $surface . '.php', $replacements);
        }
    } else {
        // This branch is unreachable for the current surface contract, but
        // retaining tests/resources in the generated mirror keeps future
        // metadata-only plugins predictable.
        copyTemplate($templateRoot . '/tests/ManifestTest.php.tpl', $pluginRoot . '/tests/' . $studly . 'ManifestTest.php', $replacements);
    }

    foreach (['admin', 'merchant'] as $surface) {
        if (!in_array($surface, $surfaces, true)) {
            continue;
        }
        $front = $stageRoot . '/template/' . $surface . '/src/plugins/' . $code;
        copyTemplate($templateRoot . '/frontend/' . $surface . '/index.js.tpl', $front . '/index.js', $replacements);
        copyTemplate($templateRoot . '/frontend/' . $surface . '/routes.js.tpl', $front . '/routes.js', $replacements);
        copyTemplate($templateRoot . '/frontend/' . $surface . '/api/plugin.js.tpl', $front . '/api/' . $code . '.js', $replacements);
        copyTemplate($templateRoot . '/frontend/' . $surface . '/views/activity/index.vue.tpl', $front . '/views/activity/index.vue', $replacements);
    }
    if (in_array('mobile', $surfaces, true)) {
        $front = $stageRoot . '/template/uniapp/src/plugins/' . $code;
        copyTemplate($templateRoot . '/frontend/mobile/index.js.tpl', $front . '/index.js', $replacements);
        copyTemplate($templateRoot . '/frontend/mobile/api/plugin.js.tpl', $front . '/api/' . $code . '.js', $replacements);
        copyTemplate($templateRoot . '/frontend/mobile/pages/index/index.vue.tpl', $front . '/pages/index/index.vue', $replacements);
        copyTemplate($templateRoot . '/frontend/mobile/pages/detail/index.vue.tpl', $front . '/pages/detail/index.vue', $replacements);
        PluginTool::writeFile($front . '/components/README.md', '# ' . $name . ' mobile components' . PHP_EOL);
    }
}

function copyTemplate(string $template, string $destination, array $replacements): void
{
    PluginTool::copyRenderedTemplate($template, $destination, $replacements);
}
