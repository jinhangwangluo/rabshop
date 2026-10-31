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

namespace app\common\service\combination;

use app\common\exception\ApiValidateException;
use app\common\model\combination\CombinationData;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 组合数据服务.
 *
 * 负责组合数据记录的增删改查、动态表单数据保存、列表动态列解析，
 * 并强制使用当前 merchantId 做平台端和商户端数据隔离。
 */
class CombinationDataService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 当前数据归属商户ID；0 表示平台端数据。
     *
     * @var int
     */
    protected int $merchantId = 0;

    /**
     * 设置当前服务操作的模型类.
     *
     * @return string
     */
    protected function setModel(): string
    {
        return CombinationData::class;
    }

    /**
     * 设置当前操作的数据归属.
     *
     * @param int $merchantId 商户ID，平台端传 0
     * @return $this
     */
    public function setMerchantId(int $merchantId): self
    {
        $this->merchantId = $merchantId;
        return $this;
    }

    /**
     * 获取组合数据分页列表，并根据配置 schema 返回动态列.
     *
     * @param array $params 查询参数：category_id/config_id/data_type/status
     * @param array|string $fields 保留资源服务签名
     * @param array $with 保留资源服务签名
     * @param string|array|null $sort 保留资源服务签名
     * @return array
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null): array
    {
        [$page, $limit] = $this->getPagesValue();
        $page = $page ?: 1;
        $limit = $limit ?: 20;

        $configId = (int)($params['config_id'] ?? 0);
        $config = $configId ? $this->getConfigService()->getOwnedConfig($configId) : null;

        $query = $this->getModel()
            ->where('merchant_id', $this->merchantId)
            ->when($configId > 0, fn($query) => $query->where('config_id', $configId))
            ->when(isset($params['category_id']) && $params['category_id'] !== '', fn($query) => $query->where('category_id', (int)$params['category_id']))
            ->when(isset($params['status']) && $params['status'] !== '', fn($query) => $query->where('status', (int)$params['status']))
            ->when(!empty($params['data_type']), fn($query) => $query->where('data_type', (string)$params['data_type']));

        $count = (clone $query)->count();
        $list = $query->order('sort desc, id desc')->page($page, $limit)->select()->toArray();
        $list = array_map(fn($item) => $this->formatListItem($item), $list);

        return [
            'list' => $list,
            'total' => $count,
            'count' => $count,
            'page' => $page,
            'limit' => $limit,
            'config' => $config ? $config->toArray() : null,
            'columns' => $config ? $this->extractColumns($config->content) : [],
        ];
    }

    /**
     * 新增组合数据.
     *
     * @param array $data 请求数据，包含 config_id/category_id/sort/status/form_data
     * @return mixed
     */
    public function resourceSave(array $data)
    {
        $payload = $this->buildSaveData($data);
        $model = $this->create($payload);
        $this->clearCombinationDataCache();

        return $model;
    }

    /**
     * 更新组合数据.
     *
     * @param int|array $id 数据ID
     * @param array $data 请求数据，包含 config_id/category_id/sort/status/form_data
     * @return bool
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $dataId = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $item = $this->getOwnedData($dataId);
        if (!$item) {
            throw new ApiValidateException('组合数据不存在');
        }

        $payload = $this->buildSaveData($data, $dataId);
        $this->update($dataId, $payload);
        $this->clearCombinationDataCache();

        return true;
    }

    /**
     * 删除当前归属下的组合数据.
     *
     * @param int $id 数据ID
     * @return bool
     */
    public function resourceDelete(int $id)
    {
        if (!$this->getOwnedData($id)) {
            throw new ApiValidateException('组合数据不存在');
        }

        $deleted = $this->getModel()->destroy($id);
        $this->clearCombinationDataCache();

        return $deleted;
    }

    /**
     * 获取指定类型的启用组合数据.
     *
     * @param string $dataType 数据类型
     * @param int $merchantId 商户ID，0 表示平台端
     * @return array
     */
    public function getEnabledDataByType(string $dataType, int $merchantId = 0): array
    {
        $dataType = trim($dataType);
        if ($dataType === '') {
            return [];
        }

        $cacheKey = sprintf('combination_data:%d:%s', $merchantId, $dataType);

        return $this->cacheTag()->remember($cacheKey, function () use ($dataType, $merchantId) {
            $list = $this->getModel()
                ->where('merchant_id', $merchantId)
                ->where('data_type', $dataType)
                ->where('status', 1)
                ->order('sort desc, id desc')
                ->field(['id', 'sort', 'data_json'])
                ->select()
                ->toArray();

            return array_values(array_map(static function ($item) {
                $data = is_array($item['data_json'] ?? null) ? $item['data_json'] : [];
                $data['id'] = (int)($item['id'] ?? 0);
                $data['combination_data_id'] = (int)($item['id'] ?? 0);
                $data['sort'] = (int)($item['sort'] ?? 0);

                return $data;
            }, $list));
        });
    }

    /**
     * 清理组合数据读取缓存.
     *
     * @return void
     */
    public function clearCombinationDataCache(): void
    {
        $this->cacheTag()->clear();
    }

    /**
     * 读取组合数据详情.
     *
     * @param int|array $id 数据ID或查询条件
     * @return array
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws DbException
     */
    public function resourceRead(int|array $id)
    {
        $model = is_array($id)
            ? $this->getModel()->where($id)->where('merchant_id', $this->merchantId)->find()
            : $this->getOwnedData((int)$id);

        if (!$model) {
            throw new ModelNotFoundException('查询的数据不存在');
        }

        $data = $model->toArray();
        $data['form_data'] = $data['data_json'] ?? [];
        $config = $this->getConfigService()->getOwnedConfig((int)$data['config_id']);
        $data['config'] = $config ? $config->toArray() : null;

        return $data;
    }

    /**
     * 获取配置 schema，用于动态列表列和动态表单渲染.
     *
     * @param int $configId 配置ID
     * @return array
     */
    public function getSchema(int $configId): array
    {
        $config = $this->getConfigService()->getOwnedConfig($configId);
        if (!$config) {
            throw new ApiValidateException('组合数据配置不存在');
        }

        $configData = $config->toArray();
        $configData['columns'] = $this->extractColumns($config->content);

        return $configData;
    }

    /**
     * 根据配置和提交表单组装数据库保存数据.
     *
     * sort/status 是组合数据记录字段，不属于动态表单 JSON；即使前端误把同名字段放入
     * form_data，也会在入库前剔除，避免和 data_json 混合。
     *
     * @param array $data 请求数据
     * @param int $dataId 编辑时的数据ID
     * @return array
     */
    protected function buildSaveData(array $data, int $dataId = 0): array
    {
        $configId = (int)($data['config_id'] ?? 0);
        if (!$configId && $dataId) {
            $current = $this->getOwnedData($dataId);
            $configId = $current ? (int)$current->config_id : 0;
        }
        if (!$configId) {
            throw new ApiValidateException('请选择组合数据配置');
        }

        $config = $this->getConfigService()->getOwnedConfig($configId);
        if (!$config) {
            throw new ApiValidateException('组合数据配置不存在');
        }

        $formData = $data['form_data'] ?? $data['data_json'] ?? [];
        if (is_string($formData)) {
            $decoded = json_decode($formData, true);
            $formData = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($formData)) {
            throw new ApiValidateException('组合数据内容格式错误');
        }
        unset($formData['sort'], $formData['status']);

        return [
            'category_id' => (int)($data['category_id'] ?? $config->category_id),
            'config_id' => $configId,
            'merchant_id' => $this->merchantId,
            'data_type' => (string)$config->config_key,
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
            'data_json' => json_encode($formData, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * 获取当前归属下的数据记录.
     *
     * @param int $id 数据ID
     * @return mixed
     */
    protected function getOwnedData(int $id)
    {
        return $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $this->merchantId)
            ->find();
    }

    /**
     * 获取已设置同一归属的配置服务.
     *
     * @return CombinationDataConfigService
     */
    protected function getConfigService(): CombinationDataConfigService
    {
        return app()->make(CombinationDataConfigService::class)->setMerchantId($this->merchantId);
    }

    /**
     * 把 data_json 展开成列表列可直接读取的 data_xxx 字段.
     *
     * @param array $item 数据库记录
     * @return array
     */
    protected function formatListItem(array $item): array
    {
        $data = is_array($item['data_json'] ?? null) ? $item['data_json'] : [];
        $item['form_data'] = $data;
        foreach ($data as $key => $value) {
            $item['data_' . $key] = $value;
        }

        return $item;
    }

    /**
     * 从设计器 schema 中提取动态表格列.
     *
     * @param array $fields 表单设计器字段树
     * @return array
     */
    protected function extractColumns(array $fields): array
    {
        $columns = [];
        $walk = function (array $items) use (&$columns, &$walk) {
            foreach ($items as $field) {
                $type = $field['type'] ?? '';
                $props = $field['props'] ?? [];
                if (!in_array($type, ['card', 'tabs', 'alert'], true) && !empty($props['field'])) {
                    $columns[] = [
                        'field' => $props['field'],
                        'prop' => 'data_' . $props['field'],
                        'label' => $props['label'] ?? ($field['label'] ?? $props['field']),
                        'type' => $type,
                    ];
                }
                if ($type === 'card' && !empty($props['children']) && is_array($props['children'])) {
                    $walk($props['children']);
                }
                if ($type === 'tabs' && !empty($props['tabs']) && is_array($props['tabs'])) {
                    foreach ($props['tabs'] as $tab) {
                        if (!empty($tab['children']) && is_array($tab['children'])) {
                            $walk($tab['children']);
                        }
                    }
                }
            }
        };
        $walk($fields);

        return $columns;
    }
}
