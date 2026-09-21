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

use app\common\exception\AdminException;
use app\common\exception\AdminResponseException;
use app\common\service\setting\CategoryService;
use think\facade\Db;

/**
 * 商品分类服务
 *
 * 复用通用 category 表（type=10）。owner_id=0 为平台分类，owner_id=merchant_id 为商户自定义分类。
 */
class ProductCategoryService extends CategoryService
{
    private const MOBILE_TREE_CACHE_PREFIX = 'mobile_product_category_tree:';

    /**
     * 分类类型：商品分类
     *
     * @var int
     */
    protected int $categoryType = CategoryService::PRODUCT_TYPE;

    /**
     * 最大分类层级（最多三级）
     *
     * @var int
     */
    protected int $maxLevel = 3;

    /**
     * 分类资源列表
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        [$page, $limit] = $this->getPagesValue();
        $ownerId = (int) ($params['_owner_id'] ?? $params['owner_id'] ?? 0);

        $query = $this->getModel()->where([
            'type'     => $this->categoryType,
            'owner_id' => $ownerId,
        ]);
        if (($params['name'] ?? '') !== '') {
            $query->whereLike('name', '%' . $params['name'] . '%');
        }
        if (($params['pid'] ?? '') !== '') {
            $query->where('pid', (int) $params['pid']);
        }
        if (($params['status'] ?? '') !== '') {
            $query->where('status', (int) $params['status']);
        }

        $count = $query->count();
        $list = $query->page($page, $limit)->order('sort desc,id desc')->select()->toArray();

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 添加分类（最多三级校验）
     *
     * @param array $data 分类数据
     * @param int $ownerId 归属方ID（0=平台，商户ID=商户自定义分类）
     * @return \think\model\contract\Modelable
     */
    public function resourceSave(array $data, int $ownerId = 0)
    {
        $data = $this->normalizeWriteData($data, $ownerId);
        $this->validateCategoryLevel((int)($data['pid'] ?? 0), 0, $ownerId);
        $result = parent::resourceSave($data);
        $this->clearMobileTreeCache();
        return $result;
    }

    /**
     * 分类详情
     */
    public function resourceRead(int|array $id, int $ownerId = 0)
    {
        return $this->assertOwned((int) $id, $ownerId)->toArray();
    }

    /**
     * 修改分类（最多三级校验，含移动后子树越界校验）
     *
     * @param int|array $id 分类ID
     * @param array $data 分类数据
     * @param int $ownerId 归属方ID
     * @return bool
     */
    public function resourceUpdate(int|array $id, array $data, int $ownerId = 0)
    {
        if (is_int($id) && $id > 0) {
            $this->assertOwned($id, $ownerId);
            $data = $this->normalizeWriteData($data, $ownerId);
            $this->validateCategoryLevel((int)($data['pid'] ?? 0), $id, $ownerId);
        }
        $result = parent::resourceUpdate($id, $data);
        $this->clearMobileTreeCache();
        return $result;
    }

    /**
     * 修改状态（启用/停用）后清理缓存。
     *
     * @param int|array $id 分类 ID。
     * @param array $data 状态数据。
     * @param int $ownerId 归属方ID
     * @return true
     */
    public function resourceShowUpdate(int|array $id, array $data, int $ownerId = 0)
    {
        if (is_int($id)) {
            $this->assertOwned($id, $ownerId);
        }
        $result = parent::resourceShowUpdate($id, $data);
        $this->clearMobileTreeCache();
        return $result;
    }

    /**
     * 删除分类（级联软删全部子分类，避免孤儿节点）
     *
     * @param int $id 分类ID
     * @param int $ownerId 归属方ID
     * @return bool
     */
    public function resourceDelete(int $id, int $ownerId = 0): bool
    {
        $this->assertOwned($id, $ownerId);
        $result = Db::transaction(function () use ($id, $ownerId) {
            $ids = $this->collectChildIds($id, $ownerId);
            foreach ($ids as $cid) {
                parent::resourceDelete($cid);
            }
            return true;
        });
        $this->clearMobileTreeCache();
        return $result;
    }

    /**
     * 校验平台分类可作为商品 category_id 使用。
     *
     * @throws AdminResponseException
     */
    public function assertPlatformCategory(int $categoryId): void
    {
        if ($categoryId <= 0) {
            throw new AdminResponseException('请选择平台分类');
        }

        $exists = $this->getModel()
            ->where([
                'id'       => $categoryId,
                'type'     => $this->categoryType,
                'owner_id' => 0,
                'status'   => 1,
            ])
            ->find();
        if (!$exists) {
            throw new AdminResponseException('平台分类不存在或已停用');
        }
    }

    /**
     * 校验并归一化商户分类 ID。
     *
     * @param array<int|string> $categoryIds 分类ID
     * @param int $ownerId 商户ID
     * @return array<int>
     * @throws AdminResponseException
     */
    public function assertMerchantCategories(array $categoryIds, int $ownerId): array
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);
        if ($categoryIds === []) {
            return [];
        }
        if ($ownerId <= 0) {
            throw new AdminResponseException('商户信息不存在');
        }

        $ownedIds = $this->getModel()
            ->where([
                'type'     => $this->categoryType,
                'owner_id' => $ownerId,
            ])
            ->whereIn('id', $categoryIds)
            ->column('id');
        $ownedIds = array_values(array_unique(array_map('intval', $ownedIds)));
        sort($ownedIds);

        if ($ownedIds !== $categoryIds) {
            throw new AdminResponseException('商户分类不存在或无权使用');
        }

        return $categoryIds;
    }

    /**
     * 校验分类层级不超过三级
     *
     * @param int $parentId 父级分类ID（0=顶级）
     * @param int $id 自身分类ID（0=新增）
     * @param int $ownerId 归属方ID
     * @return void
     * @throws AdminException
     */
    protected function validateCategoryLevel(int $parentId, int $id = 0, int $ownerId = 0): void
    {
        if ($parentId > 0) {
            $this->assertOwned($parentId, $ownerId);
        }

        $parentDepth = $parentId > 0 ? $this->getNodeDepth($parentId, $ownerId) : 0;
        $newDepth = $parentDepth + 1;
        $belowLevels = 0;
        if ($id > 0) {
            $belowLevels = $this->getSubtreeMaxDepth($id, $ownerId) - $this->getNodeDepth($id, $ownerId);
        }
        if ($newDepth + $belowLevels > $this->maxLevel) {
            throw new AdminException('商品分类最多支持三级分类');
        }
    }

    /**
     * 获取分类从顶级起的深度（顶级=1）
     *
     * @param int $id 分类ID
     * @param int $ownerId 归属方ID
     * @return int
     */
    protected function getNodeDepth(int $id, int $ownerId = 0): int
    {
        $depth = 0;
        $current = $id;
        // 防御性上限，避免脏数据循环死循环
        while ($current > 0 && $depth < 20) {
            $depth++;
            $current = (int) $this->getModel()
                ->where([
                    'id'       => $current,
                    'type'     => $this->categoryType,
                    'owner_id' => $ownerId,
                ])
                ->value('pid');
        }
        return $depth;
    }

    /**
     * 获取分类子树内最大深度
     *
     * @param int $id 分类ID（含自身）
     * @param int $ownerId 归属方ID
     * @return int
     */
    protected function getSubtreeMaxDepth(int $id, int $ownerId = 0): int
    {
        $maxDepth = $this->getNodeDepth($id, $ownerId);
        foreach ($this->collectChildIds($id, $ownerId) as $cid) {
            $maxDepth = max($maxDepth, $this->getNodeDepth($cid, $ownerId));
        }
        return $maxDepth;
    }

    /**
     * 递归收集子孙分类ID（含自身）
     *
     * @param int $id 分类ID
     * @param int $ownerId 归属方ID
     * @return int[]
     */
    protected function collectChildIds(int $id, int $ownerId = 0): array
    {
        $ids = [$id];
        $children = $this->getModel()
            ->where([
                'type'     => $this->categoryType,
                'pid'      => $id,
                'owner_id' => $ownerId,
            ])
            ->column('id');
        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->collectChildIds((int) $childId, $ownerId));
        }
        return $ids;
    }

    /**
     * 获取分类自身及全部子孙分类 ID。
     *
     * @param int $id 分类 ID
     * @param int $ownerId 归属方 ID
     * @return array<int>
     */
    public function getCategoryAndChildIds(int $id, int $ownerId = 0): array
    {
        if ($id <= 0) {
            return [];
        }

        $rows = $this->getModel()
            ->where([
                'type'     => $this->categoryType,
                'owner_id' => $ownerId,
            ])
            ->field('id,pid')
            ->select()
            ->toArray();
        if (!$rows) {
            return [];
        }

        $exists = false;
        $childrenMap = [];
        foreach ($rows as $row) {
            $categoryId = (int)($row['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            if ($categoryId === $id) {
                $exists = true;
            }

            $parentId = (int)($row['pid'] ?? 0);
            $childrenMap[$parentId][] = $categoryId;
        }
        if (!$exists) {
            return [];
        }

        $ids = [];
        $stack = [$id];
        while ($stack) {
            $categoryId = (int)array_pop($stack);
            if ($categoryId <= 0 || isset($ids[$categoryId])) {
                continue;
            }

            $ids[$categoryId] = $categoryId;
            foreach ($childrenMap[$categoryId] ?? [] as $childId) {
                $stack[] = (int)$childId;
            }
        }

        return array_values($ids);
    }

    /**
     * 获取商品分类树（平台/商户维度）
     *
     * @param int $ownerId 归属方ID（0=平台）
     * @return array 树形分类数据
     */
    public function getProductCategoryTree(int $ownerId = 0, array $categoryIds = []): array
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);
        return $this->getCategoryTree($this->categoryType, $ownerId, $categoryIds);
    }

    /**
     * 获取商品分类树（平台/商户维度）缓存
     */
    public function getProductCategoryTreeCache(int $ownerId = 0, array $categoryIds = []): array
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);
        $cacheKey = $this->getMobileTreeCacheKey($ownerId, $categoryIds);

        return $this->cacheTag()->remember($cacheKey, function () use ($ownerId, $categoryIds) {
            return $this->getCategoryTree($this->categoryType, $ownerId, $categoryIds);
        });
    }

    /**
     * 获取商品分类列表
     *
     * @param array $where 额外条件
     * @param int $ownerId 归属方ID
     * @return array 分类列表（id/name）
     */
    public function getProductCategoryList(array $where = [], int $ownerId = 0): array
    {
        $where['owner_id'] = $ownerId;
        return $this->getCategoryList($where);
    }

    /**
     * 根据分类 ID 列表获取名称映射。
     *
     * @param array<int> $categoryIds 分类 ID 列表
     * @param int $ownerId 归属方 ID
     * @return array<int, string>
     */
    public function getCategoryNameMapByIds(array $categoryIds, int $ownerId = 0): array
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);
        if ($categoryIds === []) {
            return [];
        }

        return $this->getModel()
            ->where([
                'type' => $this->categoryType,
                'owner_id' => $ownerId,
            ])
            ->whereIn('id', $categoryIds)
            ->column('name', 'id');
    }

    /**
     * 归一化写入数据。
     */
    protected function normalizeWriteData(array $data, int $ownerId): array
    {
        $path = is_array($data['path'] ?? null) ? $data['path'] : [];
        if ($path !== []) {
            $data['pid'] = (int) end($path);
        }
        $data['owner_id'] = $ownerId;
        $data['status'] = (int) ($data['status'] ?? 0);

        return $data;
    }

    /**
     * 校验分类归属并返回分类模型。
     *
     * @throws AdminResponseException
     */
    protected function assertOwned(int $id, int $ownerId = 0)
    {
        $category = $this->getModel()
            ->where([
                'id'       => $id,
                'type'     => $this->categoryType,
                'owner_id' => $ownerId,
            ])
            ->find();
        if (!$category) {
            throw new AdminResponseException('商品分类不存在或无权操作');
        }

        return $category;
    }

    /**
     * 清理移动端商品分类树缓存。
     */
    protected function clearMobileTreeCache(): void
    {
        $this->cacheTag()->clear();
    }

    /**
     * 构建移动端商品分类树缓存 key。
     *
     * @param int $ownerId 归属方 ID。
     * @param array<int> $categoryIds 分类 ID 列表。
     * @return string
     */
    protected function getMobileTreeCacheKey(int $ownerId, array $categoryIds): string
    {
        return self::MOBILE_TREE_CACHE_PREFIX . $ownerId . ':' . md5(json_encode($categoryIds, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 归一化分类 ID 列表。
     *
     * @param array<int|string> $categoryIds 分类 ID 列表。
     * @return array<int>
     */
    protected function normalizeCategoryIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        sort($categoryIds);

        return $categoryIds;
    }
}
