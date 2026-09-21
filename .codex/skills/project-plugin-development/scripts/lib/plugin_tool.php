<?php

declare(strict_types=1);

/**
 * 插件开发命令行工具的统一异常，不依赖宿主框架启动。
 */
final class PluginToolException extends RuntimeException
{
}

/**
 * 为插件开发脚本提供参数、清单、受限路径、模板和文件操作工具。
 */
final class PluginTool
{
    public const SCHEMA_VERSION = '1.0';

    public const SURFACES = ['backend', 'admin', 'merchant', 'mobile'];

    public const PACKAGE_ROOTS = [
        'backend' => 'backend',
        'resources' => 'resources',
        'tests' => 'tests',
        'admin' => 'frontend/admin',
        'merchant' => 'frontend/merchant',
        'mobile' => 'frontend/mobile',
    ];

    public const FIXED_ZIP_TIME = 946684800;

    public const MAX_MANIFEST_BYTES = 2_000_000;

    public const MAX_FILE_BYTES = 50_000_000;

    public const MAX_TOTAL_BYTES = 500_000_000;

    public const MAX_FILE_COUNT = 20_000;

    /**
     * 解析长选项并统一参数名，保留重复选项的全部值。
     *
     * @param array $argv 命令行参数列表，首项为脚本名称。
     * @return array<string,mixed> 参数名到值的映射；重复选项对应值列表。
     */
    public static function parseArgs(array $argv): array
    {
        $options = [];
        for ($i = 1, $count = count($argv); $i < $count; $i++) {
            $token = (string) $argv[$i];
            if ($token === '--') {
                if ($i + 1 < $count) {
                    throw new PluginToolException('不支持位置参数：' . (string) $argv[$i + 1]);
                }
                break;
            }
            if (!str_starts_with($token, '--') || strlen($token) < 3) {
                throw new PluginToolException('参数必须使用 --key=value 或 --key value：' . $token);
            }
            $raw = substr($token, 2);
            $value = true;
            $separator = strpos($raw, '=');
            if ($separator === false) {
                $key = $raw;
                if ($i + 1 < $count && !str_starts_with((string) $argv[$i + 1], '--')) {
                    $value = (string) $argv[++$i];
                }
            } else {
                $key = substr($raw, 0, $separator);
                $value = substr($raw, $separator + 1);
            }
            $key = strtolower(str_replace('-', '_', trim($key)));
            if ($key === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw new PluginToolException('参数名不合法：' . $key);
            }
            if (array_key_exists($key, $options)) {
                if (!is_array($options[$key])) {
                    $options[$key] = [$options[$key]];
                }
                $options[$key][] = $value;
            } else {
                $options[$key] = $value;
            }
        }
        return $options;
    }

    /**
     * 读取选项的最后一个值，未提供时返回调用方默认值。
     *
     * @param array $options 经长选项解析后的参数映射。
     * @param string $name 待读取的命令行选项名。
     * @param mixed $default 选项缺失时使用的默认值。
     * @return mixed 选项的最后一个值或调用方默认值。
     */
    public static function option(array $options, string $name, mixed $default = null): mixed
    {
        $key = strtolower(str_replace('-', '_', $name));
        if (!array_key_exists($key, $options)) {
            return $default;
        }
        $value = $options[$key];
        return is_array($value) ? end($value) : $value;
    }

    /**
     * 读取必填文本选项，拒绝空值或布尔标记。
     *
     * @param array $options 经长选项解析后的参数映射。
     * @param string $name 必须提供的文本选项名。
     * @return string 去除首尾空白的非空文本选项值。
     */
    public static function requireOption(array $options, string $name): string
    {
        $value = self::option($options, $name);
        if (!is_string($value) || trim($value) === '') {
            throw new PluginToolException('缺少必填参数 --' . $name);
        }
        return trim($value);
    }

    /**
     * 解析显式或默认仓库根目录，并返回经过路径检查的真实目录。
     *
     * @param array $options 经长选项解析后的参数映射。
     * @return string 经真实路径解析和安全检查的仓库根目录。
     */
    public static function rootFromOptions(array $options): string
    {
        $configured = self::option($options, 'root');
        if (!is_string($configured) || trim($configured) === '') {
            $configured = dirname(__DIR__, 5);
        }
        $root = realpath($configured);
        if ($root === false || !is_dir($root)) {
            throw new PluginToolException('仓库根目录不存在：' . $configured);
        }
        self::assertNoSymlinkPath($root, $root);
        return $root;
    }

    /**
     * 校验插件编码的长度与安全字符，供目录和命名空间生成使用。
     *
     * @param string $code 待校验的插件编码。
     * @return string 符合长度与字符约束的插件编码。
     */
    public static function validateCode(string $code): string
    {
        $code = trim($code);
        if (strlen($code) > 64 || !preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            throw new PluginToolException('插件编码必须匹配 ^[a-z][a-z0-9_]*$ 且不超过 64 个字符：' . $code);
        }
        return $code;
    }

    /**
     * 校验插件名称非空、长度和控制字符约束。
     *
     * @param string $name 待校验的插件显示名称。
     * @return string 去除首尾空白且符合约束的插件名称。
     */
    public static function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 300 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new PluginToolException('插件名称不能为空、不能包含控制字符且长度不能超过 100 个字符');
        }
        return $name;
    }

    /**
     * 校验插件版本符合工具允许的语义化版本格式。
     *
     * @param string $version 待校验的插件版本文本。
     * @return string 经格式校验的插件版本文本。
     */
    public static function validateVersion(string $version): string
    {
        $version = trim($version);
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version)) {
            throw new PluginToolException('版本必须是 SemVer（例如 0.1.0）：' . $version);
        }
        return $version;
    }

    /**
     * 将下划线分隔的插件编码转换为类名片段。
     *
     * @param string $code 当前插件的安全编码。
     * @return string 适合生成插件类名的首字母大写片段。
     */
    public static function studly(string $code): string
    {
        $result = '';
        foreach (preg_split('/_+/', $code) ?: [] as $part) {
            $result .= ucfirst($part);
        }
        return $result;
    }

    /**
     * 按统一约定生成插件顶级命名空间。
     *
     * @param string $code 当前插件的安全编码。
     * @return string 以统一插件前缀开头的命名空间。
     */
    public static function namespaceFor(string $code): string
    {
        return 'Plugin\\' . self::studly($code);
    }

    /**
     * 生成可读且保留中文的 JSON 文本，并将编码错误转换为工具异常。
     *
     * @param mixed $value 待编码为 JSON 的数据。
     * @return string 带末尾换行的格式化 JSON 文本。
     */
    public static function jsonEncode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new PluginToolException('JSON 编码失败：' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * 限制清单大小并读取普通 JSON 文件，拒绝软链接和无效顶层结构。
     *
     * @param string $path 待读取的普通 JSON 文件路径。
     * @param string $label 校验错误中用于定位文件的名称。
     * @return array<string,mixed> 解码后的 JSON 数组结构。
     */
    public static function readJson(string $path, string $label = 'JSON 文件'): array
    {
        if (!is_file($path) || is_link($path)) {
            throw new PluginToolException($label . '不存在或不是普通文件：' . $path);
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_MANIFEST_BYTES) {
            throw new PluginToolException($label . '超过大小上限：' . $path);
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new PluginToolException('无法读取' . $label . '：' . $path);
        }
        try {
            $value = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PluginToolException($label . '格式无效：' . $exception->getMessage(), 0, $exception);
        }
        if (!is_array($value)) {
            throw new PluginToolException($label . '根节点必须是对象：' . $path);
        }
        return $value;
    }

    /**
     * 先写入同目录临时文件，再通过重命名原子替换目标文件。
     *
     * @param string $path 允许原子替换的目标文件路径。
     * @param string $contents 需要原子写入的完整文件内容。
     * @return void 无返回值。
     */
    public static function writeFile(string $path, string $contents): void
    {
        $parent = dirname($path);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new PluginToolException('无法创建目录：' . $parent);
        }
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new PluginToolException('无法写入文件：' . $path);
        }
        chmod($temporary, 0644);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new PluginToolException('无法原子写入文件：' . $path);
        }
    }

    /**
     * 统一相对路径分隔符，拒绝绝对路径、目录穿越和控制字符。
     *
     * @param string $path 尚未信任的输入相对路径。
     * @return string 不含穿越片段且使用正斜杠的相对路径。
     */
    public static function normalizeRelative(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new PluginToolException('路径不能为空或包含 NUL 字符');
        }
        $path = str_replace('\\', '/', $path);
        if (preg_match('#^(?:/|[A-Za-z]:/|//)#', $path)) {
            throw new PluginToolException('不允许绝对路径：' . $path);
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' || preg_match('/[\x00-\x1F\x7F]/', $segment)) {
                throw new PluginToolException('路径包含穿越或控制字符：' . $path);
            }
            $segments[] = $segment;
        }
        if ($segments === []) {
            throw new PluginToolException('路径归一化后为空：' . $path);
        }
        return implode('/', $segments);
    }

    /**
     * 按完整目录边界判断路径是否等于根目录或位于其内部。
     *
     * @param string $path 需要检查是否位于根目录内的路径。
     * @param string $root 已经确认的仓库或操作根目录。
     * @return bool 路径是否等于根目录或位于其子目录内。
     */
    public static function isWithin(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * 在受限根目录中解析相对路径，并按需检查存在性和软链接。
     *
     * @param string $root 已经确认的仓库或操作根目录。
     * @param string $relative 以对应根目录为基准的相对路径。
     * @param bool $mustExist 是否要求路径已存在并核对其真实目录边界。
     * @return string 位于受限根目录内的候选路径或已解析真实路径。
     */
    public static function resolveRelative(string $root, string $relative, bool $mustExist = true): string
    {
        $relative = self::normalizeRelative($relative);
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        self::assertNoSymlinkPath($candidate, $root);
        if (!$mustExist) {
            return $candidate;
        }
        $resolved = realpath($candidate);
        if ($resolved === false || !self::isWithin($resolved, $root)) {
            throw new PluginToolException('路径不存在或越过仓库根目录：' . $relative);
        }
        self::assertNoSymlinkPath($resolved, $root);
        return $resolved;
    }

    /**
     * 逐级拒绝软链接或越界路径，尚未创建的末级路径允许后续安全写入。
     *
     * @param string $path 待逐级检查边界和软链接的路径。
     * @param string $root 已经确认的仓库或操作根目录。
     * @return void 无返回值。
     */
    public static function assertNoSymlinkPath(string $path, string $root): void
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if (!self::isWithin($path, $root)) {
            throw new PluginToolException('路径越过仓库根目录：' . $path);
        }
        if (is_link($root)) {
            throw new PluginToolException('仓库根目录不能是软链接：' . $root);
        }
        $relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
        $current = $root;
        if ($relative === '') {
            return;
        }
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new PluginToolException('不允许通过软链接访问插件路径：' . $current);
            }
            if (!file_exists($current)) {
                break;
            }
        }
    }

    /**
     * 递归归集普通文件，检查软链接、真实路径边界和单文件大小后稳定排序。
     *
     * @param string $directory 需要归集的普通源码目录。
     * @param string $root 已经确认的仓库或操作根目录。
     * @return array<int,array{absolute:string,relative:string,size:int}> 按相对路径稳定排序的文件绝对路径、相对路径和大小列表。
     */
    public static function listFiles(string $directory, string $root): array
    {
        if (!is_dir($directory) || is_link($directory)) {
            throw new PluginToolException('源码根目录不存在或是软链接：' . $directory);
        }
        $directory = realpath($directory) ?: $directory;
        $root = realpath($root) ?: $root;
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $info) {
            $absolute = $info->getPathname();
            if ($info->isLink() || is_link($absolute)) {
                throw new PluginToolException('源码树中不允许软链接：' . $absolute);
            }
            if (!$info->isFile()) {
                continue;
            }
            $real = realpath($absolute);
            if ($real === false || !self::isWithin($real, $root)) {
                throw new PluginToolException('源码文件越过仓库根目录：' . $absolute);
            }
            $size = $info->getSize();
            if ($size > self::MAX_FILE_BYTES) {
                throw new PluginToolException('单文件超过大小上限：' . $absolute);
            }
            $files[] = ['absolute' => $absolute, 'relative' => self::relativePath($absolute, $root), 'size' => $size];
        }
        usort($files, static fn (array $a, array $b): int => strcmp($a['relative'], $b['relative']));
        return $files;
    }

    /**
     * 将根目录内路径转换为可校验的统一相对路径。
     *
     * @param string $path 根目录内待转换的文件路径。
     * @param string $root 已经确认的仓库或操作根目录。
     * @return string 通过相对路径约束检查的文件相对路径。
     */
    public static function relativePath(string $path, string $root): string
    {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $root = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $root), '/');
        if (!str_starts_with($path, $root . '/')) {
            throw new PluginToolException('路径不在根目录内：' . $path);
        }
        return self::normalizeRelative(substr($path, strlen($root) + 1));
    }

    /**
     * 只清理允许根目录内的指定子树，拒绝删除根目录本身或跟随末级软链接。
     *
     * @param string $path 本次允许清理的具体子树或临时文件路径。
     * @param string $allowedRoot 清理操作允许访问且不可删除的根目录。
     * @return void 无返回值。
     */
    public static function removeTree(string $path, string $allowedRoot): void
    {
        $resolvedParent = realpath(dirname($path));
        if ($resolvedParent !== false) {
            // 规范化 macOS 的 /var -> /private/var，同时保留末级软链接本身。
            $path = rtrim($resolvedParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
        }
        $allowedRoot = realpath($allowedRoot) ?: $allowedRoot;
        if (!self::isWithin($path, $allowedRoot) || rtrim($path, DIRECTORY_SEPARATOR) === rtrim($allowedRoot, DIRECTORY_SEPARATOR)) {
            throw new PluginToolException('拒绝删除根目录外或根目录本身：' . $path);
        }
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
            self::removeTree($item->getPathname(), $allowedRoot);
        }
        @rmdir($path);
    }

    /**
     * 定位插件源码模板目录，缺失时中止开发命令。
     *
     * @return string 现有插件源码模板的真实目录路径。
     */
    public static function templateRoot(): string
    {
        $path = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugin-template');
        if ($path === false || !is_dir($path)) {
            throw new PluginToolException('插件模板目录不存在');
        }
        return $path;
    }

    /**
     * 按替换表渲染普通模板文件，并原子写入目标位置。
     *
     * @param string $templatePath 待渲染的普通模板文件路径。
     * @param string $destination 渲染结果写入的目标文件路径。
     * @param array $replacements 模板占位符与替换内容组成的映射。
     * @return void 无返回值。
     */
    public static function copyRenderedTemplate(string $templatePath, string $destination, array $replacements): void
    {
        if (!is_file($templatePath) || is_link($templatePath)) {
            throw new PluginToolException('模板文件不存在：' . $templatePath);
        }
        $contents = file_get_contents($templatePath);
        if ($contents === false) {
            throw new PluginToolException('无法读取模板：' . $templatePath);
        }
        self::writeFile($destination, strtr($contents, $replacements));
    }

    /**
     * 创建受限目录，并在创建前后检查路径未经过软链接。
     *
     * @param string $path 准备安全创建的目录路径。
     * @param string $root 已经确认的仓库或操作根目录。
     * @return void 无返回值。
     */
    public static function ensureDirectory(string $path, string $root): void
    {
        self::assertNoSymlinkPath($path, $root);
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new PluginToolException('无法创建目录：' . $path);
        }
        self::assertNoSymlinkPath($path, $root);
    }
}

/**
 * 集中收集静态校验错误与警告，并在结束时统一报告阻断错误。
 */
final class PluginValidationBag
{
    /**
     * 已收集的阻断错误，供结束时统一报告。
     *
     * @var array<int,string>
     */
    public array $errors = [];

    /**
     * 已收集的非阻断警告，保留给调用方展示。
     *
     * @var array<int,string>
     */
    public array $warnings = [];

    /**
     * 记录会阻止校验通过的错误，保留其他检查继续收集问题。
     *
     * @param string $message 本次静态校验的错误或警告说明。
     * @return void 无返回值。
     */
    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * 记录不阻止校验通过的警告，供最终报告或打包结果展示。
     *
     * @param string $message 本次静态校验的错误或警告说明。
     * @return void 无返回值。
     */
    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * 汇总已收集的错误并抛出统一异常，无错误时正常结束。
     *
     * @return void 无返回值。
     */
    public function assertValid(): void
    {
        if ($this->errors === []) {
            return;
        }
        $lines = ['插件校验失败：'];
        foreach ($this->errors as $error) {
            $lines[] = ' - ' . $error;
        }
        throw new PluginToolException(implode(PHP_EOL, $lines));
    }
}

/**
 * 静态检查插件开发源码和未签名源码包，不代替正式制品签名或运行时验证。
 */
final class PluginValidator
{
    /**
     * 检查开发清单、端侧源码映射、初始状态和 PHP 语法，返回可归集的源码清单。
     *
     * @param string $root 已经确认的仓库或操作根目录。
     * @param string $pluginPath 仓库内待检查的插件开发目录。
     * @return array{manifest:array<string,mixed>,dev:array<string,mixed>,files:array<int,array{source_root:string,package_root:string,absolute:string,relative:string,source_relative:string,size:int}>,warnings:array<int,string>} 已校验的插件清单、开发映射、源码文件和非阻断警告。
     */
    public static function development(string $root, string $pluginPath): array
    {
        $bag = new PluginValidationBag();
        if (!is_dir($pluginPath) || is_link($pluginPath)) {
            throw new PluginToolException('插件目录不存在或是软链接：' . $pluginPath);
        }
        try {
            $manifest = PluginTool::readJson($pluginPath . DIRECTORY_SEPARATOR . 'plugin.json', 'plugin.json');
        } catch (PluginToolException $exception) {
            $bag->error($exception->getMessage());
            $manifest = [];
        }
        try {
            $dev = PluginTool::readJson($pluginPath . DIRECTORY_SEPARATOR . 'plugin.dev.json', 'plugin.dev.json');
        } catch (PluginToolException $exception) {
            $bag->error($exception->getMessage());
            $dev = [];
        }
        self::validateManifest($manifest, $bag);
        $code = is_string($manifest['code'] ?? null) ? $manifest['code'] : '';
        if ($code !== '' && basename(rtrim($pluginPath, DIRECTORY_SEPARATOR)) !== $code) {
            $bag->error('plugin.json.code 必须与插件目录名一致');
        }
        $allowedTop = ['plugin.json' => true, 'plugin.dev.json' => true, 'resources' => true, 'backend' => true, 'tests' => true, 'README.md' => true, 'CHANGELOG.md' => true, 'LICENSE' => true];
        foreach (new FilesystemIterator($pluginPath, FilesystemIterator::SKIP_DOTS) as $item) {
            if (!isset($allowedTop[$item->getFilename()])) {
                $bag->error('插件根目录包含未声明项：' . $item->getFilename());
            }
            if ($item->isLink()) {
                $bag->error('插件目录不允许软链接：' . $item->getPathname());
            }
        }

        $surfaces = self::surfaces($manifest);
        $sourceRoots = is_array($dev['source_roots'] ?? null) ? $dev['source_roots'] : [];
        if (($dev['schema_version'] ?? null) !== PluginTool::SCHEMA_VERSION) {
            $bag->error('plugin.dev.json.schema_version 必须为 ' . PluginTool::SCHEMA_VERSION);
        }
        if (($dev['plugin_code'] ?? null) !== $code || $code === '') {
            $bag->error('plugin.dev.json.plugin_code 必须与 plugin.json.code 一致');
        }
        $required = ['resources', 'tests'];
        if (in_array('backend', $surfaces, true)) {
            $required[] = 'backend';
        }
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (in_array($surface, $surfaces, true)) {
                $required[] = $surface;
            }
        }
        foreach ($required as $key) {
            if (!isset($sourceRoots[$key]) || !is_string($sourceRoots[$key])) {
                $bag->error('plugin.dev.json 缺少 source_roots.' . $key);
            }
        }
        foreach (array_keys($sourceRoots) as $key) {
            if (!in_array($key, $required, true)) {
                $bag->error('plugin.dev.json 包含未声明 source_roots.' . (string) $key);
            }
        }

        $files = [];
        $seenRoots = [];
        foreach ($sourceRoots as $key => $relative) {
            if (!is_string($key) || !is_string($relative)) {
                $bag->error('source_roots 的键和值必须是字符串');
                continue;
            }
            try {
                $normalized = PluginTool::normalizeRelative($relative);
                $absolute = PluginTool::resolveRelative($root, $normalized, true);
                if (!is_dir($absolute)) {
                    throw new PluginToolException('必须指向目录');
                }
                $rootIdentity = strtolower($absolute);
                if (isset($seenRoots[$rootIdentity])) {
                    throw new PluginToolException('source_roots 重复指向同一目录');
                }
                $seenRoots[$rootIdentity] = true;
                self::assertMapping((string) $key, $code, $normalized);
                $rootFiles = PluginTool::listFiles($absolute, $root);
                if ($rootFiles === [] && in_array($key, ['resources', 'tests', 'backend'], true)) {
                    $bag->error('source_roots.' . $key . ' 不能为空');
                }
                foreach ($rootFiles as $file) {
                    $files[] = [
                        'source_root' => $key,
                        'package_root' => PluginTool::PACKAGE_ROOTS[$key] ?? $key,
                        'absolute' => $file['absolute'],
                        'relative' => $file['relative'],
                        'source_relative' => substr($file['relative'], strlen($normalized) + 1),
                        'size' => $file['size'],
                    ];
                }
            } catch (PluginToolException $exception) {
                $bag->error('source_roots.' . (string) $key . '：' . $exception->getMessage());
            }
        }
        self::validateManifestPaths($manifest, $bag);
        self::validateSourceFiles($code, $manifest, $files, $bag);
        self::validateInitialState($root, $manifest, $sourceRoots, $bag);
        self::lintPhpFiles($files, $bag);
        $bag->assertValid();
        return ['manifest' => $manifest, 'dev' => $dev, 'files' => $files, 'warnings' => $bag->warnings];
    }

    /**
     * 从插件清单提取字符串端侧标识，结构缺失时返回空列表。
     *
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @return array<int,string> 插件清单中可读取的端侧字符串列表。
     */
    private static function surfaces(array $manifest): array
    {
        $surfaces = $manifest['surfaces'] ?? [];
        return is_array($surfaces) ? array_values(array_filter($surfaces, 'is_string')) : [];
    }

    /**
     * 要求源码根目录与插件编码、端侧对应的约定开发目录一致。
     *
     * @param string $key 源码根目录对应的端侧或资源键。
     * @param string $code 当前插件的安全编码。
     * @param string $relative 以对应根目录为基准的相对路径。
     * @return void 无返回值。
     */
    private static function assertMapping(string $key, string $code, string $relative): void
    {
        $expected = match ($key) {
            'backend', 'resources', 'tests' => 'plugins/' . $code . '/' . $key,
            'admin' => 'template/admin/src/plugins/' . $code,
            'merchant' => 'template/merchant/src/plugins/' . $code,
            'mobile' => 'template/uniapp/src/plugins/' . $code,
            default => null,
        };
        if ($expected !== null && $relative !== $expected) {
            throw new PluginToolException('必须使用约定开发路径：' . $expected);
        }
    }

    /**
     * 校验插件身份、端侧入口、事件和覆盖声明的结构与安全约束。
     *
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateManifest(array $manifest, PluginValidationBag $bag): void
    {
        if (($manifest['schema_version'] ?? null) !== PluginTool::SCHEMA_VERSION) {
            $bag->error('plugin.json.schema_version 必须为 ' . PluginTool::SCHEMA_VERSION);
        }
        $code = $manifest['code'] ?? null;
        if (!is_string($code) || $code === '') {
            $bag->error('plugin.json.code 必须是非空字符串');
            $code = '';
        } else {
            try {
                PluginTool::validateCode($code);
            } catch (PluginToolException $exception) {
                $bag->error($exception->getMessage());
            }
        }
        $name = $manifest['name'] ?? null;
        if (!is_string($name) || trim($name) === '' || strlen($name) > 300 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $bag->error('plugin.json.name 必须是非空字符串且不能包含控制字符');
        }
        $version = $manifest['version'] ?? null;
        if (!is_string($version)) {
            $bag->error('plugin.json.version 必须是 SemVer 字符串');
        } else {
            try {
                PluginTool::validateVersion($version);
            } catch (PluginToolException $exception) {
                $bag->error($exception->getMessage());
            }
        }
        $namespace = $manifest['namespace'] ?? null;
        $expectedNamespace = $code !== '' ? PluginTool::namespaceFor($code) : '';
        if (!is_string($namespace) || $namespace !== $expectedNamespace) {
            $bag->error('plugin.json.namespace 必须为 ' . $expectedNamespace);
        }
        if (!is_array($manifest['publisher'] ?? null) && (!isset($manifest['publisher']) || !is_string($manifest['publisher']))) {
            $bag->error('plugin.json.publisher 必须是字符串');
        }
        if (!is_array($manifest['core'] ?? null) || !is_string($manifest['core']['constraint'] ?? null) || trim((string) $manifest['core']['constraint']) === '') {
            $bag->error('plugin.json.core.constraint 必须是非空字符串');
        }
        $surfaces = $manifest['surfaces'] ?? null;
        if (!is_array($surfaces) || $surfaces === []) {
            $bag->error('plugin.json.surfaces 不能为空');
            $surfaces = [];
        }
        $seen = [];
        foreach ($surfaces as $surface) {
            if (!is_string($surface) || !in_array($surface, PluginTool::SURFACES, true)) {
                $bag->error('plugin.json.surfaces 包含不支持的端');
                continue;
            }
            if (isset($seen[$surface])) {
                $bag->error('plugin.json.surfaces 不能重复：' . $surface);
            }
            $seen[$surface] = true;
        }
        $initial = $manifest['initial_state'] ?? null;
        if (!is_string($initial)) {
            $bag->error('plugin.json.initial_state 必须是相对路径');
        } else {
            try {
                if (PluginTool::normalizeRelative($initial) !== 'resources/initial-state.json') {
                    $bag->error('plugin.json.initial_state 必须指向 resources/initial-state.json');
                }
            } catch (PluginToolException $exception) {
                $bag->error('plugin.json.initial_state：' . $exception->getMessage());
            }
        }
        $entrypoints = $manifest['entrypoints'] ?? null;
        if (!is_array($entrypoints)) {
            $bag->error('plugin.json.entrypoints 必须是对象');
            $entrypoints = [];
        }
        $required = [];
        if (in_array('backend', $surfaces, true)) {
            $required[] = 'provider';
        }
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (in_array($surface, $surfaces, true)) {
                $required[] = $surface . '_routes';
                $required[] = $surface . '_frontend';
            }
        }
        foreach ($required as $key) {
            if (!isset($entrypoints[$key]) || !is_string($entrypoints[$key]) || trim($entrypoints[$key]) === '') {
                $bag->error('plugin.json.entrypoints.' . $key . ' 未声明');
            }
        }
        $allowedEntrypoints = ['provider', 'admin_routes', 'merchant_routes', 'mobile_routes', 'admin_frontend', 'merchant_frontend', 'mobile_frontend'];
        foreach (array_keys($entrypoints) as $key) {
            if (!in_array($key, $allowedEntrypoints, true)) {
                $bag->error('plugin.json.entrypoints 包含未知键：' . (string) $key);
            }
        }
        if ($code !== '' && isset($entrypoints['provider']) && $entrypoints['provider'] !== PluginTool::namespaceFor($code) . '\\' . PluginTool::studly($code) . 'Plugin') {
            $bag->error('entrypoints.provider 与插件命名空间不匹配');
        }
        foreach (['dependencies', 'conflicts'] as $key) {
            if (!is_array($manifest[$key] ?? null)) {
                $bag->error('plugin.json.' . $key . ' 必须是数组');
                continue;
            }
            foreach ($manifest[$key] as $dependency) {
                if (!is_string($dependency) || !preg_match('/^[a-z][a-z0-9_]*(?:@[0-9A-Za-z.+<>=*^~ -]+)?$/', $dependency)) {
                    $bag->error('plugin.json.' . $key . ' 包含不合法依赖');
                }
            }
        }
        $events = $manifest['events'] ?? null;
        if (!is_array($events)) {
            $bag->error('plugin.json.events 必须是对象');
            $events = [];
        }
        foreach (['consumes', 'publishes'] as $key) {
            if (!is_array($events[$key] ?? null)) {
                $bag->error('plugin.json.events.' . $key . ' 必须是数组');
                continue;
            }
            $eventSeen = [];
            foreach ($events[$key] as $event) {
                if (!is_string($event) || !preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $event)) {
                    $bag->error('plugin.json.events.' . $key . ' 包含不合法事件名');
                    continue;
                }
                if (isset($eventSeen[$event])) {
                    $bag->error('plugin.json.events.' . $key . ' 不能重复：' . $event);
                }
                $eventSeen[$event] = true;
                if ($key === 'publishes' && $code !== '' && !str_starts_with($event, 'plugin.' . $code . '.')) {
                    $bag->error('自有发布事件必须以 plugin.' . $code . '. 开头：' . $event);
                }
            }
        }
        $frontendEvents = $events['frontend_consumes'] ?? null;
        if (!is_array($frontendEvents)) {
            $bag->error('plugin.json.events.frontend_consumes 必须是对象');
        } else {
            foreach ($frontendEvents as $surface => $eventList) {
                if (!in_array($surface, ['admin', 'merchant', 'mobile'], true) || !in_array($surface, $surfaces, true)) {
                    $bag->error('frontend_consumes.' . (string) $surface . ' 未在 surfaces 中合法声明');
                }
                if (!is_array($eventList)) {
                    $bag->error('frontend_consumes.' . (string) $surface . ' 必须是数组');
                    continue;
                }
                foreach ($eventList as $event) {
                    if (!is_string($event) || !preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $event)) {
                        $bag->error('frontend_consumes 包含不合法事件名');
                    }
                }
            }
        }
        $overrides = $manifest['overrides'] ?? null;
        if (!is_array($overrides)) {
            $bag->error('plugin.json.overrides 必须是数组');
        } else {
            foreach ($overrides as $index => $override) {
                if (!is_array($override)) {
                    $bag->error('overrides[' . (string) $index . '] 必须是对象');
                    continue;
                }
                $target = $override['target_path'] ?? null;
                if (!is_string($target)) {
                    $bag->error('overrides[' . (string) $index . '].target_path 必须是相对路径');
                    continue;
                }
                try {
                    $target = PluginTool::normalizeRelative($target);
                    if (!self::allowedOverride($target)) {
                        $bag->error('覆盖目标不在白名单内：' . $target);
                    }
                } catch (PluginToolException $exception) {
                    $bag->error('overrides[' . (string) $index . ']：' . $exception->getMessage());
                }
                foreach (['source_path', 'reason'] as $requiredKey) {
                    if (!isset($override[$requiredKey]) || !is_string($override[$requiredKey]) || trim($override[$requiredKey]) === '') {
                        $bag->error('overrides[' . (string) $index . '].' . $requiredKey . ' 未填写');
                    }
                }
                if (isset($override['base_sha256']) && (!is_string($override['base_sha256']) || !preg_match('/^[a-f0-9]{64}$/', $override['base_sha256']))) {
                    $bag->error('overrides[' . (string) $index . '].base_sha256 必须是 64 位小写 SHA-256');
                }
            }
        }
    }

    /**
     * 判断覆盖目标是否属于工具白名单，同时排除宿主事件入口和版本库内部路径。
     *
     * @param string $target 准备覆盖的宿主相对路径。
     * @return bool 覆盖目标是否通过路径白名单检查。
     */
    private static function allowedOverride(string $target): bool
    {
        foreach (['app/', 'template/admin/src/', 'template/merchant/src/', 'template/uniapp/'] as $prefix) {
            if (str_starts_with($target, $prefix)) {
                return $target !== 'app/event.php' && !str_contains($target, '/.git/');
            }
        }
        return false;
    }

    /**
     * 检查已声明端侧的路由和前端入口使用约定的包内路径。
     *
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateManifestPaths(array $manifest, PluginValidationBag $bag): void
    {
        $surfaces = self::surfaces($manifest);
        $entries = is_array($manifest['entrypoints'] ?? null) ? $manifest['entrypoints'] : [];
        $expected = [];
        if (in_array('admin', $surfaces, true)) {
            $expected['admin_routes'] = 'backend/routes/admin.php';
            $expected['admin_frontend'] = 'frontend/admin/index.js';
        }
        if (in_array('merchant', $surfaces, true)) {
            $expected['merchant_routes'] = 'backend/routes/merchant.php';
            $expected['merchant_frontend'] = 'frontend/merchant/index.js';
        }
        if (in_array('mobile', $surfaces, true)) {
            $expected['mobile_routes'] = 'backend/routes/mobile.php';
            $expected['mobile_frontend'] = 'frontend/mobile/index.js';
        }
        foreach ($expected as $key => $expectedPath) {
            if (!isset($entries[$key]) || !is_string($entries[$key])) {
                continue;
            }
            try {
                if (PluginTool::normalizeRelative($entries[$key]) !== $expectedPath) {
                    $bag->error('entrypoints.' . $key . ' 必须为 ' . $expectedPath);
                }
            } catch (PluginToolException $exception) {
                $bag->error('entrypoints.' . $key . '：' . $exception->getMessage());
            }
        }
    }

    /**
     * 检查源码规模、包内路径冲突、命名空间和迁移安全，再核对端侧必需资源。
     *
     * @param string $code 当前插件的安全编码。
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param array<int,array{source_root:string,package_root:string,absolute:string,relative:string,source_relative:string,size:int}> $files 按声明的源码根目录归集的开发文件清单。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateSourceFiles(string $code, array $manifest, array $files, PluginValidationBag $bag): void
    {
        $total = 0;
        $packageNames = [];
        $namespace = PluginTool::namespaceFor($code);
        foreach ($files as $file) {
            $total += $file['size'];
            $inside = $file['source_relative'];
            try {
                $inside = PluginTool::normalizeRelative($inside);
            } catch (PluginToolException $exception) {
                $bag->error($exception->getMessage());
                continue;
            }
            $packagePath = PluginTool::PACKAGE_ROOTS[$file['source_root']] . '/' . $inside;
            $key = strtolower($packagePath);
            if (isset($packageNames[$key])) {
                $bag->error('打包路径大小写或名称冲突：' . $packagePath);
            }
            $packageNames[$key] = true;
            if (self::forbidden($inside)) {
                $bag->warning('打包时将排除敏感或构建文件：' . $file['relative']);
            }
            if ($file['source_root'] === 'backend' && str_starts_with($inside, 'src/') && str_ends_with($inside, '.php')) {
                $contents = @file_get_contents($file['absolute']);
                if ($contents === false || !preg_match('/^\s*namespace\s+([^;]+);/mi', $contents, $matches)) {
                    $bag->error('backend/src PHP 文件必须声明命名空间：' . $file['relative']);
                } elseif (trim($matches[1]) !== $namespace && !str_starts_with(trim($matches[1]), $namespace . '\\')) {
                    $bag->error('PHP 命名空间不属于 ' . $namespace . '：' . $file['relative']);
                }
            }
            if ($file['source_root'] === 'backend' && str_starts_with($inside, 'database/migrations/') && str_ends_with($inside, '.php')) {
                $contents = @file_get_contents($file['absolute']);
                if ($contents !== false && preg_match('/\b(?:shell_exec|exec|system|passthru|proc_open|popen)\s*\(/i', $contents)) {
                    $bag->error('迁移文件不得执行 Shell 命令：' . $file['relative']);
                }
            }
        }
        if (count($files) > PluginTool::MAX_FILE_COUNT) {
            $bag->error('插件源码文件数量超过上限 ' . PluginTool::MAX_FILE_COUNT);
        }
        if ($total > PluginTool::MAX_TOTAL_BYTES) {
            $bag->error('插件源码总大小超过上限 ' . PluginTool::MAX_TOTAL_BYTES . ' 字节');
        }
        self::validateMigrations($files, $bag);
        self::validateRoutes($files, $manifest, $bag);
        self::validateFrontends($files, $manifest, $bag);
    }

    /**
     * 识别环境配置、凭据、依赖、缓存和构建输出等不应进入源码包的路径。
     *
     * @param string $relative 以对应根目录为基准的相对路径。
     * @return bool 路径是否属于需要从源码包排除的文件或目录。
     */
    private static function forbidden(string $relative): bool
    {
        $lower = strtolower($relative);
        $base = strtolower(basename($relative));
        if (str_starts_with($base, '.env')) {
            return true;
        }
        foreach (['/.git/', '/runtime/', '/node_modules/', '/vendor/', '/dist/', '/cache/', '/caches/', '/logs/', '/unpackage/'] as $part) {
            if (str_contains('/' . $lower . '/', $part)) {
                return true;
            }
        }
        foreach (['.pem', '.key', '.p12', '.pfx', '.crt', '.cer'] as $suffix) {
            if (str_ends_with($base, $suffix)) {
                return true;
            }
        }
        return str_contains($base, 'secret') || str_contains($base, 'private_key') || str_contains($base, 'access_token');
    }

    /**
     * 向归集脚本提供与校验器一致的源码包排除规则。
     *
     * @param string $relative 以对应根目录为基准的相对路径。
     * @return bool 归集时是否应排除该路径。
     */
    public static function isExcludedPath(string $relative): bool
    {
        return self::forbidden($relative);
    }

    /**
     * 静态检查迁移文件包含类声明且迁移类名没有重复，不执行迁移。
     *
     * @param array<int,array{source_root:string,package_root:string,absolute:string,relative:string,source_relative:string,size:int}> $files 已归集的源码文件或源码包文件清单。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateMigrations(array $files, PluginValidationBag $bag): void
    {
        $classes = [];
        foreach ($files as $file) {
            if ($file['source_root'] !== 'backend' || !str_starts_with($file['source_relative'], 'database/migrations/') || !str_ends_with($file['source_relative'], '.php')) {
                continue;
            }
            $contents = @file_get_contents($file['absolute']);
            if ($contents === false || !preg_match('/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $matches)) {
                $bag->error('迁移 PHP 文件必须包含类声明：' . $file['relative']);
                continue;
            }
            if (isset($classes[$matches[1]])) {
                $bag->error('迁移类名重复：' . $matches[1]);
            }
            $classes[$matches[1]] = true;
        }
    }

    /**
     * 核对每个声明端侧的后端路由文件，并检查原生插件路由格式。
     *
     * @param array<int,array{source_root:string,package_root:string,absolute:string,relative:string,source_relative:string,size:int}> $files 已归集的源码文件或源码包文件清单。
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateRoutes(array $files, array $manifest, PluginValidationBag $bag): void
    {
        $routes = [];
        foreach ($files as $file) {
            if ($file['source_root'] === 'backend' && str_starts_with($file['source_relative'], 'routes/') && str_ends_with($file['source_relative'], '.php')) {
                $routes[$file['source_relative']] = $file['absolute'];
            }
        }
        $code = (string) ($manifest['code'] ?? '');
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (!in_array($surface, self::surfaces($manifest), true)) {
                continue;
            }
            $relative = 'routes/' . $surface . '.php';
            if (!isset($routes[$relative])) {
                $bag->error('缺少后端路由文件：' . $relative);
                continue;
            }
            self::validateNativeRouteFile($routes[$relative], $relative, $surface, $code, $bag);
        }
    }

    /**
     * 校验唯一插件分组、合法相对路由和静态路径优先顺序，不执行路由文件。
     *
     * @param string $absolute 待检查的后端路由文件绝对路径。
     * @param string $label 报告中使用的路由相对路径。
     * @param string $surface 清单声明的当前端侧标识。
     * @param string $code 当前插件的安全编码。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateNativeRouteFile(
        string $absolute,
        string $label,
        string $surface,
        string $code,
        PluginValidationBag $bag
    ): void {
        $contents = @file_get_contents($absolute);
        if ($contents === false) {
            $bag->error('无法读取路由文件：' . $label);
            return;
        }

        $calls = self::extractRouteCalls($contents);
        $groups = array_values(array_filter(
            $calls,
            static fn(array $call): bool => $call['method'] === 'group'
        ));
        $expectedGroup = 'api/' . $surface . '/plugins/' . $code;
        if (count($groups) !== 1 || $groups[0]['path'] !== $expectedGroup) {
            $bag->error('路由文件必须使用唯一分组 Route::group(\'' . $expectedGroup . '\', ...)：' . $label);
        }

        $routes = array_values(array_filter(
            $calls,
            static fn(array $call): bool => $call['method'] !== 'group'
        ));
        if ($routes === []) {
            $bag->error('路由文件未找到原生 Route 路由声明：' . $label);
            return;
        }

        // 静态路径优先，避免被此前声明的动态参数路由吞掉。
        $dynamicSeen = false;
        foreach ($routes as $route) {
            $path = $route['path'];
            $validPath = $path !== ''
                && $path === trim($path)
                && !str_starts_with($path, '/')
                && !str_starts_with($path, 'api/')
                && !str_contains($path, '..')
                && !str_contains($path, '//')
                && preg_match(
                    '#^(?:[A-Za-z0-9._~-]+|:[A-Za-z_][A-Za-z0-9_]*)(?:/(?:[A-Za-z0-9._~-]+|:[A-Za-z_][A-Za-z0-9_]*))*$#',
                    $path
                ) === 1;
            if (!$validPath) {
                $bag->error('插件分组内必须使用合法相对路由：' . $path . '（' . $label . '）');
                continue;
            }

            $dynamic = preg_match('#(?:^|/):[A-Za-z_][A-Za-z0-9_]*(?:/|$)#', $path) === 1;
            if (!$dynamic && $dynamicSeen) {
                $bag->error('路由动态参数必须排在静态路径之后：' . $label);
            }
            $dynamicSeen = $dynamicSeen || $dynamic;
        }
    }

    /**
     * 提取原生路由调用的首个字符串参数。
     * 使用 PHP 词法分析，避免将注释和普通字符串中的示例误判为路由。
     *
     * @param string $contents 待分析的原生后端路由 PHP 源码。
     * @return list<array{method:string,path:string}> 按源码出现顺序提取的路由方法和字符串路径列表。
     */
    private static function extractRouteCalls(string $contents): array
    {
        $tokens = token_get_all($contents);
        $calls = [];
        $allowed = ['group', 'get', 'post', 'put', 'patch', 'delete', 'any'];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || $token[0] !== T_STRING || strcasecmp($token[1], 'Route') !== 0) {
                continue;
            }

            // 只识别真实静态调用及字面量首参数，不解释变量或执行源码。
            $separator = self::nextSignificantTokenIndex($tokens, $index + 1);
            $methodIndex = $separator === null ? null : self::nextSignificantTokenIndex($tokens, $separator + 1);
            $opening = $methodIndex === null ? null : self::nextSignificantTokenIndex($tokens, $methodIndex + 1);
            $argument = $opening === null ? null : self::nextSignificantTokenIndex($tokens, $opening + 1);
            if (
                $separator === null
                || !is_array($tokens[$separator])
                || $tokens[$separator][0] !== T_DOUBLE_COLON
                || $methodIndex === null
                || !is_array($tokens[$methodIndex])
                || $tokens[$methodIndex][0] !== T_STRING
                || $opening === null
                || $tokens[$opening] !== '('
                || $argument === null
                || !is_array($tokens[$argument])
                || $tokens[$argument][0] !== T_CONSTANT_ENCAPSED_STRING
            ) {
                continue;
            }

            $method = strtolower($tokens[$methodIndex][1]);
            if (!in_array($method, $allowed, true)) {
                continue;
            }

            $literal = $tokens[$argument][1];
            $quote = $literal[0] ?? '';
            if (($quote !== "'" && $quote !== '"') || substr($literal, -1) !== $quote) {
                continue;
            }
            $path = substr($literal, 1, -1);
            $path = $quote === "'"
                ? str_replace(["\\\\", "\\'"], ["\\", "'"], $path)
                : stripcslashes($path);
            $calls[] = ['method' => $method, 'path' => $path];
        }

        return $calls;
    }

    /**
     * 跳过注释和空白，返回指定起点之后首个有效词法单元的位置。
     *
     * @param array<int, array|string> $tokens PHP 词法分析得到的单元列表。
     * @param int $offset 开始寻找有效单元的零基索引。
     * @return ?int 首个有效单元索引；没有匹配单元时为空。
     */
    private static function nextSignificantTokenIndex(array $tokens, int $offset): ?int
    {
        $count = count($tokens);
        for ($index = $offset; $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $index;
        }
        return null;
    }

    /**
     * 核对声明端侧的前端必需文件、注册入口和消费事件提示。
     *
     * @param array<int,array{source_root:string,package_root:string,absolute:string,relative:string,source_relative:string,size:int}> $files 已归集的源码文件或源码包文件清单。
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateFrontends(array $files, array $manifest, PluginValidationBag $bag): void
    {
        $byRoot = [];
        foreach ($files as $file) {
            $byRoot[$file['source_root'] . '/' . $file['source_relative']] = $file['absolute'];
        }
        $code = (string) ($manifest['code'] ?? '');
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (!in_array($surface, self::surfaces($manifest), true)) {
                continue;
            }
            $required = ['index.js'];
            if ($surface === 'mobile') {
                $required = array_merge($required, ['api/' . $code . '.js', 'pages/index/index.vue', 'pages/detail/index.vue']);
            } else {
                $required = array_merge($required, ['routes.js', 'api/' . $code . '.js']);
            }
            foreach ($required as $relative) {
                if (!isset($byRoot[$surface . '/' . $relative])) {
                    $bag->error($surface . ' 端缺少文件：' . $relative);
                }
            }
            if ($surface !== 'mobile' && !array_filter(array_keys($byRoot), static fn(string $path): bool =>
                str_starts_with($path, $surface . '/views/') && str_ends_with($path, '.vue'))) {
                $bag->error($surface . ' 端缺少 views 页面');
            }
            $index = $byRoot[$surface . '/index.js'] ?? null;
            if ($index !== null) {
                $contents = @file_get_contents($index);
                if ($contents === false || !preg_match('/\b(register|pluginCode)\b/', $contents)) {
                    $bag->error($surface . ' 端 index.js 必须提供注册入口');
                }
                $events = $manifest['events']['frontend_consumes'][$surface] ?? [];
                if (is_array($events)) {
                    foreach ($events as $event) {
                        if (is_string($event) && !str_contains($contents ?: '', $event)) {
                            $bag->warning($surface . ' 端入口未直接出现声明事件：' . $event);
                        }
                    }
                }
            }
        }
    }

    /**
     * 检查开发初始状态为未安装且未启用，不允许预填安装或迁移版本。
     *
     * @param string $root 已经确认的仓库或操作根目录。
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param array $sourceRoots 开发清单中的端侧和资源源码根目录映射。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateInitialState(string $root, array $manifest, array $sourceRoots, PluginValidationBag $bag): void
    {
        if (!isset($sourceRoots['resources']) || !is_string($sourceRoots['resources'])) {
            return;
        }
        try {
            $resources = PluginTool::resolveRelative($root, $sourceRoots['resources'], true);
            $state = PluginTool::readJson($resources . DIRECTORY_SEPARATOR . 'initial-state.json', 'resources/initial-state.json');
            if (($state['schema_version'] ?? null) !== PluginTool::SCHEMA_VERSION) {
                $bag->error('initial-state.json.schema_version 必须为 ' . PluginTool::SCHEMA_VERSION);
            }
            if (($state['plugin_code'] ?? null) !== ($manifest['code'] ?? null)) {
                $bag->error('initial-state.json.plugin_code 必须与清单一致');
            }
            if (($state['lifecycle_status'] ?? null) !== 'uninstalled' || ($state['enabled'] ?? null) !== false) {
                $bag->error('初始化状态必须是 uninstalled 且 enabled=false');
            }
            foreach (['installed_version', 'migration_version'] as $key) {
                if (!array_key_exists($key, $state) || $state[$key] !== null) {
                    $bag->error('initial-state.json.' . $key . ' 必须为 null');
                }
            }
        } catch (PluginToolException $exception) {
            $bag->error($exception->getMessage());
        }
    }

    /**
     * 使用当前 PHP 解释器逐文件检查语法，只解析源码而不执行其业务代码。
     *
     * @param array<int,array{source_root:string,package_root:string,absolute:string,relative:string,source_relative:string,size:int}> $files 已归集的源码文件或源码包文件清单。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function lintPhpFiles(array $files, PluginValidationBag $bag): void
    {
        foreach ($files as $file) {
            if (!str_ends_with(strtolower($file['absolute']), '.php')) {
                continue;
            }
            $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = @proc_open([PHP_BINARY, '-l', $file['absolute']], $descriptor, $pipes);
            if (!is_resource($process)) {
                $bag->warning('无法启动 PHP 语法检查：' . $file['relative']);
                continue;
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            if ($status !== 0) {
                $detail = trim((string) $stderr . ' ' . (string) $stdout);
                $bag->error('PHP 语法检查失败：' . $file['relative'] . ($detail !== '' ? '（' . $detail . '）' : ''));
            }
        }
    }

    /**
     * 检查规范化的未签名源码包目录，核对资源、代码、校验和及语法。
     *
     * @param string $packageRoot 待生成或待校验的独立源码包目录。
     * @return array{manifest:array<string,mixed>,files:array<int,array{path:string,absolute:string,size:int}>,warnings:array<int,string>} 已校验的源码包清单、包内文件列表和非阻断警告。
     */
    public static function packageDirectory(string $packageRoot): array
    {
        $bag = new PluginValidationBag();
        if (!is_dir($packageRoot) || is_link($packageRoot)) {
            throw new PluginToolException('源码包目录不存在或是软链接：' . $packageRoot);
        }
        $manifest = PluginTool::readJson($packageRoot . DIRECTORY_SEPARATOR . 'plugin.json', '源码包 plugin.json');
        self::validateManifest($manifest, $bag);
        self::validateManifestPaths($manifest, $bag);
        $allowedTop = ['plugin.json' => true, 'resources' => true, 'backend' => true, 'frontend' => true, 'tests' => true, 'checksums.json' => true];
        foreach (new FilesystemIterator($packageRoot, FilesystemIterator::SKIP_DOTS) as $item) {
            if (!isset($allowedTop[$item->getFilename()])) {
                $bag->error('源码包根目录包含未声明项：' . $item->getFilename());
            }
            if ($item->isLink()) {
                $bag->error('源码包不允许软链接：' . $item->getPathname());
            }
        }
        $inventory = PluginTool::listFiles($packageRoot, $packageRoot);
        $files = [];
        $set = [];
        $lowerSet = [];
        $total = 0;
        foreach ($inventory as $file) {
            $path = $file['relative'];
            $files[] = ['path' => $path, 'absolute' => $file['absolute'], 'size' => $file['size']];
            $set[$path] = true;
            $lower = strtolower($path);
            if (isset($lowerSet[$lower])) {
                $bag->error('源码包路径大小写冲突：' . $path);
            }
            $lowerSet[$lower] = true;
            $total += $file['size'];
            if ($path === 'plugin.dev.json') {
                $bag->error('源码包不得包含 plugin.dev.json');
            }
            if ($path !== 'checksums.json' && self::forbidden($path)) {
                $bag->error('源码包包含敏感或构建文件：' . $path);
            }
        }
        if (count($files) > PluginTool::MAX_FILE_COUNT) {
            $bag->error('源码包文件数量超过上限 ' . PluginTool::MAX_FILE_COUNT);
        }
        if ($total > PluginTool::MAX_TOTAL_BYTES) {
            $bag->error('源码包总大小超过上限 ' . PluginTool::MAX_TOTAL_BYTES . ' 字节');
        }
        $code = (string) ($manifest['code'] ?? '');
        $required = ['resources/initial-state.json'];
        $surfaces = self::surfaces($manifest);
        if (in_array('backend', $surfaces, true)) {
            $required[] = 'backend/__directory__';
        }
        if (in_array('admin', $surfaces, true)) {
            $required = array_merge($required, ['frontend/admin/index.js', 'frontend/admin/routes.js', 'frontend/admin/api/' . $code . '.js']);
        }
        if (in_array('merchant', $surfaces, true)) {
            $required = array_merge($required, ['frontend/merchant/index.js', 'frontend/merchant/routes.js', 'frontend/merchant/api/' . $code . '.js']);
        }
        if (in_array('mobile', $surfaces, true)) {
            $required = array_merge($required, ['frontend/mobile/index.js', 'frontend/mobile/api/' . $code . '.js', 'frontend/mobile/pages/index/index.vue', 'frontend/mobile/pages/detail/index.vue']);
        }
        foreach ($required as $path) {
            if ($path === 'backend/__directory__') {
                if (!self::hasPrefix($set, 'backend/')) {
                    $bag->error('源码包缺少 backend 内容');
                }
            } elseif (!isset($set[$path])) {
                $bag->error('源码包缺少文件：' . $path);
            }
        }
        foreach (['admin', 'merchant'] as $surface) {
            if (in_array($surface, $surfaces, true) && !array_filter(array_keys($set), static fn(string $path): bool =>
                str_starts_with($path, 'frontend/' . $surface . '/views/') && str_ends_with($path, '.vue'))) {
                $bag->error('源码包缺少 frontend/' . $surface . '/views 页面');
            }
        }
        if (!self::hasPrefix($set, 'tests/')) {
            $bag->error('源码包必须包含 tests');
        }
        self::validatePackageCode($manifest, $files, $bag);
        self::validateChecksums($packageRoot, $manifest, $files, $bag);
        $lintFiles = [];
        foreach ($files as $file) {
            $lintFiles[] = [
                'source_root' => 'package',
                'package_root' => '',
                'absolute' => $file['absolute'],
                'relative' => $file['path'],
                'source_relative' => $file['path'],
                'size' => $file['size'],
            ];
        }
        self::lintPhpFiles($lintFiles, $bag);
        $bag->assertValid();
        return ['manifest' => $manifest, 'files' => $files, 'warnings' => $bag->warnings];
    }

    /**
     * 判断路径集合中是否存在指定目录前缀的文件。
     *
     * @param array<string,bool> $set 以包内路径为键的已归集文件集合。
     * @param string $prefix 需要匹配的目录前缀。
     * @return bool 是否有文件路径以指定目录前缀开头。
     */
    private static function hasPrefix(array $set, string $prefix): bool
    {
        foreach (array_keys($set) as $path) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 按包内布局校验后端命名空间和声明端侧的原生路由文件。
     *
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param array<int,array{path:string,absolute:string,size:int}> $files 已归集的包内路径、绝对路径和文件大小清单。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validatePackageCode(array $manifest, array $files, PluginValidationBag $bag): void
    {
        $code = (string) ($manifest['code'] ?? '');
        $namespace = PluginTool::namespaceFor($code);
        $routeFiles = [];
        foreach ($files as $file) {
            $path = $file['path'];
            if (str_starts_with($path, 'backend/src/') && str_ends_with($path, '.php')) {
                $contents = @file_get_contents($file['absolute']);
                if ($contents === false || !preg_match('/^\s*namespace\s+([^;]+);/mi', $contents, $matches)) {
                    $bag->error('backend/src PHP 文件必须声明命名空间：' . $path);
                } elseif (trim($matches[1]) !== $namespace && !str_starts_with(trim($matches[1]), $namespace . '\\')) {
                    $bag->error('PHP 命名空间不属于 ' . $namespace . '：' . $path);
                }
            }
            if (str_starts_with($path, 'backend/routes/') && str_ends_with($path, '.php')) {
                $routeFiles[$path] = $file['absolute'];
            }
        }
        foreach (['admin', 'merchant', 'mobile'] as $surface) {
            if (!in_array($surface, self::surfaces($manifest), true)) {
                continue;
            }
            $route = 'backend/routes/' . $surface . '.php';
            if (!isset($routeFiles[$route])) {
                $bag->error('源码包缺少路由文件：' . $route);
                continue;
            }
            self::validateNativeRouteFile($routeFiles[$route], $route, $surface, $code, $bag);
        }
    }

    /**
     * 校验包内清单身份与每个源码文件的摘要一致，不将摘要视为制品签名。
     *
     * @param string $packageRoot 待生成或待校验的独立源码包目录。
     * @param array $manifest 插件清单中的身份、端侧和资源声明。
     * @param array<int,array{path:string,absolute:string,size:int}> $files 已归集的包内文件及摘要计算所需的绝对路径。
     * @param PluginValidationBag $bag 共享的错误与警告收集器。
     * @return void 无返回值。
     */
    private static function validateChecksums(string $packageRoot, array $manifest, array $files, PluginValidationBag $bag): void
    {
        try {
            $checksum = PluginTool::readJson($packageRoot . DIRECTORY_SEPARATOR . 'checksums.json', 'checksums.json');
        } catch (PluginToolException $exception) {
            $bag->error($exception->getMessage());
            return;
        }
        if (($checksum['schema_version'] ?? null) !== PluginTool::SCHEMA_VERSION || ($checksum['plugin_code'] ?? null) !== ($manifest['code'] ?? null) || ($checksum['plugin_version'] ?? null) !== ($manifest['version'] ?? null)) {
            $bag->error('checksums.json 元数据与 plugin.json 不一致');
        }
        $actual = [];
        foreach ($files as $file) {
            if ($file['path'] !== 'checksums.json') {
                $actual[$file['path']] = hash_file('sha256', $file['absolute']);
            }
        }
        // 生成器使用同一排序规则，严格比较可发现缺失、额外或被修改的文件。
        ksort($actual, SORT_STRING);
        if (($checksum['files'] ?? null) !== $actual) {
            $bag->error('checksums.json 与源码文件 SHA-256 不一致');
        }
    }

    /**
     * 将 ZIP 视为不可信输入，拒绝越界、重复或软链接条目后隔离解包并校验源码目录。
     *
     * @param string $archive 待按不可信输入校验的 ZIP 源码包路径。
     * @return array{manifest:array<string,mixed>,files:array<int,array{path:string,absolute:string,size:int}>,warnings:array<int,string>} 隔离解包后校验的清单、文件元数据和警告；临时文件随后清理。
     */
    public static function packageArchive(string $archive): array
    {
        if (!is_file($archive) || is_link($archive)) {
            throw new PluginToolException('源码包不存在或是软链接：' . $archive);
        }
        $zip = new ZipArchive();
        $opened = $zip->open($archive, ZipArchive::CHECKCONS);
        if ($opened !== true) {
            throw new PluginToolException('无法打开 ZIP（错误码 ' . (string) $opened . '）');
        }
        $temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.plugin-validate-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700, true)) {
            $zip->close();
            throw new PluginToolException('无法创建 ZIP 校验临时目录');
        }
        $closed = false;
        try {
            $seen = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || !isset($stat['name'])) {
                    throw new PluginToolException('ZIP 条目元数据无效');
                }
                $name = (string) $stat['name'];
                $directory = str_ends_with($name, '/');
                $trimmed = rtrim($name, '/');
                if ($trimmed === '') {
                    continue;
                }
                $normalized = PluginTool::normalizeRelative($trimmed);
                $key = strtolower($normalized);
                if (isset($seen[$key])) {
                    throw new PluginToolException('ZIP 条目重复或大小写冲突：' . $name);
                }
                $seen[$key] = true;
                if ($normalized === 'plugin.dev.json') {
                    throw new PluginToolException('ZIP 不得包含 plugin.dev.json');
                }
                // 检查 ZIP 的 Unix 文件类型位，不允许解包生成软链接。
                $opsys = 0;
                $attributes = 0;
                if (method_exists($zip, 'getExternalAttributesIndex') && $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
                    $type = ($attributes >> 16) & 0170000;
                    if ($type === 0120000) {
                        throw new PluginToolException('ZIP 不允许软链接条目：' . $name);
                    }
                }
                $destination = $temporary . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
                if ($directory) {
                    if (!mkdir($destination, 0700, true) && !is_dir($destination)) {
                        throw new PluginToolException('无法创建 ZIP 目录条目');
                    }
                    continue;
                }
                $parent = dirname($destination);
                if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
                    throw new PluginToolException('无法创建 ZIP 条目目录');
                }
                $contents = $zip->getFromIndex($index);
                if ($contents === false || file_put_contents($destination, $contents, LOCK_EX) === false) {
                    throw new PluginToolException('无法解包 ZIP 条目：' . $name);
                }
                chmod($destination, 0600);
            }
            $zip->close();
            $closed = true;
            return self::packageDirectory($temporary);
        } finally {
            if (!$closed) {
                $zip->close();
            }
            if (is_dir($temporary)) {
                PluginTool::removeTree($temporary, dirname($temporary));
            }
        }
    }
}
