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

use app\common\enum\ProductEnum;
use app\common\exception\AdminResponseException;
use app\common\model\content\ContentProductRelation;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\store\MobileProductService;
use app\common\service\store\StoreProductService;

/**
 * 内容商品关联服务
 *
 * 统一维护文章、专题与店铺商品的多选关联。关联只保存商品 ID 和顺序，
 * 商品价格、封面、上下架状态均在读取时实时获取，避免展示过期快照。
 */
class ContentProductRelationService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 关联类型：文章
     */
    public const RELATION_TYPE_ARTICLE = 'article';

    /**
     * 关联类型：专题
     */
    public const RELATION_TYPE_SPECIAL = 'special';

    /**
     * 单条内容最多关联商品数
     */
    public const MAX_PRODUCT_COUNT = 100;

    /**
     * 后台编辑回显所需商品字段
     */
    protected const PRODUCT_SUMMARY_FIELDS = [
        'id',
        'merchant_id',
        'name',
        'cover',
        'price',
        'stock',
        'status',
        'audit_status',
    ];

    /**
     * 设置对应模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return ContentProductRelation::class;
    }

    /**
     * 同步内容关联商品
     *
     * 传入空数组表示清空关联；保存前统一校验商品存在性和商户归属，
     * 商品下架或审核未通过时仍允许保留关联，由移动端读取时过滤。
     *
     * @param string $relationType 关联类型：article/special
     * @param int $relationId 文章ID或专题ID
     * @param array $productIds 店铺商品ID数组，按数组顺序展示
     * @param int $ownerId 内容归属：0=平台，大于0=商户ID
     * @return void 无返回值
     * @throws AdminResponseException 关联类型、商品数量、商品存在性或归属不合法时抛出
     */
    public function syncRelation(string $relationType, int $relationId, array $productIds, int $ownerId): void
    {
        $this->assertRelationType($relationType);
        if ($relationId <= 0) {
            throw new AdminResponseException('关联业务ID不正确');
        }

        $productIds = $this->normalizeProductIds($productIds);
        if (count($productIds) > self::MAX_PRODUCT_COUNT) {
            throw new AdminResponseException('关联商品不能超过' . self::MAX_PRODUCT_COUNT . '个');
        }

        if ($productIds !== []) {
            $productMap = app()->make(StoreProductService::class)
                ->getMapByIds($productIds, self::PRODUCT_SUMMARY_FIELDS);

            foreach ($productIds as $productId) {
                if (!isset($productMap[$productId])) {
                    throw new AdminResponseException('关联商品不存在或已删除：' . $productId);
                }
                // 平台内容只能关联平台商品，商户内容只能关联当前商户自己的商品。
                if ((int)($productMap[$productId]['merchant_id'] ?? 0) !== $ownerId) {
                    throw new AdminResponseException('商品不属于当前内容所属商户，无法关联：' . $productId);
                }
            }
        }

        $this->transaction(function () use ($relationType, $relationId, $productIds) {
            $this->getModel()->where([
                'relation_type' => $relationType,
                'relation_id'   => $relationId,
            ])->delete();

            foreach ($productIds as $index => $productId) {
                $this->create([
                    'relation_type' => $relationType,
                    'relation_id'   => $relationId,
                    'product_id'    => $productId,
                    'sort'          => $index + 1,
                ]);
            }
        });
    }

    /**
     * 获取内容关联的商品ID
     *
     * @param string $relationType 关联类型：article/special
     * @param int $relationId 文章ID或专题ID
     * @return array 按展示顺序排列的商品ID，包含已下架或已删除商品
     */
    public function getProductIds(string $relationType, int $relationId): array
    {
        $this->assertRelationType($relationType);
        if ($relationId <= 0) {
            return [];
        }

        $ids = $this->getModel()
            ->where([
                'relation_type' => $relationType,
                'relation_id'   => $relationId,
            ])
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->column('product_id');

        return array_values(array_filter(array_map('intval', $ids)));
    }

    /**
     * 为内容/专题详情附加关联商品
     *
     * 返回的 products 与 product_ids 顺序一致；商品被软删除时返回占位项，
     * 保证后台编辑页仍能看到并手动移除失效关联。
     *
     * @param array $detail 内容或专题详情
     * @param string $relationType 关联类型：article/special
     * @param int $relationId 文章ID或专题ID
     * @return array 追加 product_ids、products 后的详情
     */
    public function attachProductsToDetail(array $detail, string $relationType, int $relationId): array
    {
        $productIds = $this->getProductIds($relationType, $relationId);
        $productMap = $productIds === []
            ? []
            : app()->make(StoreProductService::class)->getMapByIds($productIds, self::PRODUCT_SUMMARY_FIELDS);

        $products = [];
        foreach ($productIds as $productId) {
            $product = $productMap[$productId] ?? null;
            if (!$product) {
                $products[] = [
                    'id'           => $productId,
                    'merchant_id'  => 0,
                    'name'         => '商品已删除',
                    'cover'        => '',
                    'price'        => 0.0,
                    'stock'        => 0,
                    'status'       => 0,
                    'audit_status' => 0,
                    'is_available' => false,
                    'is_deleted'   => true,
                ];
                continue;
            }

            $products[] = [
                'id'           => (int)$productId,
                'merchant_id'  => (int)($product['merchant_id'] ?? 0),
                'name'         => (string)($product['name'] ?? ''),
                'cover'        => (string)($product['cover'] ?? ''),
                'price'        => (float)($product['price'] ?? 0),
                'stock'        => (int)($product['stock'] ?? 0),
                'status'       => (int)($product['status'] ?? 0),
                'audit_status' => (int)($product['audit_status'] ?? 0),
                'is_available' => (int)($product['status'] ?? 0) === ProductEnum::STATUS_OPEN
                    && (int)($product['audit_status'] ?? 0) === ProductEnum::AUDIT_STATUS_PASSED,
                'is_deleted'   => false,
            ];
        }

        $detail['product_ids'] = $productIds;
        $detail['products'] = $products;

        return $detail;
    }

    /**
     * 删除指定内容的全部商品关联
     *
     * @param string $relationType 关联类型：article/special
     * @param int $relationId 文章ID或专题ID
     * @return void 无返回值
     */
    public function deleteByRelation(string $relationType, int $relationId): void
    {
        $this->assertRelationType($relationType);
        if ($relationId <= 0) {
            return;
        }

        $this->getModel()->where([
            'relation_type' => $relationType,
            'relation_id'   => $relationId,
        ])->delete();
    }

    /**
     * 获取移动端关联商品分页
     *
     * 关联商品中的下架、审核未通过和软删除商品由 MobileProductService 自动过滤，
     * 返回顺序保持后台选择顺序，并补充 uniapp 商品详情页跳转地址。
     *
     * @param string $relationType 关联类型：article/special
     * @param int $relationId 文章ID或专题ID
     * @param array $params 分页与用户参数：page、limit、user_id
     * @return array 商品列表和分页信息
     */
    public function getMobileProductPage(string $relationType, int $relationId, array $params): array
    {
        $this->assertRelationType($relationType);
        $productIds = $this->getProductIds($relationType, $relationId);

        if ($productIds === []) {
            [$page, $limit] = $this->getPagesValue(true, true, $params, 20);
            return $this->listData([], 0, ['has_more' => false], $page, $limit)->toArray();
        }

        $params['_ids'] = $productIds;
        $params['_limit_max'] = 20;
        $result = app()->make(MobileProductService::class)->getList($params);

        foreach ($result['list'] as &$item) {
            $item['detail_url'] = '/pages/goods/detail/index?id=' . (int)($item['id'] ?? 0);
        }
        unset($item);

        return $result;
    }

    /**
     * 校验关联类型白名单
     *
     * @param string $relationType 关联类型
     * @return void 无返回值
     * @throws AdminResponseException 关联类型不在白名单时抛出
     */
    protected function assertRelationType(string $relationType): void
    {
        if (!in_array($relationType, [self::RELATION_TYPE_ARTICLE, self::RELATION_TYPE_SPECIAL], true)) {
            throw new AdminResponseException('关联类型不正确');
        }
    }

    /**
     * 归一化商品ID：校验格式、去重并保持提交顺序
     *
     * @param array $productIds 原始商品ID数组
     * @return array 合法的商品ID数组
     * @throws AdminResponseException 包含非正整数ID时抛出
     */
    protected function normalizeProductIds(array $productIds): array
    {
        $result = [];
        foreach ($productIds as $productId) {
            if (is_string($productId)) {
                $productId = trim($productId);
            }
            if (is_int($productId)) {
                $normalizedId = $productId;
            } elseif (is_string($productId) && ctype_digit($productId)) {
                $normalizedId = (int)$productId;
            } else {
                throw new AdminResponseException('关联商品参数格式不正确');
            }

            if ($normalizedId <= 0) {
                throw new AdminResponseException('关联商品ID不正确');
            }
            if (!in_array($normalizedId, $result, true)) {
                $result[] = $normalizedId;
            }
        }

        return $result;
    }
}
