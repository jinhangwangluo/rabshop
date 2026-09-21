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

namespace app\common\service\content;

use app\common\exception\AdminResponseException;
use app\common\model\content\TagRelation;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\store\StoreProductService;

/**
 * 标签关联服务。
 *
 * 统一维护标签与内容、专题、商品的绑定关系。
 * relation_type 当前支持 article、special、product，owner_id 用于隔离平台与商户数据。
 */
class TagRelationService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置当前 Service 对应模型。
     *
     * @return string 标签关联模型类名。
     */
    protected function setModel(): string
    {
        return TagRelation::class;
    }

    /**
     * 同步资源标签关系。
     *
     * 该方法会先校验资源归属和标签可用性，再删除当前资源旧关系并写入新关系。
     *
     * @param string $relationType 关联类型：article=内容，special=专题，product=商品。
     * @param int $relationId 关联资源 ID。
     * @param array $tagIds 标签 ID 数组。
     * @param int $ownerId 所属者 ID，0=平台，商户 ID=商户。
     * @param int $tagType 标签类型；0 表示根据 relation_type 自动判断。
     * @return array{tag_ids: array<int>, tag_list: array} 同步后的标签 ID 和标签列表。
     * @throws AdminResponseException 关联类型错误、资源不存在、资源归属不匹配或标签不可用时抛出。
     */
    public function syncRelation(string $relationType, int $relationId, array $tagIds, int $ownerId, int $tagType = 0): array
    {
        $tagService = app()->make(TagService::class);
        $relationType = $tagService->normalizeRelationType($relationType);
        $tagType = $tagType > 0 ? $tagService->normalizeTagType($tagType) : $tagService->getTagTypeByRelationType($relationType);
        if ($tagType !== $tagService->getTagTypeByRelationType($relationType)) {
            throw new AdminResponseException('标签类型与关联资源不匹配');
        }
        $this->assertRelationOwner($relationType, $relationId, $ownerId);
        $tagIds = $tagService->validateTagIds($tagIds, $tagType, $ownerId);

        $this->transaction(function () use ($relationType, $relationId, $tagIds, $ownerId, $tagType) {
            $this->getModel()
                ->where([
                    'relation_type' => $relationType,
                    'relation_id' => $relationId,
                    'owner_id' => $ownerId,
                    'tag_type' => $tagType,
                ])
                ->delete();

            foreach ($tagIds as $tagId) {
                $this->create([
                    'tag_id' => $tagId,
                    'owner_id' => $ownerId,
                    'tag_type' => $tagType,
                    'relation_type' => $relationType,
                    'relation_id' => $relationId,
                ]);
            }
        });

        return $this->getRelationTags($relationType, $relationId, $ownerId);
    }

    /**
     * 获取资源已绑定的标签。
     *
     * @param string $relationType 关联类型：article、special、product。
     * @param int $relationId 关联资源 ID。
     * @param int|null $ownerId 所属者 ID；null 表示不按 owner 过滤，主要用于平台读取展示。
     * @return array{tag_ids: array<int>, tag_list: array} 标签 ID 和标签详情列表。
     * @throws AdminResponseException 关联类型不正确时抛出。
     */
    public function getRelationTags(string $relationType, int $relationId, ?int $ownerId = null): array
    {
        $relationType = app()->make(TagService::class)->normalizeRelationType($relationType);
        $relations = $this->getModel()
            ->where([
                'relation_type' => $relationType,
                'relation_id' => $relationId,
            ])
            ->when($ownerId !== null, fn($query) => $query->where('owner_id', $ownerId))
            ->order('id asc')
            ->select()
            ->toArray();

        $tagIds = array_values(array_unique(array_filter(array_map(
            fn(array $item): int => (int)($item['tag_id'] ?? 0),
            $relations
        ))));
        $tagMap = app()->make(TagService::class)->getMapByIds($tagIds);
        $tagList = [];
        foreach ($tagIds as $tagId) {
            if (isset($tagMap[$tagId])) {
                $tagList[] = $tagMap[$tagId];
            }
        }

        return [
            'tag_ids' => array_values(array_map(fn(array $tag): int => (int)$tag['id'], $tagList)),
            'tag_list' => $tagList,
        ];
    }

    /**
     * 为资源列表批量附加标签。
     *
     * @param array $list 资源列表，单项需包含 id。
     * @param string $relationType 关联类型：article、special、product。
     * @return array 附加 tag_ids 和 tag_list 后的资源列表。
     * @throws AdminResponseException 关联类型不正确时抛出。
     */
    public function appendTagsToList(array $list, string $relationType): array
    {
        if ($list === []) {
            return [];
        }

        $relationType = app()->make(TagService::class)->normalizeRelationType($relationType);
        $relationIds = array_values(array_unique(array_filter(array_map(
            fn(array $item): int => (int)($item['id'] ?? 0),
            $list
        ))));
        if ($relationIds === []) {
            return $list;
        }

        $relations = $this->getModel()
            ->where('relation_type', $relationType)
            ->whereIn('relation_id', $relationIds)
            ->order('id asc')
            ->select()
            ->toArray();
        $tagIds = array_values(array_unique(array_filter(array_map(
            fn(array $item): int => (int)($item['tag_id'] ?? 0),
            $relations
        ))));
        $tagMap = app()->make(TagService::class)->getMapByIds($tagIds);

        $relationMap = [];
        foreach ($relations as $relation) {
            $relationId = (int)($relation['relation_id'] ?? 0);
            $tagId = (int)($relation['tag_id'] ?? 0);
            if ($relationId > 0 && isset($tagMap[$tagId])) {
                $relationMap[$relationId][] = $tagMap[$tagId];
            }
        }

        foreach ($list as &$item) {
            $id = (int)($item['id'] ?? 0);
            $tagList = $relationMap[$id] ?? [];
            $item['tag_list'] = $tagList;
            $item['tag_ids'] = array_values(array_map(fn(array $tag): int => (int)$tag['id'], $tagList));
        }

        return $list;
    }

    /**
     * 根据标签 ID 反查已绑定的资源 ID。
     *
     * @param string $relationType 关联类型：article、special、product。
     * @param int $tagId 标签 ID。
     * @param int|null $ownerId 所属者 ID；null 表示不按 owner 过滤。
     * @return array<int> 资源 ID 列表。
     * @throws AdminResponseException 关联类型不正确时抛出。
     */
    public function getRelationIdsByTagId(string $relationType, int $tagId, ?int $ownerId = null): array
    {
        if ($tagId <= 0) {
            return [];
        }

        $tagService = app()->make(TagService::class);
        $relationType = $tagService->normalizeRelationType($relationType);
        $tagType = $tagService->getTagTypeByRelationType($relationType);

        $ids = $this->getModel()
            ->where([
                'relation_type' => $relationType,
                'tag_type' => $tagType,
                'tag_id' => $tagId,
            ])
            ->when($ownerId !== null, fn($query) => $query->where('owner_id', $ownerId))
            ->column('relation_id');

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * 删除指定标签的全部关联关系。
     *
     * @param int $tagId 标签 ID。
     * @return void
     */
    public function deleteByTagId(int $tagId): void
    {
        if ($tagId <= 0) {
            return;
        }
        $this->getModel()->where('tag_id', $tagId)->delete();
    }

    /**
     * 删除指定资源的全部标签关联。
     *
     * @param string $relationType 关联类型：article、special、product。
     * @param int $relationId 关联资源 ID。
     * @return void
     * @throws AdminResponseException 关联类型不正确时抛出。
     */
    public function deleteByRelation(string $relationType, int $relationId): void
    {
        if ($relationId <= 0) {
            return;
        }
        $relationType = app()->make(TagService::class)->normalizeRelationType($relationType);
        $this->getModel()
            ->where([
                'relation_type' => $relationType,
                'relation_id' => $relationId,
            ])
            ->delete();
    }

    /**
     * 校验资源归属。
     *
     * @param string $relationType 关联类型：article、special、product。
     * @param int $relationId 关联资源 ID。
     * @param int $ownerId 当前操作所属者 ID，0=平台，商户 ID=商户。
     * @return int 资源真实所属者 ID。
     * @throws AdminResponseException 资源不存在、关联类型错误或归属不匹配时抛出。
     */
    public function assertRelationOwner(string $relationType, int $relationId, int $ownerId): int
    {
        if ($relationId <= 0) {
            throw new AdminResponseException('关联资源不存在');
        }

        $targetOwnerId = match ($relationType) {
            TagService::RELATION_TYPE_ARTICLE => $this->getArticleOwnerId($relationId),
            TagService::RELATION_TYPE_SPECIAL => $this->getSpecialOwnerId($relationId),
            TagService::RELATION_TYPE_PRODUCT => $this->getProductOwnerId($relationId),
            default => throw new AdminResponseException('标签关联类型不正确'),
        };

        if ($targetOwnerId !== $ownerId) {
            throw new AdminResponseException('无权操作该资源标签');
        }

        return $targetOwnerId;
    }

    /**
     * 获取内容所属者 ID。
     *
     * @param int $articleId 内容 ID。
     * @return int 内容所属者 ID，0=平台，商户 ID=商户。
     * @throws AdminResponseException 内容不存在时抛出。
     */
    protected function getArticleOwnerId(int $articleId): int
    {
        $article = app()->make(ArticleService::class)->get($articleId);
        if (!$article) {
            throw new AdminResponseException('内容不存在');
        }
        return (int)($article['mer_id'] ?? 0);
    }

    /**
     * 获取专题所属者 ID。
     *
     * @param int $specialId 专题 ID。
     * @return int 专题所属者 ID，0=平台，商户 ID=商户。
     * @throws AdminResponseException 专题不存在时抛出。
     */
    protected function getSpecialOwnerId(int $specialId): int
    {
        $special = app()->make(SpecialService::class)->get($specialId);
        if (!$special) {
            throw new AdminResponseException('专题不存在');
        }
        return (int)($special['merchant_id'] ?? 0);
    }

    /**
     * 获取商品所属者 ID。
     *
     * @param int $productId 店铺商品 ID。
     * @return int 商品所属者 ID，0=平台，商户 ID=商户。
     * @throws AdminResponseException 商品不存在时抛出。
     */
    protected function getProductOwnerId(int $productId): int
    {
        $product = app()->make(StoreProductService::class)->get($productId);
        if (!$product) {
            throw new AdminResponseException('商品不存在');
        }

        return (int)($product['merchant_id'] ?? 0);
    }

}
