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

use app\common\model\store\StoreProductParamIndex;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 店铺商品参数索引服务。
 */
class StoreProductParamIndexService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return StoreProductParamIndex::class;
    }

    /**
     * 按商品重建参数索引。
     *
     * @param int $productId 商品 ID
     * @param int $merchantId 商户 ID
     * @param int $categoryId 分类 ID
     * @param int $templateId 模板 ID
     * @param array<int, array<string, mixed>> $paramSnapshot 参数快照
     * @return void
     */
    public function syncByProductId(int $productId, int $merchantId, int $categoryId, int $templateId, array $paramSnapshot): void
    {
        if ($productId <= 0) {
            return;
        }

        $this->deleteByProductId($productId, $merchantId);
        $rows = $this->buildIndexRows($productId, $merchantId, $categoryId, $templateId, $paramSnapshot);
        if ($rows === []) {
            return;
        }

        $this->transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->create($row);
            }
        });
    }

    /**
     * 按商品删除索引。
     *
     * @param int $productId 商品 ID
     * @param int $merchantId 商户 ID
     * @return void
     */
    public function deleteByProductId(int $productId, int $merchantId = 0): void
    {
        if ($productId <= 0) {
            return;
        }

        $query = $this->getModel()->where('store_product_id', $productId);
        if ($merchantId >= 0) {
            $query->where('merchant_id', $merchantId);
        }
        $query->delete();
    }

    /**
     * 将参数筛选条件直接应用到商品查询上。
     *
     * @param array<int, array<string, mixed>> $paramFilters 参数条件
     * @param object $query 商品查询对象
     * @param string $productTable 商品表名
     * @param int $merchantId 商户 ID，0=不限制
     * @param int $categoryId 分类 ID，0=不限制
     * @return void
     */
    public function applyFilters(object $query, array $paramFilters, string $productTable, int $merchantId = 0, int $categoryId = 0): void
    {
        $groups = $this->normalizeFilters($paramFilters);
        if ($groups === []) {
            return;
        }

        $indexTable = $this->getModel()->getTable();
        foreach ($groups as $group) {
            $query->whereExists(function ($subQuery) use ($group, $indexTable, $productTable, $merchantId, $categoryId): void {
                $subQuery->table($indexTable)
                    ->whereColumn($indexTable . '.store_product_id', $productTable . '.id')
                    ->where($indexTable . '.status', 1)
                    ->whereNull($indexTable . '.delete_time')
                    ->where($indexTable . '.param_key', $group['param_key'])
                    ->whereIn($indexTable . '.value_key', $group['values']);
                if ($group['attribute_id'] > 0) {
                    $subQuery->where($indexTable . '.attribute_id', $group['attribute_id']);
                }
                if ($merchantId > 0) {
                    $subQuery->where($indexTable . '.merchant_id', $merchantId);
                }
                if ($categoryId > 0) {
                    $subQuery->where($indexTable . '.category_id', $categoryId);
                }
            });
        }
    }

    /**
     * 按参数条件过滤商品 ID。
     *
     * 仅保留兼容入口，不再用于分页主查询。
     *
     * @param array<int, array<string, mixed>> $paramFilters 参数条件
     * @param int $merchantId 商户 ID，0=不限制
     * @param int $categoryId 分类 ID，0=不限制
     * @return array<int>
     */
    public function getProductIdsByFilters(array $paramFilters, int $merchantId = 0, int $categoryId = 0): array
    {
        $groups = $this->normalizeFilters($paramFilters);
        if ($groups === []) {
            return [];
        }

        $query = $this->getModel()
            ->where('status', 1)
            ->whereNull('delete_time');
        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $query->where(function ($subQuery) use ($groups) {
            foreach ($groups as $group) {
                $subQuery->whereExists(function ($existsQuery) use ($group): void {
                    $indexTable = $this->getModel()->getTable();
                    $existsQuery->table($indexTable)
                        ->whereColumn($indexTable . '.store_product_id', 'store_product.id')
                        ->where($indexTable . '.status', 1)
                        ->whereNull($indexTable . '.delete_time')
                        ->where($indexTable . '.param_key', $group['param_key'])
                        ->whereIn($indexTable . '.value_key', $group['values']);
                    if ($group['attribute_id'] > 0) {
                        $existsQuery->where($indexTable . '.attribute_id', $group['attribute_id']);
                    }
                });
            }
        });

        return array_values(array_unique(array_filter(array_map('intval', $query->column('store_product_id')))));
    }

    /**
     * 构建索引行。
     *
     * @param int $productId 商品 ID
     * @param int $merchantId 商户 ID
     * @param int $categoryId 分类 ID
     * @param int $templateId 模板 ID
     * @param array<int, array<string, mixed>> $paramSnapshot 参数快照
     * @return array<int, array<string, mixed>>
     */
    protected function buildIndexRows(int $productId, int $merchantId, int $categoryId, int $templateId, array $paramSnapshot): array
    {
        $rows = [];
        foreach ($paramSnapshot as $index => $param) {
            if (!is_array($param)) {
                continue;
            }
            $paramKey = trim((string) ($param['row_key'] ?? $param['key'] ?? ''));
            if ($paramKey === '') {
                continue;
            }
            $paramName = trim((string) ($param['name'] ?? ''));
            $selectedValues = array_values(array_unique(array_filter(array_map('trim', (array) ($param['selected_values'] ?? [])))));
            foreach ($selectedValues as $valueIndex => $value) {
                $rows[] = [
                    'store_product_id' => $productId,
                    'merchant_id' => $merchantId,
                    'category_id' => $categoryId,
                    'attribute_id' => $templateId,
                    'param_key' => $paramKey,
                    'param_name' => $paramName,
                    'param_value' => $value,
                    'value_key' => $this->normalizeValueKey($value),
                    'sort' => (int) ($param['sort'] ?? 0) - $valueIndex,
                    'status' => 1,
                ];
            }
        }

        return $rows;
    }

    /**
     * 归一化前端筛选条件。
     *
     * @param array<int, array<string, mixed>> $paramFilters 参数条件
     * @return array<int, array{attribute_id:int,param_key:string,values:array<int,string>}>
     */
    protected function normalizeFilters(array $paramFilters): array
    {
        $groups = [];
        foreach ($paramFilters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $attributeId = (int) ($filter['attribute_id'] ?? $filter['template_id'] ?? 0);
            $paramKey = trim((string) ($filter['param_key'] ?? $filter['key'] ?? ''));
            $values = array_values(array_unique(array_filter(array_map('trim', (array) ($filter['values'] ?? [])))));
            if ($paramKey === '' || $values === []) {
                continue;
            }

            $groupKey = $attributeId > 0 ? $attributeId . ':' . $paramKey : $paramKey;
            $groups[$groupKey] = [
                'attribute_id' => $attributeId,
                'param_key' => $paramKey,
                'values' => array_map(fn(string $value): string => $this->normalizeValueKey($value), $values),
            ];
        }

        return array_values($groups);
    }

    /**
     * 标准化值检索键。
     */
    protected function normalizeValueKey(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower($value);
    }
}
