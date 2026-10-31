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

use app\common\enum\PayTypeEnum;
use app\common\enum\PaymentConfigKeyEnum;
use app\common\enum\PaymentTerminalEnum;
use app\common\exception\AdminResponseException;
use app\common\model\setting\system\PaymentConfig as PaymentConfigModel;
use app\common\service\BaseService;

/**
 * 支付配置服务。
 */
class PaymentConfigService extends BaseService
{
    /**
     * 获取后台支付配置详情。
     *
     * @return array 支付方式、支付类型选项、终端选项和独立配置项。
     */
    public function detail(): array
    {
        $methods = $this->getDisplayMethods();
        return [
            'methods' => $this->appendMethodSettings($methods),
            'pay_type_options' => PayTypeEnum::options(true),
            'pay_type_templates' => $this->getPayTypeTemplates(),
            'terminal_options' => PaymentTerminalEnum::options(),
        ];
    }

    /**
     * 获取支付配置可选项。
     *
     * @return array 支付类型和终端选项。
     */
    public function options(): array
    {
        return [
            'pay_type_options' => PayTypeEnum::options(true),
            'pay_type_templates' => $this->getPayTypeTemplates(),
            'terminal_options' => PaymentTerminalEnum::options(),
        ];
    }

    /**
     * 保存支付展示配置和独立支付参数。
     *
     * @param array $methods 支付方式配置列表。
     * @return array 保存后的支付配置详情。
     */
    public function saveDisplayConfig(array $methods, array $settingKeys = []): array
    {
        $methods = $this->normalizeMethods($methods);
        $settings = $this->extractSettings($methods, $settingKeys);

        $this->saveDisplayMethodsToTable($methods);
        if ($settings !== []) {
            app()->make(ConfigService::class)->saveKeyValues($settings, 0, $this->buildConfigMetas($settings));
        }

        return $this->detail();
    }

    /**
     * 保存单个支付展示配置和对应独立支付参数。
     *
     * @param array $method 支付方式配置。
     * @return array 保存后的支付配置详情。
     */
    public function saveDisplayMethod(array $method): array
    {
        $normalized = $this->normalizeMethods([$method]);
        $currentMethods = $this->getDisplayMethods();
        $savedMethod = $normalized[0];
        $exists = false;

        foreach ($currentMethods as $index => $currentMethod) {
            if ($currentMethod['pay_type'] !== $savedMethod['pay_type']) {
                continue;
            }

            $currentMethods[$index] = $savedMethod;
            $exists = true;
            break;
        }

        if (!$exists) {
            $currentMethods[] = $savedMethod;
        }

        return $this->saveDisplayConfig(
            $currentMethods,
            $this->extractSubmittedSettingKeys($savedMethod['pay_type'], $method['settings'] ?? [])
        );
    }

    /**
     * 获取启用且适配终端的支付方式。
     *
     * @param string $terminal 终端类型。
     * @return array 支付方式列表。
     */
    public function getEnabledMethodsByTerminal(string $terminal): array
    {
        if (!in_array($terminal, PaymentTerminalEnum::values(), true)) {
            return [];
        }

        $methods = array_values(array_filter($this->getDisplayMethods(), function (array $method) use ($terminal) {
            return (int)$method['status'] === 1 && in_array($terminal, $method['terminals'], true);
        }));

        return array_map([$this, 'formatPublicMethod'], $methods);
    }

    /**
     * 格式化移动端可见的支付方式字段，避免暴露支付密钥配置。
     */
    protected function formatPublicMethod(array $method): array
    {
        return [
            'pay_type' => (string)($method['pay_type'] ?? ''),
            'image' => (string)($method['image'] ?? ''),
            'text' => (string)($method['text'] ?? ''),
            'title' => (string)($method['title'] ?? ''),
            'subtitle' => (string)($method['subtitle'] ?? ''),
            'status' => (int)($method['status'] ?? 0),
            'terminals' => is_array($method['terminals'] ?? null) ? $method['terminals'] : [],
            'sort' => (int)($method['sort'] ?? 0),
        ];
    }

    /**
     * 获取支付方式展示配置。
     *
     * @return array 支付方式列表。
     */
    public function getDisplayMethods(): array
    {
        $rows = $this->getModel()
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->normalizeMethods($this->formatStoredDisplayMethods($rows), false);
    }

    /**
     * 保存支付方式展示配置到 payment_config 表。
     */
    protected function saveDisplayMethodsToTable(array $methods): void
    {
        $this->transaction(function () use ($methods) {
            $payTypes = array_values(array_map(static fn(array $method) => (string)$method['pay_type'], $methods));
            foreach ($methods as $method) {
                $payType = (string)$method['pay_type'];
                $data = $this->buildDisplayMethodSaveData($method);

                $exists = $this->getModel()->where('pay_type', $payType)->find();
                if ($exists) {
                    $this->getModel()->where('pay_type', $payType)->update($data);
                    continue;
                }

                $data['pay_type'] = $payType;
                $this->getModel()->create($data);
            }

            if ($payTypes !== []) {
                $this->getModel()
                    ->whereIn('pay_type', PayTypeEnum::DISPLAY_PAY_TYPE_ALL)
                    ->whereNotIn('pay_type', $payTypes)
                    ->delete();
            }
            return true;
        });
    }

    /**
     * 构建 payment_config 表保存数据。
     */
    protected function buildDisplayMethodSaveData(array $method): array
    {
        $terminals = $method['terminals'] ?? [];

        return [
            'name' => (string)($method['title'] ?: $method['text']),
            'icon' => (string)($method['image'] ?? ''),
            'text' => (string)($method['text'] ?? ''),
            'title' => (string)$method['title'],
            'subtitle' => (string)($method['subtitle'] ?? ''),
            'description' => (string)($method['subtitle'] ?? ''),
            'terminals' => json_encode(is_array($terminals) ? array_values($terminals) : [], JSON_UNESCAPED_UNICODE),
            'sort' => (int)($method['sort'] ?? 0),
            'status' => (int)($method['status'] ?? 0),
        ];
    }

    /**
     * 格式化 payment_config 表数据为后台和移动端统一字段。
     */
    protected function formatStoredDisplayMethods(array $rows): array
    {
        return array_map(function (array $row) {
            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            $name = trim((string)($row['name'] ?? ''));
            $subtitle = trim((string)($row['subtitle'] ?? ''));
            $description = trim((string)($row['description'] ?? ''));

            return [
                'pay_type' => (string)($row['pay_type'] ?? ''),
                'image' => trim((string)($row['image'] ?? $row['icon'] ?? '')),
                'text' => $text !== '' ? $text : $name,
                'title' => $title !== '' ? $title : $name,
                'subtitle' => $subtitle !== '' ? $subtitle : $description,
                'status' => (int)($row['status'] ?? 0),
                'terminals' => is_array($row['terminals'] ?? null) ? $row['terminals'] : [],
                'sort' => (int)($row['sort'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * 标准化支付方式配置。
     *
     * @param array $methods 支付方式配置列表。
     * @param bool $requireAll 是否要求至少提交一个有效支付方式。
     * @return array 标准化后的支付方式配置列表。
     */
    protected function normalizeMethods(array $methods, bool $requireAll = true): array
    {
        $validPayTypes = PayTypeEnum::DISPLAY_PAY_TYPE_ALL;
        $validTerminals = PaymentTerminalEnum::values();
        $result = [];
        $exists = [];

        foreach ($methods as $method) {
            if (!is_array($method)) {
                continue;
            }

            $payType = (string)($method['pay_type'] ?? '');
            if (!in_array($payType, $validPayTypes, true)) {
                if ($requireAll) {
                    throw new AdminResponseException('支付类型错误');
                }
                continue;
            }
            if (isset($exists[$payType])) {
                if ($requireAll) {
                    throw new AdminResponseException('支付方式不能重复添加');
                }
                continue;
            }

            $terminals = $method['terminals'] ?? [];
            $terminals = is_array($terminals) ? array_values(array_intersect($terminals, $validTerminals)) : [];
            if (!$terminals && $requireAll) {
                throw new AdminResponseException('请至少选择一个展示终端');
            }

            $title = trim((string)($method['title'] ?? $method['name'] ?? ''));
            if ($title === '' && $requireAll) {
                throw new AdminResponseException('支付标题不能为空');
            }

            $result[] = [
                'pay_type' => $payType,
                'image' => trim((string)($method['image'] ?? $method['icon'] ?? '')),
                'text' => trim((string)($method['text'] ?? '')),
                'title' => $title ?: (PayTypeEnum::PAY_TYPE_ALL[$payType] ?? $payType),
                'subtitle' => trim((string)($method['subtitle'] ?? $method['description'] ?? '')),
                'status' => (int)($method['status'] ?? 0) === 1 ? 1 : 0,
                'terminals' => $terminals,
                'sort' => max(0, (int)($method['sort'] ?? 0)),
                'settings' => $this->normalizeSettings($payType, $method['settings'] ?? []),
            ];
            $exists[$payType] = true;
        }

        if (!$result && $requireAll) {
            throw new AdminResponseException('支付方式配置不能为空');
        }

        usort($result, fn(array $a, array $b) => $a['sort'] <=> $b['sort']);
        return $result;
    }

    /**
     * 标准化指定支付方式下的独立配置值。
     *
     * @param string $payType 支付类型。
     * @param array $settings 前端提交的配置值。
     * @return array 标准化后的配置值。
     */
    protected function normalizeSettings(string $payType, array $settings): array
    {
        $result = [];
        foreach (PaymentConfigKeyEnum::fieldsByPayType($payType) as $field) {
            $key = $field['key'];
            $value = $settings[$key] ?? '';
            if ($field['type'] === 'switch') {
                $result[$key] = (int)$value === 1 ? 1 : 0;
                continue;
            }

            if ($field['type'] === 'select') {
                $options = array_column($field['options'] ?? [], 'value');
                $value = trim((string)$value);
                $result[$key] = in_array($value, $options, true) ? $value : (string)($options[0] ?? '');
                continue;
            }

            $result[$key] = trim((string)$value);
        }
        return $result;
    }

    /**
     * 为支付方式追加独立配置字段和值。
     *
     * @param array $methods 支付方式列表。
     * @return array 带配置字段和值的支付方式列表。
     */
    protected function appendMethodSettings(array $methods): array
    {

        foreach ($methods as &$method) {
            $fields = PaymentConfigKeyEnum::fieldsByPayType($method['pay_type']);
            $method['setting_fields'] = $fields;
            $method['settings'] = $this->appendStoredSettingValues(
                $this->buildDefaultSettings($fields),
                $fields
            );
        }
        unset($method);

        return $methods;
    }

    /**
     * 为默认配置追加已保存的配置值。
     *
     * @param array $settings 默认配置值。
     * @param array $fields 配置字段定义列表。
     * @return array 合并已保存数据后的配置值。
     */
    protected function appendStoredSettingValues(array $settings, array $fields): array
    {
        $keys = array_column($fields, 'key');
        if ($keys === []) {
            return $settings;
        }

        $values = app()->make(ConfigService::class)->getMultiConfigVlaue($keys, false);
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                $settings[$key] = $values[$key];
            }
        }

        return $settings;
    }

    /**
     * 获取支付类型默认模板。
     *
     * @return array 支付类型默认模板映射。
     */
    protected function getPayTypeTemplates(): array
    {
        $templates = [];
        foreach (PayTypeEnum::defaultDisplayMethods() as $method) {
            $normalized = $this->normalizeMethods([$method], false);
            if (!$normalized) {
                continue;
            }

            $template = $normalized[0];
            $template['setting_fields'] = PaymentConfigKeyEnum::fieldsByPayType($template['pay_type']);
            $template['settings'] = $this->appendStoredSettingValues(
                $this->buildDefaultSettings($template['setting_fields']),
                $template['setting_fields']
            );
            $templates[$template['pay_type']] = $template;
        }

        return $templates;
    }

    /**
     * 获取支付参数默认值。
     *
     * @param array $field 配置字段定义。
     * @return int|string 默认值。
     */
    protected function getDefaultSettingValue(array $field)
    {
        if ($field['type'] === 'switch') {
            return 0;
        }

        if ($field['type'] === 'select') {
            $options = array_column($field['options'] ?? [], 'value');
            return (string)($options[0] ?? '');
        }

        return '';
    }

    /**
     * 构建默认支付参数。
     *
     * @param array $fields 配置字段定义列表。
     * @return array 默认支付参数。
     */
    protected function buildDefaultSettings(array $fields): array
    {
        $settings = [];
        foreach ($fields as $field) {
            $settings[$field['key']] = $this->getDefaultSettingValue($field);
        }

        return $settings;
    }

    /**
     * 从支付方式列表提取独立配置值。
     *
     * @param array $methods 支付方式列表。
     * @return array 配置 key 与值映射。
     */
    protected function extractSettings(array $methods, array $settingKeys = []): array
    {
        $settings = [];
        $settingKeys = array_flip($settingKeys);
        foreach ($methods as $method) {
            foreach ($method['settings'] as $key => $value) {
                if ($settingKeys && !isset($settingKeys[$key])) {
                    continue;
                }

                $settings[$key] = $value;
            }
        }
        return $settings;
    }

    /**
     * 提取本次单项保存实际提交的配置 key。
     *
     * @param string $payType 支付类型。
     * @param array $settings 前端提交的配置值。
     * @return array 允许覆盖的配置 key 列表。
     */
    protected function extractSubmittedSettingKeys(string $payType, array $settings): array
    {
        $validKeys = array_column(PaymentConfigKeyEnum::fieldsByPayType($payType), 'key');
        return array_values(array_intersect(array_keys($settings), $validKeys));
    }

    /**
     * 构建配置写入 config 表时的标题等元信息。
     *
     * @param array $settings 本次保存的独立配置值。
     * @return array 配置元信息。
     */
    protected function buildConfigMetas(array $settings): array
    {
        $metas = [];
        $labels = PaymentConfigKeyEnum::settingKeys();
        $sort = 61;
        foreach (array_keys($settings) as $key) {
            $metas[$key] = [
                'title' => $labels[$key] ?? $key,
                'into' => '后台支付配置页面维护的独立支付参数',
                'sort' => $sort++,
                'status' => 1,
            ];
        }
        return $metas;
    }

    /**
     * 设置对应模型。
     *
     * @return string 配置模型类名。
     */
    protected function setModel(): string
    {
        return PaymentConfigModel::class;
    }
}
