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
 * 写入商品采集配置及其后台表单模板。
 */
class SeedProductCollectorConfig extends Migrator
{
    /**
     * OneBound 商品采集配置模板标识。
     */
    private const TEMPLATE_KEY = 'product_collector_config';

    /**
     * 写入配置。
     *
     * @return void
     */
    public function change(): void
    {
        $prefix = config('database.connections.' . config('database.default') . '.prefix');
        $now = date('Y-m-d H:i:s');

        $configs = [
            [
                'key' => ConfigKeyEnum::PRODUCT_COLLECTOR_ENABLED,
                'title' => '是否开启商品采集',
                'into' => '控制后台商品采集功能是否可用，默认关闭。',
                'sort' => 389,
                'value' => 0,
            ],
            [
                'key' => ConfigKeyEnum::PRODUCT_COLLECTOR_KEY,
                'title' => '商品采集调用 Key',
                'into' => 'OneBound 商品采集接口的调用 Key，服务端请求时以 GET 参数发送。',
                'sort' => 390,
                'value' => '',
            ],
            [
                'key' => ConfigKeyEnum::PRODUCT_COLLECTOR_SECRET,
                'title' => '商品采集调用 Secret',
                'into' => 'OneBound 商品采集接口的调用密钥，仅保存在服务端配置中。',
                'sort' => 391,
                'value' => '',
            ],
        ];

        foreach ($configs as $config) {
            $key = addslashes($config['key']);
            $title = addslashes($config['title']);
            $into = addslashes($config['into']);
            $sort = (int) $config['sort'];
            $value = addslashes(json_encode($config['value'], JSON_UNESCAPED_UNICODE));
            $this->execute(
                "INSERT INTO `{$prefix}config` "
                . "(`key_name`, `cate_id`, `value`, `title`, `into`, `sort`, `status`, `create_time`, `update_time`) "
                . "SELECT '{$key}', 0, '{$value}', '{$title}', '{$into}', {$sort}, 1, '{$now}', '{$now}' "
                . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config` WHERE `key_name` = '{$key}' LIMIT 1)"
            );
        }

        $content = [
            $this->switchField(
                'product_collector_enabled',
                '是否开启商品采集',
                ConfigKeyEnum::PRODUCT_COLLECTOR_ENABLED,
                '关闭后后台商品采集入口不可用，服务端不会请求万邦接口。'
            ),
            $this->field(
                'product_collector_key',
                '商品采集调用 Key',
                ConfigKeyEnum::PRODUCT_COLLECTOR_KEY,
                '请输入 OneBound 商品采集 Key',
                'OneBound 淘宝商品详情服务的调用 Key。'
            ),
            $this->field(
                'product_collector_secret',
                '商品采集调用 Secret',
                ConfigKeyEnum::PRODUCT_COLLECTOR_SECRET,
                '请输入 OneBound 商品采集 Secret',
                'OneBound 淘宝商品详情服务的调用密钥，仅由服务端使用。'
            ),
        ];
        $fieldKeys = [
            ConfigKeyEnum::PRODUCT_COLLECTOR_ENABLED,
            ConfigKeyEnum::PRODUCT_COLLECTOR_KEY,
            ConfigKeyEnum::PRODUCT_COLLECTOR_SECRET,
        ];
        $fields = [
            ['field' => ConfigKeyEnum::PRODUCT_COLLECTOR_ENABLED, 'label' => '是否开启商品采集', 'type' => 'switch'],
            ['field' => ConfigKeyEnum::PRODUCT_COLLECTOR_KEY, 'label' => '商品采集调用 Key', 'type' => 'input'],
            ['field' => ConfigKeyEnum::PRODUCT_COLLECTOR_SECRET, 'label' => '商品采集调用 Secret', 'type' => 'input'],
        ];

        $templateKey = addslashes(self::TEMPLATE_KEY);
        $configName = addslashes('商品采集配置');
        $configDesc = addslashes('配置商品采集开关以及 OneBound 淘宝、京东商品详情采集所需的调用 Key 和 Secret');
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
     * 构造动态配置表单字段。
     *
     * @param string $id 字段 ID。
     * @param string $label 字段标题。
     * @param string $field 配置键。
     * @param string $placeholder 占位提示。
     * @param string $description 字段说明。
     * @return array<string,mixed>
     */
    private function field(
        string $id,
        string $label,
        string $field,
        string $placeholder,
        string $description
    ): array {
        return [
            '_id' => $id,
            'type' => 'input',
            'label' => $label,
            'props' => [
                'field' => $field,
                'label' => $label,
                'placeholder' => $placeholder,
                'required' => false,
                'disabled' => false,
                'defaultValue' => '',
                'description' => $description,
            ],
        ];
    }

    /**
     * 构造动态配置开关字段。
     *
     * @param string $id 字段 ID。
     * @param string $label 字段标题。
     * @param string $field 配置键。
     * @param string $description 字段说明。
     * @return array<string,mixed>
     */
    private function switchField(
        string $id,
        string $label,
        string $field,
        string $description
    ): array {
        return [
            '_id' => $id,
            'type' => 'switch',
            'label' => $label,
            'props' => [
                'field' => $field,
                'label' => $label,
                'activeText' => '开启',
                'inactiveText' => '关闭',
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
