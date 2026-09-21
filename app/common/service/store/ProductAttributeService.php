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

namespace app\common\service\store;

use app\common\exception\AdminResponseException;
use app\common\model\store\ProductAttribute;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 商品参数模板服务。
 */
class ProductAttributeService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return ProductAttribute::class;
    }

    /**
     * 模板资源列表。
     *
     * @param array $params 搜索参数
     * @return array
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], array|string|null $sort = null)
    {
        $merchantId = (int) ($params['_merchant_id'] ?? 0);
        $query = $this->getModel()->order('sort desc, id desc');
        if ($merchantId > 0) {
            $query->where(function ($subQuery) use ($merchantId) {
                $subQuery->where('merchant_id', 0)->whereOr('merchant_id', $merchantId);
            });
        }
        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . trim((string) $params['name']) . '%');
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
        if (isset($params['is_required']) && $params['is_required'] !== '') {
            $query->where('is_required', (int) $params['is_required']);
        }

        [$page, $limit] = $this->getPagesValue();
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $list = $this->attachTemplateMeta($list, $merchantId);

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 模板详情。
     */
    public function resourceRead(int|array $id, int $merchantId = 0)
    {
        $row = $this->get((int) $id);
        if (!$row) {
            throw new AdminResponseException('商品参数模板不存在');
        }

        $data = $row->toArray();
        if (!$this->isVisibleToMerchant((int) ($data['merchant_id'] ?? 0), $merchantId)) {
            throw new AdminResponseException('商品参数模板不存在或无权查看');
        }

        return $this->hydrateTemplate($data, $merchantId);
    }

    /**
     * 新增模板。
     */
    public function resourceSave(array $data, int $merchantId = 0)
    {
        return $this->transaction(function () use ($data, $merchantId) {
            $payload = $this->normalizeTemplatePayload($data, $merchantId);
            $created = $this->create([
                'merchant_id' => $payload['merchant_id'],
                'name' => $payload['name'],
                'is_required' => $payload['is_required'],
                'sort' => $payload['sort'],
                'status' => $payload['status'],
                'params_json' => $this->encodeParamsJson($payload['params_json']),
            ]);
            $templateId = (int) ($created->id ?? 0);
            $this->saveCategoryBindings($templateId, $payload['category_ids'] ?? [], $merchantId);
            return $this->resourceRead($templateId, $merchantId);
        });
    }

    /**
     * 修改模板。
     */
    public function resourceUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $templateId = (int) $id;
        $this->assertOwned($templateId, $merchantId);

        return $this->transaction(function () use ($templateId, $data, $merchantId) {
            $payload = $this->normalizeTemplatePayload($data, $merchantId);
            $this->update($templateId, [
                'merchant_id' => $payload['merchant_id'],
                'name' => $payload['name'],
                'is_required' => $payload['is_required'],
                'sort' => $payload['sort'],
                'status' => $payload['status'],
                'params_json' => $this->encodeParamsJson($payload['params_json']),
            ]);
            $this->saveCategoryBindings($templateId, $payload['category_ids'] ?? [], $merchantId);
            return true;
        });
    }

    /**
     * 删除模板。
     */
    public function resourceDelete(int $id, int $merchantId = 0)
    {
        $this->assertOwned($id, $merchantId);
        return $this->transaction(function () use ($id, $merchantId) {
            app()->make(ProductAttributeCategoryRelationService::class)
                ->getModel()
                ->where('template_id', $id)
                ->delete();
            $this->getModel()->where('id', $id)->delete();
            return true;
        });
    }

    /**
     * 修改状态。
     */
    public function resourceShowUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertOwned((int) $id, $merchantId);
        return $this->update($id, $data);
    }

    /**
     * 选项列表。
     */
    public function options(int $merchantId = 0): array
    {
        $query = $this->getModel()
            ->where('status', 1)
            ->order('sort desc, id desc');
        if ($merchantId > 0) {
            $query->where(function ($subQuery) use ($merchantId) {
                $subQuery->where('merchant_id', 0)->whereOr('merchant_id', $merchantId);
            });
        }

        $list = $query->field(['id', 'name', 'merchant_id', 'status', 'is_required', 'sort', 'params_json'])
            ->select()
            ->toArray();

        return $this->attachTemplateMeta($list, $merchantId);
    }

    /**
     * 按分类获取模板。
     */
    public function findByCategoryId(int $categoryId, int $merchantId = 0): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $relationService = app()->make(ProductAttributeCategoryRelationService::class);
        $templateId = $relationService->getTemplateIdByCategoryId($categoryId, $merchantId);
        if ($templateId <= 0) {
            return [];
        }

        return $this->resourceRead($templateId, $merchantId);
    }

    /**
     * 保存分类关系。
     *
     * @param int $templateId 模板ID
     * @param array<int|string> $categoryIds 分类ID列表
     * @param int $merchantId 商户ID
     * @return void
     */
    protected function saveCategoryBindings(int $templateId, array $categoryIds, int $merchantId = 0): void
    {
        app()->make(ProductAttributeCategoryRelationService::class)
            ->replaceBindings($templateId, $categoryIds, $merchantId);
    }

    /**
     * 归一化模板写入数据。
     */
    protected function normalizeTemplatePayload(array $data, int $merchantId = 0): array
    {
        $categoryIds = is_array($data['category_ids'] ?? null) ? $data['category_ids'] : [];
        $params = $this->normalizeParamsSource($data['params'] ?? ($data['params_json'] ?? []));

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new AdminResponseException('请输入商品参数模板名称');
        }
        $params = $this->normalizeParams($params);
        if ($params === []) {
            throw new AdminResponseException('请至少添加一条商品参数');
        }

        $payload = [
            'merchant_id' => $merchantId,
            'name' => $name,
            'is_required' => (int) ($data['is_required'] ?? 0),
            'sort' => max(0, (int) ($data['sort'] ?? 0)),
            'status' => (int) ($data['status'] ?? 1),
            'params_json' => $params,
            'category_ids' => array_values(array_unique(array_filter(array_map('intval', $categoryIds)))),
        ];

        return $payload;
    }

    /**
     * 标准化参数 JSON。
     *
     * @param array<int, array<string, mixed>> $params 参数项
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeParams(array $params): array
    {
        $rows = [];
        $usedKeys = [];
        foreach ($params as $index => $param) {
            if (!is_array($param)) {
                continue;
            }

            $name = trim((string) ($param['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $values = is_array($param['values'] ?? null) ? $param['values'] : [];
            $values = array_values(array_unique(array_filter(array_map(static function ($value): string {
                return trim((string) $value);
            }, $values))));
            if ($values === []) {
                continue;
            }

            $rowKey = trim((string) ($param['row_key'] ?? $param['key'] ?? ''));
            if ($rowKey === '' || isset($usedKeys[$rowKey])) {
                $rowKey = $this->generateParamKey($name, $index, $usedKeys);
            }
            $usedKeys[$rowKey] = true;

            $rows[] = [
                'row_key' => $rowKey,
                'name' => $name,
                'is_required' => (int) ($param['is_required'] ?? 0),
                'sort' => max(0, (int) ($param['sort'] ?? 0)),
                'status' => (int) ($param['status'] ?? 1),
                'values' => $values,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $sort = (int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0);
            return $sort !== 0 ? $sort : strcmp((string) ($left['row_key'] ?? ''), (string) ($right['row_key'] ?? ''));
        });

        return array_values($rows);
    }

    /**
     * 生成参数标识。
     */
    protected function generateParamKey(string $name, int $index, array $usedKeys): string
    {
        $base = 'param_' . strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $name) ?? '')) . '_' . ($index + 1);
        $base = trim($base, '_');
        if ($base === 'param_' || $base === '') {
            $base = 'param_' . ($index + 1);
        }

        $candidate = $base;
        $suffix = 1;
        while (isset($usedKeys[$candidate])) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * 附加模板元数据。
     *
     * @param array<int, array<string, mixed>> $list 模板列表
     * @param int $merchantId 商户ID
     * @return array<int, array<string, mixed>>
     */
    protected function attachTemplateMeta(array $list, int $merchantId = 0): array
    {
        if ($list === []) {
            return [];
        }

        $relationService = app()->make(ProductAttributeCategoryRelationService::class);
        $categoryService = app()->make(ProductCategoryService::class);
        foreach ($list as &$item) {
            $templateId = (int) ($item['id'] ?? 0);
            $item['params'] = $this->normalizeParamsSource($item['params_json'] ?? []);
            $item['params_count'] = count($item['params']);
            $categoryIds = $relationService->getCategoryIdsByTemplateId($templateId);
            $item['category_ids'] = $categoryIds;
            $item['category_names'] = implode('、', array_values($categoryService->getCategoryNameMapByIds($categoryIds, 0)));
            $item['is_visible_to_merchant'] = $this->isVisibleToMerchant((int) ($item['merchant_id'] ?? 0), $merchantId);
        }
        unset($item);

        return $list;
    }

    /**
     * 还原模板详情。
     */
    protected function hydrateTemplate(array $data, int $merchantId = 0): array
    {
        $templateId = (int) ($data['id'] ?? 0);
        $data['params'] = $this->normalizeParamsSource($data['params_json'] ?? []);
        $data['params_count'] = count($data['params']);
        $categoryIds = app()->make(ProductAttributeCategoryRelationService::class)->getCategoryIdsByTemplateId($templateId);
        $data['category_ids'] = $categoryIds;
        $data['category_names'] = implode('、', array_values(app()->make(ProductCategoryService::class)->getCategoryNameMapByIds($categoryIds, 0)));
        $data['is_visible_to_merchant'] = $this->isVisibleToMerchant((int) ($data['merchant_id'] ?? 0), $merchantId);
        return $data;
    }

    /**
     * 标准化已存储的参数 JSON。
     *
     * @param mixed $params 原始参数 JSON
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeParamsSource(mixed $params): array
    {
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($params)) {
            $params = [];
        }

        return $this->normalizeParams($params);
    }

    /**
     * 编码参数 JSON。
     *
     * @param array<int, array<string, mixed>> $params 参数项
     * @return string|null
     */
    protected function encodeParamsJson(array $params): ?string
    {
        $json = json_encode($params, JSON_UNESCAPED_UNICODE);
        return $json === false ? null : $json;
    }

    /**
     * 校验并获取模板归属。
     */
    protected function assertOwned(int $id, int $merchantId = 0): void
    {
        if ($merchantId <= 0) {
            return;
        }

        $ownerId = (int) $this->getModel()->where('id', $id)->value('merchant_id');
        if ($ownerId !== $merchantId) {
            throw new AdminResponseException('无权操作该商品参数模板');
        }
    }

    protected function isVisibleToMerchant(int $ownerId, int $merchantId): bool
    {
        return $merchantId <= 0 || $ownerId === 0 || $ownerId === $merchantId;
    }
}
