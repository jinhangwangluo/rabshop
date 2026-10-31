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

use app\common\enum\ConfigKeyEnum;
use think\migration\Migrator;

/**
 * 写入内容展示配置及后台配置模板。
 */
class SeedContentDisplayConfig extends Migrator
{
    /**
     * 内容展示配置模板标识。
     */
    private const TEMPLATE_KEY = 'content_display_config';

    /**
     * 写入默认配置。
     */
    public function change(): void
    {
        $prefix = config('database.connections.' . config('database.default') . '.prefix');
        $now = date('Y-m-d H:i:s');

        $configs = [
            [
                'key' => ConfigKeyEnum::CONTENT_DISPLAY_CLOSED,
                'title' => '关闭内容',
                'into' => '开启后移动端内容列表、详情、搜索和 DIY 内容模块不展示内容。',
                'sort' => 412,
                'value' => 0,
            ],
            [
                'key' => ConfigKeyEnum::SPECIAL_DISPLAY_CLOSED,
                'title' => '关闭专题',
                'into' => '开启后移动端专题列表、详情、搜索和 DIY 专题模块不展示专题。',
                'sort' => 413,
                'value' => 0,
            ],
        ];

        foreach ($configs as $config) {
            $this->insertConfigIfMissing($prefix, $now, $config);
        }

        $content = [
            $this->switchField(
                'content_display_closed',
                '关闭内容',
                ConfigKeyEnum::CONTENT_DISPLAY_CLOSED,
                '开启后移动端内容接口返回空数据，DIY 后台不能新增内容组件，已装修内容模块在移动端隐藏。'
            ),
            $this->switchField(
                'special_display_closed',
                '关闭专题',
                ConfigKeyEnum::SPECIAL_DISPLAY_CLOSED,
                '开启后移动端专题接口返回空数据，DIY 后台不能新增专题组件，已装修专题模块在移动端隐藏。'
            ),
        ];
        $fieldKeys = [
            ConfigKeyEnum::CONTENT_DISPLAY_CLOSED,
            ConfigKeyEnum::SPECIAL_DISPLAY_CLOSED,
        ];
        $fields = [
            ['field' => ConfigKeyEnum::CONTENT_DISPLAY_CLOSED, 'label' => '关闭内容', 'type' => 'switch'],
            ['field' => ConfigKeyEnum::SPECIAL_DISPLAY_CLOSED, 'label' => '关闭专题', 'type' => 'switch'],
        ];

        $this->insertTemplateIfMissing($prefix, $now, $content, $fieldKeys, $fields);
    }

    /**
     * 插入缺失配置项。
     *
     * @param string $prefix 表前缀。
     * @param string $now 当前时间。
     * @param array<string, mixed> $config 配置元数据。
     */
    private function insertConfigIfMissing(string $prefix, string $now, array $config): void
    {
        $key = addslashes((string)$config['key']);
        $title = addslashes((string)$config['title']);
        $into = addslashes((string)$config['into']);
        $sort = (int)$config['sort'];
        $value = addslashes(json_encode($config['value'], JSON_UNESCAPED_UNICODE));

        $this->execute(
            "INSERT INTO `{$prefix}config` "
            . "(`key_name`, `cate_id`, `value`, `title`, `into`, `sort`, `status`, `create_time`, `update_time`) "
            . "SELECT '{$key}', 0, '{$value}', '{$title}', '{$into}', {$sort}, 1, '{$now}', '{$now}' "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config` WHERE `key_name` = '{$key}' LIMIT 1)"
        );
    }

    /**
     * 插入缺失配置模板。
     *
     * @param string $prefix 表前缀。
     * @param string $now 当前时间。
     * @param array<int, array<string, mixed>> $content 表单内容。
     * @param array<int, string> $fieldKeys 字段键。
     * @param array<int, array<string, string>> $fields 预定义字段。
     */
    private function insertTemplateIfMissing(
        string $prefix,
        string $now,
        array $content,
        array $fieldKeys,
        array $fields
    ): void {
        $templateKey = addslashes(self::TEMPLATE_KEY);
        $configName = addslashes('内容设置');
        $configDesc = addslashes('控制移动端内容和专题是否展示');
        $contentJson = addslashes(json_encode($content, JSON_UNESCAPED_UNICODE));
        $fieldKeysJson = addslashes(json_encode($fieldKeys, JSON_UNESCAPED_UNICODE));
        $fieldsJson = addslashes(json_encode($fields, JSON_UNESCAPED_UNICODE));

        $this->execute(
            "INSERT INTO `{$prefix}config_template` "
            . "(`cate_id`, `admin_id`, `menu_id`, `admin_name`, `config_name`, `config_desc`, `config_key`, "
            . "`content`, `field_keys`, `fields`, `status`, `create_time`, `update_time`) "
            . "SELECT 0, 0, 0, '', '{$configName}', '{$configDesc}', '{$templateKey}', "
            . "'{$contentJson}', '{$fieldKeysJson}', '{$fieldsJson}', 1, '{$now}', '{$now}' "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config_template` WHERE `config_key` = '{$templateKey}' LIMIT 1)"
        );
    }

    /**
     * 构造动态配置开关字段。
     */
    private function switchField(string $id, string $label, string $field, string $description): array
    {
        return [
            '_id' => $id,
            'type' => 'switch',
            'label' => $label,
            'props' => [
                'field' => $field,
                'label' => $label,
                'activeText' => '关闭',
                'inactiveText' => '开启',
                'activeValue' => 1,
                'inactiveValue' => 0,
                'required' => false,
                'disabled' => false,
                'defaultValue' => 0,
                'description' => $description,
            ],
        ];
    }
}
