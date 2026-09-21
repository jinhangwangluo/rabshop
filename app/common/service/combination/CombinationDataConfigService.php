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
use app\common\model\combination\CombinationDataConfig;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 组合数据配置服务.
 *
 * 负责组合数据配置表的增删改查、配置标识唯一性校验、设计器 schema 保存，
 * 并通过 merchantId 统一隔离平台端和商户端数据。
 */
class CombinationDataConfigService extends BaseService implements ResourceServiceInterfaceTrait
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
        return CombinationDataConfig::class;
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
     * 检查同一归属下配置标识是否重复.
     *
     * @param string $configKey 配置标识
     * @param int $merchantId 商户ID，平台端为 0
     * @param int $ignoreId 编辑时忽略的配置ID
     * @return bool
     */
    public function checkConfigKey(string $configKey, int $merchantId = 0, int $ignoreId = 0): bool
    {
        if ($configKey === '') {
            return false;
        }

        return (bool)$this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('config_key', $configKey)
            ->when($ignoreId > 0, fn($query) => $query->where('id', '<>', $ignoreId))
            ->count();
    }

    /**
     * 获取启用状态的配置下拉选项.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->getModel()
            ->where('merchant_id', $this->merchantId)
            ->where('status', 1)
            ->field('id, category_id, config_name, config_key')
            ->order('id desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取当前归属下的配置.
     *
     * @param int $id 配置ID
     * @return mixed
     */
    public function getOwnedConfig(int $id)
    {
        return $this->getModel()
            ->where('id', $id)
            ->where('merchant_id', $this->merchantId)
            ->find();
    }

    /**
     * 按配置标识获取当前归属下的配置详情.
     *
     * @param string $configKey 配置标识
     * @return array
     */
    public function getOwnedConfigByKey(string $configKey): array
    {
        $configKey = trim($configKey);
        if ($configKey === '') {
            throw new ApiValidateException('配置标识不能为空');
        }

        $config = $this->getModel()
            ->where('merchant_id', $this->merchantId)
            ->where('config_key', $configKey)
            ->find();

        if (!$config) {
            throw new ApiValidateException('组合数据配置不存在');
        }

        return $config->toArray();
    }

    /**
     * 读取配置详情.
     *
     * @param int|array $id 配置ID或查询条件
     * @return array
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws DbException
     */
    public function resourceRead(int|array $id)
    {
        $model = is_array($id)
            ? $this->getModel()->where($id)->where('merchant_id', $this->merchantId)->find()
            : $this->getOwnedConfig((int)$id);

        if (!$model) {
            throw new ModelNotFoundException('查询的数据不存在');
        }

        return $model->toArray();
    }

    /**
     * 保存配置基础信息.
     *
     * @param array $data 请求数据
     * @return mixed
     */
    public function resourceSave(array $data)
    {
        $data['merchant_id'] = $this->merchantId;
        $data = $this->normalizeJsonFields($data);
        $this->assertValidConfig($data);
        return $this->create($data);
    }

    /**
     * 更新配置基础信息.
     *
     * @param int|array $id 配置ID
     * @param array $data 请求数据
     * @return bool
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $configId = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $config = $this->getOwnedConfig($configId);
        if (!$config) {
            throw new ApiValidateException('组合数据配置不存在');
        }

        $data['merchant_id'] = $this->merchantId;
        $data = $this->normalizeJsonFields($data);
        $this->assertValidConfig($data, $configId);
        $this->update($configId, $data);

        return true;
    }

    /**
     * 删除当前归属下的配置.
     *
     * @param int $id 配置ID
     * @return bool
     */
    public function resourceDelete(int $id)
    {
        $config = $this->getOwnedConfig($id);
        if (!$config) {
            throw new ApiValidateException('组合数据配置不存在');
        }

        return $this->getModel()->destroy($id);
    }

    /**
     * 修改配置状态.
     *
     * @param int|array $id 配置ID
     * @param array $data 状态字段
     * @return bool
     */
    public function resourceShowUpdate(int|array $id, array $data)
    {
        $configId = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        if (!$this->getOwnedConfig($configId)) {
            throw new ApiValidateException('组合数据配置不存在');
        }

        $this->update($configId, $data);
        return true;
    }

    /**
     * 获取配置分页列表.
     *
     * @param array $params 查询参数：category_id/status/keyword
     * @param array|string $fields 保留资源服务签名
     * @param array $with 保留资源服务签名
     * @param string|array|null $sort 保留资源服务签名
     * @return array
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        [$page, $limit] = $this->getPagesValue();
        $page = $page ?: 1;
        $limit = $limit ?: 20;

        $query = $this->getModel()
            ->where('merchant_id', $this->merchantId)
            ->when(isset($params['category_id']) && $params['category_id'] !== '', fn($query) => $query->where('category_id', (int)$params['category_id']))
            ->when(isset($params['status']) && $params['status'] !== '', fn($query) => $query->where('status', (int)$params['status']))
            ->when(!empty($params['keyword']), function ($query) use ($params) {
                $query->whereLike('config_name|config_key', '%' . $params['keyword'] . '%');
            });

        $count = (clone $query)->count();
        $list = $query->order('id desc')->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'total' => $count,
            'count' => $count,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 校验配置基础信息.
     *
     * @param array $data 配置数据
     * @param int $ignoreId 编辑时忽略的配置ID
     * @return void
     */
    protected function assertValidConfig(array $data, int $ignoreId = 0): void
    {
        if (trim((string)($data['config_name'] ?? '')) === '') {
            throw new ApiValidateException('配置名称必须填写');
        }
        if (trim((string)($data['config_key'] ?? '')) === '') {
            throw new ApiValidateException('配置标识必须填写');
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', (string)$data['config_key'])) {
            throw new ApiValidateException('配置标识必须以字母开头，仅支持字母、数字和下划线');
        }
        if ($this->checkConfigKey((string)$data['config_key'], $this->merchantId, $ignoreId)) {
            throw new ApiValidateException('配置标识已存在');
        }
    }

    /**
     * 规整配置中的 JSON 字段，避免查询构造器更新数组时报错.
     *
     * @param array $data 配置数据
     * @return array
     */
    protected function normalizeJsonFields(array $data): array
    {
        foreach (['content', 'field_keys', 'fields'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->encodeJsonField($data[$field]);
            }
        }

        return $data;
    }

    /**
     * 把数组字段编码为 JSON 字符串.
     *
     * @param array|string $value JSON 字符串或数组
     * @return string
     */
    protected function encodeJsonField(array|string $value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
