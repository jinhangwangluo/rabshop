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
 * 写入平台自营店铺展示配置。
 */
class SeedPlatformStoreProfileConfig extends Migrator
{
    /**
     * 配置模板标识。
     */
    private const TEMPLATE_KEY = 'platform_store_profile_config';

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
                'key' => ConfigKeyEnum::PLATFORM_STORE_NAME,
                'title' => '平台自营店铺名称',
                'into' => '商品详情页平台自营店铺信息卡片展示名称，默认使用网站名称兜底。',
                'sort' => 392,
                'value' => '',
            ],
            [
                'key' => ConfigKeyEnum::PLATFORM_STORE_AVATAR,
                'title' => '平台自营店铺头像',
                'into' => '商品详情页平台自营店铺信息卡片展示头像，默认使用登录页 Logo 兜底。',
                'sort' => 393,
                'value' => '',
            ],
            [
                'key' => ConfigKeyEnum::PLATFORM_STORE_DESCRIPTION,
                'title' => '平台自营店铺简介',
                'into' => '商品详情页平台自营店铺信息卡片展示的简短简介。',
                'sort' => 394,
                'value' => '品质美妆 · 正品保证',
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
            $this->field(
                'platform_store_name',
                '平台自营店铺名称',
                ConfigKeyEnum::PLATFORM_STORE_NAME,
                '请输入平台自营店铺名称',
                '为空时商品详情页使用网站名称兜底。'
            ),
            $this->uploadField(
                'platform_store_avatar',
                '平台自营店铺头像',
                ConfigKeyEnum::PLATFORM_STORE_AVATAR,
                '为空时商品详情页使用登录页 Logo 兜底。'
            ),
            $this->field(
                'platform_store_description',
                '平台自营店铺简介',
                ConfigKeyEnum::PLATFORM_STORE_DESCRIPTION,
                '请输入平台自营店铺简短简介',
                '展示在商品详情页平台自营店铺信息卡片中。'
            ),
        ];
        $fieldKeys = [
            ConfigKeyEnum::PLATFORM_STORE_NAME,
            ConfigKeyEnum::PLATFORM_STORE_AVATAR,
            ConfigKeyEnum::PLATFORM_STORE_DESCRIPTION,
        ];
        $fields = [
            ['field' => ConfigKeyEnum::PLATFORM_STORE_NAME, 'label' => '平台自营店铺名称', 'type' => 'input'],
            ['field' => ConfigKeyEnum::PLATFORM_STORE_AVATAR, 'label' => '平台自营店铺头像', 'type' => 'upload'],
            ['field' => ConfigKeyEnum::PLATFORM_STORE_DESCRIPTION, 'label' => '平台自营店铺简介', 'type' => 'input'],
        ];

        $templateKey = addslashes(self::TEMPLATE_KEY);
        $configName = addslashes('平台自营店铺展示配置');
        $configDesc = addslashes('配置商品详情页平台自营店铺信息卡片展示内容');
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
            '_fc_drag_tag' => 'input',
            'type' => 'input',
            'field' => $field,
            'title' => $label,
            'info' => '',
            '$required' => false,
            '_fc_id' => $id,
            'name' => $id,
            'display' => true,
            'hidden' => false,
            'props' => [
                'type' => 'text',
                'placeholder' => $placeholder,
            ],
            'validate' => [],
            'description' => $description,
        ];
    }

    /**
     * 构造图片上传配置字段。
     *
     * @param string $id 字段 ID。
     * @param string $label 字段标题。
     * @param string $field 配置键。
     * @param string $description 字段说明。
     * @return array<string,mixed>
     */
    private function uploadField(
        string $id,
        string $label,
        string $field,
        string $description
    ): array {
        return [
            '_fc_drag_tag' => 'upload',
            'type' => 'upload',
            'field' => $field,
            'title' => $label,
            'info' => '',
            '$required' => false,
            '_fc_id' => $id,
            'name' => $id,
            'display' => true,
            'hidden' => false,
            'props' => [
                'buttonText' => '点击上传',
                'accept' => 'image/*',
                'limit' => 1,
                'required' => false,
                'disabled' => false,
                'description' => $description,
            ],
            'validate' => [],
            'description' => $description,
        ];
    }
}
