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

use app\common\enum\LiveConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\setting\system\Config;
use app\common\service\plugin\PluginBusinessEventService;
use app\common\service\BaseService;
use Rabbit\interfaces\ConfigInterface;
use Rabbit\traits\ViewTrait;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 获取配置服务
 * Class ConfigService
 * @package app\common\service
 */
class ConfigService extends BaseService implements ConfigInterface
{
    use ViewTrait;

    //配置分类
    const CATEGORY_TYPE = 1;
    /**
     * 只读字段到实际存储字段的映射
     * key: field_keys 中的只读字段名
     * value: ['store_key' => 实际存储的 config key, 'param' => URL 中解析的参数名]
     */
    const READONLY_URL_MAP = [
        'ali_callback_url'   => ['store_key' => LiveConfigKeyEnum::ALI_CALLBACK_TOKEN,   'param' => 'token'],
        'qiniu_callback_url' => ['store_key' => LiveConfigKeyEnum::QINIU_CALLBACK_TOKEN, 'param' => 'token'],
    ];

    // 不保存的配置项
    const NOT_SAVE_KEYS = ['wechat_url', 'ali_callback_url', 'qiniu_callback_url'];
    const WECHAT_URL_KEY = 'wechat_url';

    /**
     * 不允许通过后台 GET 配置接口返回的敏感配置键模式。
     */
    private const SENSITIVE_CONFIG_KEY_PATTERN = '/(^|_)(access_key|secret_key|private_key|api_key|token|password|certificate|cert|key|secret)(_|$)/i';

    /**
     * 获取单个配置
     * @param string $key
     * @return null
     */
    public function getConfigValue(string $key)
    {
        $res = $this->cacheTag()->remember('key_name:' . $key, fn() => $this->where('key_name', $key)->value('value'));
        return $res !== null ? json_decode($res, true) : null;
    }

    /**
     * 批量获取配置
     * @param array $keys
     * @param bool $isset
     * @return array
     */
    public function getMultiConfigVlaue(array $keys, bool $isset = true)
    {
        $datas = $this->whereIn('key_name', $keys)->column('value', 'key_name');

        foreach ($datas as &$item) {
            $item = json_decode($item, true);
        }
        foreach ($keys as $key) {
            if (!isset($datas[$key]) && $isset) {
                $datas[$key] = '';
            }
        }

        return $datas;
    }

    /**
     * 按 key 批量保存配置值。
     *
     * @param array $values 配置 key 与配置值映射。
     * @param int $cateId 配置分类 ID。
     * @param array $metas 配置 key 对应标题、简介等元信息。
     * @return bool 是否保存成功。
     */
    public function saveKeyValues(array $values, int $cateId = 0, array $metas = []): bool
    {
        $changedKeys = [];
        $this->transaction(function () use ($values, $cateId, $metas, &$changedKeys) {
            $save = [];
            foreach ($values as $key => $value) {
                $changedKeys[] = (string)$key;
                $data = [
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'cate_id' => $cateId,
                ];
                if (isset($metas[$key]['title'])) {
                    $data['title'] = $metas[$key]['title'];
                }
                if (isset($metas[$key]['into'])) {
                    $data['into'] = $metas[$key]['into'];
                }
                if (isset($metas[$key]['sort'])) {
                    $data['sort'] = (int)$metas[$key]['sort'];
                }
                if (isset($metas[$key]['status'])) {
                    $data['status'] = (int)$metas[$key]['status'];
                }

                if ($this->getModel()->where('key_name', $key)->count()) {
                    $this->getModel()->update($data, ['key_name' => $key]);
                    continue;
                }

                $data['key_name'] = $key;
                $save[] = $data;
            }
            if ($save) {
                $this->getModel()->saveAll($save);
            }
            return true;
        });
        $this->cacheTag()->clear();
        $this->publishConfigChanged($changedKeys, 'save_key_values', $cateId);
        return true;
    }

    /**
     * 获取配置
     * @param string $key
     * @return mixed
     */
    public function getValue(string $key): mixed
    {
        return $this->getConfigValue($key);
    }

    /**
     * 获取配置多个
     * @param array $key
     * @return array
     */
    public function getMultiValue(array $key): array
    {
        return $this->getMultiConfigVlaue($key);
    }

    /**
     * 获取配置分类
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getConfigCate(int $pid)
    {
        return app()->make(CategoryService::class)
            ->setCategoryType(self::CATEGORY_TYPE)
            ->getCategoryList(['pid' => $pid]);
    }

    /**
     * @param int $id
     * @param array $data
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function configSave(int $id, array $data): bool
    {
        $configTemplate = app()->make(ConfigTemplateService::class)->get($id);
        if (!$configTemplate) {
            throw new AdminResponseException('配置不存在');
        }

        $saveData = [];

        foreach ($configTemplate->field_keys as $key) {
            $saveData[$key] = $data[$key] ?? '';
        }

        $changedKeys = [];
        $res = $this->transaction(function () use ($saveData, $configTemplate, &$changedKeys) {
            $save = [];
            foreach ($saveData as $key => $value) {
                if (in_array($key, self::NOT_SAVE_KEYS)) {
                    // 只读 URL 字段：解析 URL 中的 token 参数存入对应的 token key
                    if (isset(self::READONLY_URL_MAP[$key])) {
                        $map        = self::READONLY_URL_MAP[$key];
                        $storeKey   = $map['store_key'];
                        $paramName  = $map['param'];
                        $parsed     = [];
                        parse_str((string)parse_url((string)$value, PHP_URL_QUERY), $parsed);
                        $tokenValue = (string)($parsed[$paramName] ?? '');
                        if ($tokenValue !== '') {
                            $changedKeys[] = (string)$storeKey;
                            if ($this->getModel()->where('key_name', $storeKey)->count()) {
                                $this->getModel()->update(['value' => json_encode($tokenValue)], ['key_name' => $storeKey]);
                            } else {
                                $save[] = [
                                    'key_name' => $storeKey,
                                    'value'    => json_encode($tokenValue),
                                    'cate_id'  => $configTemplate->cate_id,
                                ];
                            }
                        }
                    }
                    continue;
                }
                $changedKeys[] = (string)$key;
                if ($this->getModel()->where('key_name', $key)->count()) {
                    $this->getModel()->update(['value' => json_encode($value)], ['key_name' => $key]);
                } else {
                    $save[] = [
                        'key_name' => $key,
                        'value'    => json_encode($value),
                        'cate_id'  => $configTemplate->cate_id,
                    ];
                }
            }
            if ($save) {
                $this->getModel()->saveAll($save);
            }
            return true;
        });
        $this->cacheTag()->clear();
        $this->publishConfigChanged($changedKeys, 'config_save', (int)($configTemplate->cate_id ?? 0));
        return $res;
    }

    /**
     * 按前端声明的字段获取配置值。
     *
     * GET 配置接口默认不返回任何字段，避免调用方遗漏字段筛选时泄露整张配置表。
     *
     * @param array|string $keys 配置键列表，也支持逗号分隔的字符串。
     * @return array
     */
    public function getConfigValues(array|string $keys = []): array
    {
        if (is_string($keys)) {
            $keys = preg_split('/[,，\s]+/', $keys, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $keys = array_values(array_unique(array_filter(array_map(
            static fn ($key): string => trim((string)$key),
            $keys
        ))));
        $keys = array_values(array_filter(
            $keys,
            fn (string $key): bool => !$this->isSensitiveConfigKey($key)
        ));

        if ($keys === []) {
            return [];
        }

        $column = $this->getModel()
            ->whereIn('key_name', $keys)
            ->column('value', 'key_name');
        foreach ($column as $key => $item) {
            $column[$key] = $item ? json_decode($item, true) : '';
        }
        return $column;
    }

    /**
     * 判断配置键是否包含敏感凭据。
     *
     * @param string $key 配置键。
     * @return bool
     */
    private function isSensitiveConfigKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_CONFIG_KEY_PATTERN, $key) === 1;
    }

    /**
     * 发布配置变更事件，只携带键名和分类，不包含任何配置值。
     *
     * @param array<int, string> $keys
     * @param string $source
     * @param int $cateId
     * @return void
     */
    private function publishConfigChanged(array $keys, string $source, int $cateId = 0): void
    {
        $keys = array_values(array_unique(array_filter(array_map(
            static fn ($key): string => trim((string)$key),
            $keys
        ))));
        if ($keys === []) {
            return;
        }
        sort($keys, SORT_STRING);

        app()->make(PluginBusinessEventService::class)->publish(
            'config.changed',
            [
                'config_keys' => $keys,
                'cate_id' => $cateId,
                'source' => $source,
            ],
            [
                'subject_type' => 'config',
                'subject_id' => (string)$cateId,
                'idempotency_key' => 'config.changed:' . substr(hash('sha256', $source . '|' . $cateId . '|' . implode(',', $keys)), 0, 64),
            ]
        );
    }

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return Config::class;
    }
}
