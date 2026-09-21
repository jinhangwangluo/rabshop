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

namespace app\common\service\cs;

use app\common\exception\AdminException;
use app\common\model\cs\CsAiModel;
use app\common\model\cs\CsAiProvider;
use app\common\service\BaseService;

/**
 * AI模型配置中心服务
 */
class AiModelConfigService extends BaseService
{
    public const TYPE_CHAT = 'chat';
    public const TYPE_EMBEDDING = 'embedding';
    public const TYPE_IMAGE = 'image';

    public const DRIVER_ALIYUN = 'aliyun';
    public const DRIVER_OPENAI = 'openai';
    public const DRIVER_OPENAI_COMPATIBLE = 'openai_compatible';

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return CsAiModel::class;
    }

    /**
     * 获取模型类型下拉选项
     */
    public function getTypeOptions(): array
    {
        return array_map(
            static fn(string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys($this->getTypeLabelMap()),
            array_values($this->getTypeLabelMap())
        );
    }

    /**
     * 获取服务商列表
     */
    public function getProviderList(
        string $type = '',
        string $keyword = '',
        $status = -1,
        int $page = 1,
        int $limit = 20,
        int $merchantId = 0
    ): array
    {
        $merchantId = max(0, $merchantId);
        $query = app()->make(CsAiProviderService::class)->getModel()->order('sort desc,id desc');
        $query->where('merchant_id', $merchantId);

        if ($this->isValidType($type)) {
            $query->where('type', $type);
        }

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('provider_name', 'like', '%' . $keyword . '%')
                    ->whereOr('provider_code', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '' && (int)$status >= 0) {
            $query->where('status', (int)$status);
        }

        $total = $query->count();
        $list = $query->page(max(1, $page), max(1, $limit))->select()->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 创建服务商
     */
    public function createProvider(array $data, int $merchantId = 0): CsAiProvider
    {
        $merchantId = max(0, $merchantId);
        $type = $this->normalizeType((string)($data['type'] ?? ''));
        $providerCode = trim((string)($data['provider_code'] ?? ''));
        $providerName = trim((string)($data['provider_name'] ?? ''));
        $driver = $this->normalizeDriver((string)($data['driver'] ?? ''));

        if ($providerCode === '') {
            throw new AdminException('服务商编码不能为空');
        }

        if ($providerName === '') {
            throw new AdminException('服务商名称不能为空');
        }

        $aiProvider = app()->make(CsAiProviderService::class);
        if ($aiProvider->where('merchant_id', $merchantId)->where('type', $type)->where('provider_code', $providerCode)->count() > 0) {
            throw new AdminException('同类型下服务商编码已存在');
        }

        /** @var CsAiProvider $provider */
        $provider = $aiProvider->create([
            'merchant_id' => $merchantId,
            'type' => $type,
            'provider_code' => $providerCode,
            'provider_name' => $providerName,
            'driver' => $driver,
            'base_url' => trim((string)($data['base_url'] ?? '')),
            'api_key' => trim((string)($data['api_key'] ?? '')),
            'is_builtin' => 0,
            'status' => $this->normalizeStatus((int)($data['status'] ?? 1)),
            'sort' => (int)($data['sort'] ?? 0),
        ]);

        return $provider;
    }

    /**
     * 更新服务商
     */
    public function updateProvider(int $id, array $data, int $merchantId = 0): bool
    {
        $merchantId = max(0, $merchantId);
        $provider = app()->make(CsAiProviderService::class)->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$provider) {
            throw new AdminException('服务商不存在');
        }

        $save = [];

        if ((int)$provider->is_builtin !== 1) {
            if (isset($data['type'])) {
                $save['type'] = $this->normalizeType((string)$data['type']);
            }
            if (isset($data['provider_code'])) {
                $providerCode = trim((string)$data['provider_code']);
                if ($providerCode === '') {
                    throw new AdminException('服务商编码不能为空');
                }

                $type = (string)($save['type'] ?? $provider->type);
                $exists = app()->make(CsAiProviderService::class)->getModel()->where('id', '<>', $id)
                    ->where('merchant_id', $merchantId)
                    ->where('type', $type)
                    ->where('provider_code', $providerCode)
                    ->count();
                if ($exists > 0) {
                    throw new AdminException('同类型下服务商编码已存在');
                }

                $save['provider_code'] = $providerCode;
            }
            if (isset($data['driver'])) {
                $save['driver'] = $this->normalizeDriver((string)$data['driver']);
            }
        }

        if (isset($data['provider_name'])) {
            $providerName = trim((string)$data['provider_name']);
            if ($providerName === '') {
                throw new AdminException('服务商名称不能为空');
            }
            $save['provider_name'] = $providerName;
        }

        if (array_key_exists('base_url', $data)) {
            $save['base_url'] = trim((string)$data['base_url']);
        }

        if (array_key_exists('api_key', $data)) {
            $save['api_key'] = trim((string)$data['api_key']);
        }

        if (isset($data['status'])) {
            $save['status'] = $this->normalizeStatus((int)$data['status']);
        }

        if (isset($data['sort'])) {
            $save['sort'] = (int)$data['sort'];
        }

        if ($save === []) {
            return true;
        }

        return (bool)$provider->save($save);
    }

    /**
     * 删除服务商
     */
    public function deleteProvider(int $id, int $merchantId = 0): bool
    {
        $merchantId = max(0, $merchantId);
        $provider = app()->make(CsAiProviderService::class)->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$provider) {
            throw new AdminException('服务商不存在');
        }

        if ($this->getModel()->where('provider_id', $id)->where('merchant_id', $merchantId)->count() > 0) {
            throw new AdminException('请先删除该服务商下的模型');
        }

        return (bool)$provider->delete();
    }

    /**
     * 更新服务商状态
     */
    public function updateProviderStatus(int $id, int $status, int $merchantId = 0): bool
    {
        $merchantId = max(0, $merchantId);
        $provider = app()->make(CsAiProviderService::class)->getModel()
            ->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$provider) {
            throw new AdminException('服务商不存在');
        }

        return (bool)$provider->save(['status' => $this->normalizeStatus($status)]);
    }

    /**
     * 获取模型列表
     */
    public function getModelList(
        string $type = '',
        int $providerId = 0,
        string $keyword = '',
        $status = -1,
        int $page = 1,
        int $limit = 20,
        int $merchantId = 0
    ): array {
        $merchantId = max(0, $merchantId);
        $query = $this->getModel()->alias('m')
            ->leftJoin('cs_ai_provider p', 'p.id = m.provider_id')
            ->field([
                'm.*',
                'p.provider_code',
                'p.provider_name',
                'p.driver',
                'p.base_url',
                'p.api_key',
                'p.status as provider_status',
            ])
            ->order('m.sort desc,m.id desc');
        $query->where('m.merchant_id', $merchantId)
            ->where('p.merchant_id', $merchantId);

        if ($this->isValidType($type)) {
            $query->where('m.type', $type);
        }

        if ($providerId > 0) {
            $query->where('m.provider_id', $providerId);
        }

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('m.model_name', 'like', '%' . $keyword . '%')
                    ->whereOr('m.model_id', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '' && (int)$status >= 0) {
            $query->where('m.status', (int)$status);
        }

        $total = $query->count();
        $list = $query->page(max(1, $page), max(1, $limit))->select()->toArray();

        foreach ($list as &$item) {
            $item['params'] = $this->normalizeParams($item['params'] ?? []);
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 创建模型
     */
    public function createModel(array $data, int $merchantId = 0)
    {
        $merchantId = max(0, $merchantId);
        $providerId = (int)($data['provider_id'] ?? 0);
        if ($providerId <= 0) {
            throw new AdminException('服务商不能为空');
        }

        $provider = app()->make(CsAiProviderService::class)->getModel()
            ->where('id', $providerId)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$provider) {
            throw new AdminException('服务商不存在');
        }

        $type = $this->normalizeType((string)($data['type'] ?? $provider->type));
        if ($type !== (string)$provider->type) {
            throw new AdminException('模型类型必须与服务商类型一致');
        }

        $modelId = trim((string)($data['model_id'] ?? ''));
        if ($modelId === '') {
            throw new AdminException('模型ID不能为空');
        }

        if ($this->getModel()->where('merchant_id', $merchantId)->where('type', $type)->where('provider_id', $providerId)->where('model_id', $modelId)->count() > 0) {
            throw new AdminException('该模型已存在');
        }

        $modelName = trim((string)($data['model_name'] ?? ''));
        $isDefault = (int)($data['is_default'] ?? 0) === 1 ? 1 : 0;

        if ($isDefault === 1) {
            $this->clearTypeDefault($type, $merchantId);
        }

        return $this->getModel()->create([
            'merchant_id' => $merchantId,
            'provider_id' => $providerId,
            'type' => $type,
            'model_id' => $modelId,
            'model_name' => $modelName !== '' ? $modelName : $modelId,
            'params' => json_encode($this->normalizeParams($data['params'] ?? []), JSON_UNESCAPED_UNICODE),
            'timeout' => max(1, (int)($data['timeout'] ?? 30)),
            'is_default' => $isDefault,
            'status' => $this->normalizeStatus((int)($data['status'] ?? 1)),
            'sort' => (int)($data['sort'] ?? 0),
        ]);
    }

    /**
     * 更新模型
     */
    public function updateModel(int $id, array $data, int $merchantId = 0): bool
    {
        $merchantId = max(0, $merchantId);
        $model = $this->getModel()->where('id', $id)->where('merchant_id', $merchantId)->find();
        if (!$model) {
            throw new AdminException('模型不存在');
        }

        $save = [];

        if (isset($data['provider_id'])) {
            $providerId = (int)$data['provider_id'];
            if ($providerId <= 0) {
                throw new AdminException('服务商不能为空');
            }

            $provider = app()->make(CsAiProviderService::class)->getModel()
                ->where('id', $providerId)
                ->where('merchant_id', $merchantId)
                ->find();
            if (!$provider) {
                throw new AdminException('服务商不存在');
            }

            if ((string)$provider->type !== (string)$model->type) {
                throw new AdminException('服务商类型与模型类型不一致');
            }

            $save['provider_id'] = $providerId;
        }

        if (isset($data['model_id'])) {
            $modelId = trim((string)$data['model_id']);
            if ($modelId === '') {
                throw new AdminException('模型ID不能为空');
            }
            $providerId = (int)($save['provider_id'] ?? $model->provider_id);
            $exists = $this->getModel()->where('id', '<>', $id)
                ->where('merchant_id', $merchantId)
                ->where('type', (string)$model->type)
                ->where('provider_id', $providerId)
                ->where('model_id', $modelId)
                ->count();
            if ($exists > 0) {
                throw new AdminException('该模型已存在');
            }

            $save['model_id'] = $modelId;
        }

        if (isset($data['model_name'])) {
            $modelName = trim((string)$data['model_name']);
            $save['model_name'] = $modelName !== '' ? $modelName : (string)($save['model_id'] ?? $model->model_id);
        }

        if (array_key_exists('params', $data)) {
            $save['params'] = json_encode($this->normalizeParams($data['params']), JSON_UNESCAPED_UNICODE);
        }

        if (isset($data['timeout'])) {
            $save['timeout'] = max(1, (int)$data['timeout']);
        }

        if (isset($data['status'])) {
            $save['status'] = $this->normalizeStatus((int)$data['status']);
        }

        if (isset($data['sort'])) {
            $save['sort'] = (int)$data['sort'];
        }

        if (isset($data['is_default']) && (int)$data['is_default'] === 1) {
            $this->clearTypeDefault((string)$model->type, $merchantId);
            $save['is_default'] = 1;
        }

        if ($save === []) {
            return true;
        }

        return (bool)$model->save($save);
    }

    /**
     * 删除模型
     */
    public function deleteModel(int $id, int $merchantId = 0): bool
    {
        $merchantId = max(0, $merchantId);
        $model = $this->getModel()->where('id', $id)->where('merchant_id', $merchantId)->find();
        if (!$model) {
            throw new AdminException('模型不存在');
        }

        return (bool)$model->delete();
    }

    /**
     * 更新模型状态
     */
    public function updateModelStatus(int $id, int $status, int $merchantId = 0): bool
    {
        $merchantId = max(0, $merchantId);
        $model = $this->getModel()->where('id', $id)->where('merchant_id', $merchantId)->find();
        if (!$model) {
            throw new AdminException('模型不存在');
        }

        return (bool)$model->save(['status' => $this->normalizeStatus($status)]);
    }

    /**
     * 设为默认模型
     */
    public function setDefaultModel(int $id, int $merchantId = 0): bool
    {
        $merchantId = max(0, $merchantId);
        $model = $this->getModel()->where('id', $id)->where('merchant_id', $merchantId)->find();
        if (!$model) {
            throw new AdminException('模型不存在');
        }

        $this->clearTypeDefault((string)$model->type, $merchantId);

        return (bool)$model->save(['is_default' => 1, 'status' => 1]);
    }

    /**
     * 解析指定类型默认模型运行时
     */
    public function resolveRuntime(string $type, int $merchantId = 0, string $modelId = ''): ?array
    {
        $type = $this->normalizeType($type);
        $merchantId = max(0, $merchantId);
        $modelId = trim($modelId);

        $record = $this->getModel()->alias('m')
            ->leftJoin('cs_ai_provider p', 'p.id = m.provider_id')
            ->field([
                'm.provider_id',
                'm.merchant_id',
                'm.type',
                'm.model_id',
                'm.params',
                'm.timeout',
                'p.provider_code',
                'p.provider_name',
                'p.driver',
                'p.base_url',
                'p.api_key',
            ])
            ->where('m.type', $type)
            ->where('m.merchant_id', $merchantId)
            ->where('p.merchant_id', $merchantId)
            ->where('m.status', 1)
            ->where('p.status', 1)
            ->when($modelId !== '', function ($query) use ($modelId) {
                $query->where('m.model_id', $modelId);
            }, function ($query) {
                $query->where('m.is_default', 1);
            })
            ->find();

        if (!$record) {
            return null;
        }

        $params = $this->normalizeParams($record['params'] ?? []);

        return [
            'provider' => (string)$record['driver'],
            'options' => [
                'enable_config_fallback' => false,
                'api_key' => (string)($record['api_key'] ?? ''),
                'model' => (string)($record['model_id'] ?? ''),
                'base_url' => (string)($record['base_url'] ?? ''),
                'params' => $params,
                'timeout' => max(1, (int)($record['timeout'] ?? 30)),
            ],
            'provider_id' => (int)($record['provider_id'] ?? 0),
            'merchant_id' => (int)($record['merchant_id'] ?? 0),
            'provider_code' => (string)($record['provider_code'] ?? ''),
            'provider_name' => (string)($record['provider_name'] ?? ''),
            'model_id' => (string)($record['model_id'] ?? ''),
            'type' => $type,
        ];
    }

    /**
     * 清空同类型默认模型标记
     */
    private function clearTypeDefault(string $type, int $merchantId = 0): void
    {
        $this->getModel()->where('merchant_id', max(0, $merchantId))->where('type', $type)->update(['is_default' => 0]);
    }

    /**
     * 规范化类型
     */
    private function normalizeType(string $type): string
    {
        if (!$this->isValidType($type)) {
            throw new AdminException('类型仅支持 chat、embedding、image');
        }

        return $type;
    }

    /**
     * 类型是否合法
     */
    private function isValidType(string $type): bool
    {
        return in_array($type, array_keys($this->getTypeLabelMap()), true);
    }

    /**
     * 获取模型类型标签映射
     */
    private function getTypeLabelMap(): array
    {
        return [
            self::TYPE_CHAT => '对话',
            self::TYPE_EMBEDDING => '向量',
            self::TYPE_IMAGE => '图片',
        ];
    }

    /**
     * 规范化驱动
     */
    private function normalizeDriver(string $driver): string
    {
        if (!in_array($driver, [self::DRIVER_ALIYUN, self::DRIVER_OPENAI, self::DRIVER_OPENAI_COMPATIBLE], true)) {
            throw new AdminException('驱动仅支持 aliyun/openai/openai_compatible');
        }

        return $driver;
    }

    /**
     * 规范化状态
     */
    private function normalizeStatus(int $status): int
    {
        if (!in_array($status, [0, 1], true)) {
            throw new AdminException('状态值不正确');
        }

        return $status;
    }

    /**
     * 规范化参数
     */
    private function normalizeParams(mixed $params): array
    {
        if (is_array($params)) {
            return $params;
        }

        if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
