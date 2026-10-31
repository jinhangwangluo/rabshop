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

namespace app\common\listener\swoole;

/**
 * Swoole 启动信息输出。
 */
final class SwooleInitListener
{
    private const LINE_WIDTH = 66;

    /**
     * 处理 Swoole 初始化事件。
     *
     * @param mixed $params 事件参数
     * @return void
     */
    public function handle($params = null): void
    {
        if (PHP_SAPI !== 'cli' || !defined('STDOUT')) {
            return;
        }

        $swooleConfig = app()->config->get('swoole', []);
        $appHost = rtrim((string) app()->config->get('app.app_host', ''), '/');
        $httpConfig = $swooleConfig['http'] ?? [];
        $websocketConfig = $swooleConfig['websocket'] ?? [];
        $rpcConfig = $swooleConfig['rpc']['server'] ?? [];
        $queueConfig = $swooleConfig['queue'] ?? [];
        $hotUpdateConfig = $swooleConfig['hot_update'] ?? [];
        $poolConfig = $swooleConfig['pool'] ?? [];

        fwrite(STDOUT, $this->buildOutput(
            $swooleConfig,
            $appHost,
            $httpConfig,
            $websocketConfig,
            $rpcConfig,
            $queueConfig,
            $hotUpdateConfig,
            $poolConfig,
            $this->supportColor()
        ));
    }

    /**
     * 构建终端输出内容。
     *
     * @param array $swooleConfig Swoole 完整配置
     * @param string $appHost 应用配置地址
     * @param array $httpConfig HTTP 配置
     * @param array $websocketConfig WebSocket 配置
     * @param array $rpcConfig RPC 服务端配置
     * @param array $queueConfig 队列配置
     * @param array $hotUpdateConfig 热更新配置
     * @param array $poolConfig 连接池配置
     * @param bool $withColor 是否输出颜色
     * @return string
     */
    private function buildOutput(
        array $swooleConfig,
        string $appHost,
        array $httpConfig,
        array $websocketConfig,
        array $rpcConfig,
        array $queueConfig,
        array $hotUpdateConfig,
        array $poolConfig,
        bool $withColor
    ): string {
        $host = (string) ($httpConfig['host'] ?? '0.0.0.0');
        $port = (int) ($httpConfig['port'] ?? 80);
        $displayHost = $this->normalizeDisplayHost($host);
        $baseUrl = $this->buildHttpUrl($displayHost, $port);
        $listenUrl = $this->buildHttpUrl($host, $port);
        $websocketUrl = $this->buildWebsocketUrl($displayHost, $port);

        $lines = [
            '',
            $this->color('+' . str_repeat('-', self::LINE_WIDTH) . '+', 'cyan', $withColor),
            $this->color($this->headerLine('Swoole Server Ready'), 'cyan', $withColor),
            $this->color('+' . str_repeat('-', self::LINE_WIDTH) . '+', 'cyan', $withColor),
            '',
            $this->section('访问地址', $withColor),
        ];

        if ($appHost !== '') {
            $lines[] = $this->item('应用地址', $appHost, $withColor, 'green');
        } else {
            $lines[] = $this->item('应用地址', '未配置 app.app_host', $withColor, 'yellow');
        }

        $lines[] = $this->item('本机地址', $baseUrl, $withColor, 'green');

        if ($listenUrl !== $baseUrl) {
            $lines[] = $this->item('监听地址', $listenUrl, $withColor, 'green');
        }

        if ($this->isEnabled($websocketConfig)) {
            $lines[] = $this->item('WebSocket', $websocketUrl . '/ws', $withColor, 'green');
        }

        $lines = array_merge($lines, [
            '',
            $this->section('服务状态', $withColor),
            $this->serviceLine('HTTP', $httpConfig, [
                'listen' => $host . ':' . $port,
                'workers' => $httpConfig['worker_num'] ?? '',
            ], $withColor),
            $this->serviceLine('WebSocket', $websocketConfig, [
                'room' => $websocketConfig['room']['type'] ?? '',
                'ping' => ($websocketConfig['ping_interval'] ?? '-') . '/' . ($websocketConfig['ping_timeout'] ?? '-'),
            ], $withColor),
            $this->serviceLine('RPC Server', $rpcConfig, [
                'listen' => ($rpcConfig['host'] ?? '-') . ':' . ($rpcConfig['port'] ?? '-'),
                'workers' => $rpcConfig['worker_num'] ?? '',
            ], $withColor),
            $this->serviceLine('Queue', $queueConfig, [
                'workers' => implode(',', array_keys((array) ($queueConfig['workers'] ?? []))),
                'console_log' => (bool) ($queueConfig['console_log']['enable'] ?? false),
            ], $withColor),
            $this->serviceLine('Hot Update', $hotUpdateConfig, [
                'include' => $this->formatList((array) ($hotUpdateConfig['include'] ?? [])),
            ], $withColor),
            '',
            $this->section('连接与扩展', $withColor),
            $this->item('IPC', (string) ($swooleConfig['ipc']['type'] ?? '-'), $withColor),
        ]);

        $lines = array_merge(
            $lines,
            $this->buildHttpOptionLines((array) ($httpConfig['options'] ?? []), $withColor),
            $this->buildPoolLines($poolConfig, $withColor),
            [
                $this->color('+' . str_repeat('-', self::LINE_WIDTH) . '+', 'cyan', $withColor),
                '',
            ]
        );

        return implode(PHP_EOL, $lines);
    }

    /**
     * 构建 HTTP 配置行。
     *
     * @param array $options HTTP options
     * @param bool $withColor 是否输出颜色
     * @return array
     */
    private function buildHttpOptionLines(array $options, bool $withColor): array
    {
        if ($options === []) {
            return [];
        }

        $lines = [
            '',
            $this->section('HTTP Options', $withColor),
        ];

        foreach ($options as $key => $value) {
            $lines[] = $this->item((string) $key, $this->formatValue($value), $withColor);
        }

        return $lines;
    }

    /**
     * 构建连接池配置行。
     *
     * @param array $poolConfig 连接池配置
     * @param bool $withColor 是否输出颜色
     * @return array
     */
    private function buildPoolLines(array $poolConfig, bool $withColor): array
    {
        if ($poolConfig === []) {
            return [];
        }

        $lines = [
            '',
            $this->section('Pool', $withColor),
        ];

        foreach ($poolConfig as $name => $config) {
            $config = (array) $config;
            $lines[] = $this->serviceLine('Pool ' . $name, $config, [
                'max_active' => $config['max_active'] ?? '',
                'max_wait_time' => $config['max_wait_time'] ?? '',
            ], $withColor);
        }

        return $lines;
    }

    /**
     * 输出标题行。
     *
     * @param string $title 标题
     * @return string
     */
    private function headerLine(string $title): string
    {
        return '| ' . str_pad($title, self::LINE_WIDTH - 1) . '|';
    }

    /**
     * 将不可直接访问的监听地址转换成本机访问地址。
     *
     * @param string $host 监听主机
     * @return string
     */
    private function normalizeDisplayHost(string $host): string
    {
        return in_array($host, ['0.0.0.0', '::'], true) ? '127.0.0.1' : $host;
    }

    /**
     * 构建 HTTP 地址。
     *
     * @param string $host 主机
     * @param int $port 端口
     * @return string
     */
    private function buildHttpUrl(string $host, int $port): string
    {
        return 'http://' . $this->formatHost($host) . ':' . $port;
    }

    /**
     * 构建 WebSocket 地址。
     *
     * @param string $host 主机
     * @param int $port 端口
     * @return string
     */
    private function buildWebsocketUrl(string $host, int $port): string
    {
        return 'ws://' . $this->formatHost($host) . ':' . $port;
    }

    /**
     * 格式化主机，兼容 IPv6。
     *
     * @param string $host 主机
     * @return string
     */
    private function formatHost(string $host): string
    {
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }

        return $host;
    }

    /**
     * 判断配置是否启用。
     *
     * @param array $config 配置
     * @return bool
     */
    private function isEnabled(array $config): bool
    {
        return (bool) ($config['enable'] ?? false);
    }

    /**
     * 输出分区标题。
     *
     * @param string $title 标题
     * @param bool $withColor 是否输出颜色
     * @return string
     */
    private function section(string $title, bool $withColor): string
    {
        return $this->color('[' . $title . ']', 'blue', $withColor);
    }

    /**
     * 输出普通项目行。
     *
     * @param string $label 标签
     * @param string $value 值
     * @param bool $withColor 是否输出颜色
     * @param string|null $valueColor 值颜色
     * @return string
     */
    private function item(string $label, string $value, bool $withColor, ?string $valueColor = null): string
    {
        return sprintf(
            '  %-14s %s',
            $label,
            $valueColor === null ? $value : $this->color($value, $valueColor, $withColor)
        );
    }

    /**
     * 输出服务状态行。
     *
     * @param string $name 服务名称
     * @param array $config 服务配置
     * @param array $pairs 追加键值
     * @param bool $withColor 是否输出颜色
     * @return string
     */
    private function serviceLine(string $name, array $config, array $pairs, bool $withColor): string
    {
        return sprintf(
            '  %s %-12s %s',
            $this->statusBadge($config, $withColor),
            $name,
            $this->formatPairs($pairs)
        );
    }

    /**
     * 输出状态标记。
     *
     * @param array $config 服务配置
     * @param bool $withColor 是否输出颜色
     * @return string
     */
    private function statusBadge(array $config, bool $withColor): string
    {
        if ($this->isEnabled($config)) {
            return $this->color('[ON ]', 'green', $withColor);
        }

        return $this->color('[OFF]', 'yellow', $withColor);
    }

    /**
     * 格式化键值对。
     *
     * @param array $pairs 键值对
     * @return string
     */
    private function formatPairs(array $pairs): string
    {
        $items = [];

        foreach ($pairs as $key => $value) {
            if ($value === '' || $value === []) {
                continue;
            }

            $items[] = $key . '=' . $this->formatValue($value);
        }

        return implode('  ', $items);
    }

    /**
     * 格式化列表。
     *
     * @param array $items 列表
     * @return string
     */
    private function formatList(array $items): string
    {
        $items = array_filter(array_map('strval', $items), static fn (string $item): bool => $item !== '');

        return $items === [] ? '-' : implode(',', $items);
    }

    /**
     * 格式化配置值。
     *
     * @param mixed $value 配置值
     * @return string
     */
    private function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 终端是否支持颜色。
     *
     * @return bool
     */
    private function supportColor(): bool
    {
        return getenv('NO_COLOR') === false
            && function_exists('stream_isatty')
            && stream_isatty(STDOUT);
    }

    /**
     * 输出颜色。
     *
     * @param string $text 文本
     * @param string $color 颜色名
     * @param bool $enabled 是否启用颜色
     * @return string
     */
    private function color(string $text, string $color, bool $enabled): string
    {
        if (!$enabled) {
            return $text;
        }

        $colors = [
            'blue' => '1;34',
            'cyan' => '1;36',
            'green' => '1;32',
            'yellow' => '1;33',
        ];

        return "\033[" . ($colors[$color] ?? '0') . 'm' . $text . "\033[0m";
    }
}
