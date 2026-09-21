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

use app\common\model\store\ProductCategoryRelation;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 商品分类关联服务
 *
 * 店铺商品与商户自定义分类关联（category type=10）。
 * 用于商户后台和移动端商户店铺分类筛选；不改变平台商品分类语义。
 */
class ProductCategoryRelationService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return ProductCategoryRelation::class;
    }

    /**
     * 按商户自定义分类获取关联的店铺商品ID列表
     *
     * @param int $categoryId 分类ID
     * @return array 店铺商品ID列表
     */
    public function getProductIdsByCategoryId(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $this->getModel()
            ->where('category_id', $categoryId)
            ->column('store_product_id')))));
    }

    /**
     * 通过有界分类集合筛选商品查询，不将全部关联商品ID加载到内存。
     *
     * @param object $query 已限制当前商户的商品查询对象。
     * @param array<int> $categoryIds 已由商户展示服务校验归属的分类及子级ID。
     * @param string $productTable 商品模型表名。
     * @return void 无返回值。
     */
    public function applyCategoryFilter(object $query, array $categoryIds, string $productTable): void
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), fn(int $id): bool => $id > 0)));
        if ($categoryIds === []) {
            $query->where('id', 0);
            return;
        }
        $relationTable = $this->getModel()->getTable();
        $query->whereExists(function ($subQuery) use ($categoryIds, $relationTable, $productTable): void {
            $subQuery->table($relationTable)
                ->whereColumn($relationTable . '.store_product_id', $productTable . '.id')
                ->whereIn($relationTable . '.category_id', $categoryIds)
                ->whereNull($relationTable . '.delete_time');
        });
    }

    /**
     * 按商品获取关联分类 ID 列表。
     *
     * @param int $productId 店铺商品ID
     * @return array<int> 分类ID列表
     */
    public function getCategoryIdsByProductId(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $this->getModel()
            ->where('store_product_id', $productId)
            ->column('category_id')))));
    }

    /**
     * 保存店铺商品与商户自定义分类关联（整体替换）
     *
     * @param int $productId 店铺商品ID
     * @param array $categoryIds 分类ID列表
     * @param int $ownerId 商户ID
     * @return void 无返回值。
     */
    public function replaceRelations(int $productId, array $categoryIds, int $ownerId = 0): void
    {
        $oldRelations = $this->getModel()
            ->withTrashed()
            ->where('store_product_id', $productId)
            ->select();
        foreach ($oldRelations as $oldRelation) {
            $oldRelation->force()->delete();
        }

        $categoryIds = $ownerId > 0
            ? app()->make(ProductCategoryService::class)->assertMerchantCategories($categoryIds, $ownerId)
            : array_values(array_unique(array_filter(array_map('intval', $categoryIds))));

        $sort = 100;
        foreach ($categoryIds as $index => $categoryId) {
            if ($categoryId <= 0) {
                continue;
            }
            $this->getModel()->create([
                'store_product_id' => $productId,
                'category_id' => $categoryId,
                'sort' => $sort - $index,
            ]);
        }
    }
}
