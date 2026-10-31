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
use app\common\model\store\ProductAttributeCategoryRelation;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 商品参数模板分类绑定服务。
 */
class ProductAttributeCategoryRelationService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return ProductAttributeCategoryRelation::class;
    }

    /**
     * 按模板获取分类 ID 列表。
     *
     * @param int $templateId 模板 ID
     * @return array<int>
     */
    public function getCategoryIdsByTemplateId(int $templateId): array
    {
        if ($templateId <= 0) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $this->getModel()
            ->where('template_id', $templateId)
            ->column('category_id')))));
    }

    /**
     * 按分类获取模板 ID。
     *
     * @param int $categoryId 分类 ID
     * @param int $merchantId 商户 ID
     * @return int 模板 ID
     */
    public function getTemplateIdByCategoryId(int $categoryId, int $merchantId = 0): int
    {
        if ($categoryId <= 0) {
            return 0;
        }

        $templateId = (int) $this->getModel()
            ->where('category_id', $categoryId)
            ->where('merchant_id', $merchantId)
            ->whereNull('delete_time')
            ->value('template_id');
        if ($templateId > 0 || $merchantId <= 0) {
            return $templateId;
        }

        return (int) $this->getModel()
            ->where('category_id', $categoryId)
            ->where('merchant_id', 0)
            ->whereNull('delete_time')
            ->value('template_id');
    }

    /**
     * 整体替换模板绑定分类。
     *
     * @param int $templateId 模板 ID
     * @param array<int|string> $categoryIds 分类 ID 列表
     * @param int $merchantId 商户 ID
     * @return void
     */
    public function replaceBindings(int $templateId, array $categoryIds, int $merchantId = 0): void
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        sort($categoryIds);

        $rows = $this->getModel()->withTrashed()
            ->where('template_id', $templateId)
            ->select();
        foreach ($rows as $row) {
            $row->force()->delete();
        }

        if ($categoryIds === []) {
            return;
        }

        $categoryService = app()->make(ProductCategoryService::class);
        foreach ($categoryIds as $categoryId) {
            $categoryService->assertPlatformCategory($categoryId);
        }

        $conflictIds = $this->getModel()
            ->where('merchant_id', $merchantId)
            ->whereIn('category_id', $categoryIds)
            ->where('template_id', '<>', $templateId)
            ->column('category_id');
        $conflictIds = array_values(array_unique(array_filter(array_map('intval', $conflictIds))));
        if ($conflictIds !== []) {
            throw new AdminResponseException('所选分类已绑定其他商品参数模板');
        }

        foreach ($categoryIds as $index => $categoryId) {
            $this->getModel()->create([
                'template_id' => $templateId,
                'merchant_id' => $merchantId,
                'category_id' => $categoryId,
                'sort' => 100 - $index,
            ]);
        }
    }
}
