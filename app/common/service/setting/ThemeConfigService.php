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

namespace app\common\service\setting;

use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\setting\system\Config;
use app\common\service\BaseService;

/**
 * 移动端主题换色配置服务。
 */
class ThemeConfigService extends BaseService
{
    private const DEFAULT_THEME_KEY = 'orange';

    private const DEFAULT_CONFIG = [
        'theme_key' => self::DEFAULT_THEME_KEY,
        'primary_color' => '#ea580c',
        'secondary_color' => '#fb923c',
        'gradient_color' => 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)',
    ];

    private const PRESETS = [
        'orange' => [
            'name' => '活力橙',
            'description' => '当前默认主题，适合商城促销和高转化按钮。',
            'primary_color' => '#ea580c',
            'secondary_color' => '#fb923c',
            'gradient_color' => 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)',
        ],
        'blue' => [
            'name' => '信任蓝',
            'description' => '适合知识付费和工具类场景，视觉更稳。',
            'primary_color' => '#2563eb',
            'secondary_color' => '#60a5fa',
            'gradient_color' => 'linear-gradient(135deg, #2563eb 0%, #60a5fa 100%)',
        ],
        'green' => [
            'name' => '生长绿',
            'description' => '适合教育、健康、成长类内容场景。',
            'primary_color' => '#16a34a',
            'secondary_color' => '#4ade80',
            'gradient_color' => 'linear-gradient(135deg, #16a34a 0%, #4ade80 100%)',
        ],
        'rose' => [
            'name' => '柔和玫红',
            'description' => '适合美妆、生活方式和女性消费场景。',
            'primary_color' => '#e11d48',
            'secondary_color' => '#fb7185',
            'gradient_color' => 'linear-gradient(135deg, #e11d48 0%, #fb7185 100%)',
        ],
        'slate' => [
            'name' => '高级黑',
            'description' => '适合品牌感强、视觉克制的内容与会员场景。',
            'primary_color' => '#18181b',
            'secondary_color' => '#52525b',
            'gradient_color' => 'linear-gradient(135deg, #18181b 0%, #52525b 100%)',
        ],
    ];

    /**
     * 获取后台主题配置详情。
     *
     * @return array<string,mixed>
     */
    public function detail(): array
    {
        return [
            'config' => $this->getStoredConfig(),
            'presets' => $this->presetOptions(),
            'color_types' => $this->colorTypes(),
            'color_count' => count($this->colorTypes()),
        ];
    }

    /**
     * 保存后台选择的主题配置。
     *
     * @param array<string,mixed> $data 主题配置。
     * @return array<string,mixed>
     */
    public function save(array $data): array
    {
        $config = $this->normalizeConfig($data);
        app()->make(ConfigService::class)->saveKeyValues(
            [ConfigKeyEnum::SITE_THEME_CONFIG => $config],
            0,
            [
                ConfigKeyEnum::SITE_THEME_CONFIG => [
                    'title' => '移动端主题换色配置',
                    'into' => '后台主题配置页面维护的移动端主色、辅助色和渐变色',
                    'sort' => 410,
                    'status' => 1,
                ],
            ]
        );

        return $this->detail();
    }

    /**
     * 获取移动端公开主题配置。
     *
     * @return array<string,mixed>
     */
    public function publicConfig(): array
    {
        $config = $this->getStoredConfig();

        return [
            'theme_key' => $config['theme_key'],
            'theme_name' => $config['theme_name'],
            'primary_color' => $config['primary_color'],
            'secondary_color' => $config['secondary_color'],
            'gradient_color' => $config['gradient_color'],
            'color_count' => count($this->colorTypes()),
        ];
    }

    /**
     * 获取已保存配置，不存在时返回默认主题。
     *
     * @return array<string,mixed>
     */
    protected function getStoredConfig(): array
    {
        $stored = app()->make(ConfigService::class)->getValue(ConfigKeyEnum::SITE_THEME_CONFIG);
        return $this->normalizeConfig(is_array($stored) ? $stored : [], false);
    }

    /**
     * 标准化主题配置。
     *
     * @param array<string,mixed> $data 待处理配置。
     * @param bool $strict 是否严格校验预设。
     * @return array<string,mixed>
     */
    protected function normalizeConfig(array $data, bool $strict = true): array
    {
        $themeKey = (string)($data['theme_key'] ?? self::DEFAULT_CONFIG['theme_key']);
        if (!isset(self::PRESETS[$themeKey])) {
            if ($strict) {
                throw new AdminResponseException('主题预设不存在');
            }
            $themeKey = self::DEFAULT_THEME_KEY;
        }

        $preset = self::PRESETS[$themeKey];
        $primaryColor = $this->normalizeHexColor(
            (string)($data['primary_color'] ?? $preset['primary_color']),
            $preset['primary_color']
        );
        $secondaryColor = $this->normalizeHexColor(
            (string)($data['secondary_color'] ?? $preset['secondary_color']),
            $preset['secondary_color']
        );
        $gradientColor = $this->normalizeGradient(
            (string)($data['gradient_color'] ?? $preset['gradient_color']),
            $preset['gradient_color']
        );

        return [
            'theme_key' => $themeKey,
            'theme_name' => $preset['name'],
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'gradient_color' => $gradientColor,
        ];
    }

    /**
     * 获取主题预设选项。
     *
     * @return array<int,array<string,string>>
     */
    protected function presetOptions(): array
    {
        $result = [];
        foreach (self::PRESETS as $key => $preset) {
            $result[] = [
                'key' => $key,
                'name' => $preset['name'],
                'description' => $preset['description'],
                'primary_color' => $preset['primary_color'],
                'secondary_color' => $preset['secondary_color'],
                'gradient_color' => $preset['gradient_color'],
            ];
        }

        return $result;
    }

    /**
     * 获取后台展示用颜色类型。
     *
     * @return array<int,array<string,string>>
     */
    protected function colorTypes(): array
    {
        return [
            ['key' => 'primary_color', 'label' => '主色'],
            ['key' => 'secondary_color', 'label' => '辅助色'],
            ['key' => 'gradient_color', 'label' => '渐变色'],
        ];
    }

    /**
     * 标准化十六进制颜色。
     */
    protected function normalizeHexColor(string $color, string $default): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }
        if (preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
            return '#' . strtolower($color);
        }

        return $default;
    }

    /**
     * 标准化渐变色，只允许安全的 CSS linear-gradient 字符串。
     */
    protected function normalizeGradient(string $gradient, string $default): string
    {
        $gradient = trim($gradient);
        if (preg_match('/^linear-gradient\([#a-zA-Z0-9\s,%.()-]+\)$/', $gradient)) {
            return $gradient;
        }

        return $default;
    }

    /**
     * 设置对应模型。
     */
    protected function setModel(): string
    {
        return Config::class;
    }
}
