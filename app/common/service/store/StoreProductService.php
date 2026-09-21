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

use app\common\enum\ProductEnum;
use app\common\exception\AdminResponseException;
use app\common\job\store\StoreProductImageLocalizeJob;
use app\common\model\store\StoreProduct;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\plugin\PluginRegistry;
use app\common\service\plugin\PluginBusinessEventService;
use app\common\service\content\TagRelationService;
use app\common\service\content\TagService;
use app\common\service\merchant\MerchantService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 店铺商品服务
 *
 * 店铺商品 = 店铺运营副本（merchant_id=运营商户）：
 * - 单规格（spec_type=0）：直接引用 1 个库商品
 * - 多规格（spec_type=1）：多选单规格库商品自动组合（assembleFromLibrary）
 * - 商品级统计：stock 为 SKU 汇总（扣减以 SKU 为准）、sales/view_count 为真实累计、
 * virtual_sales/virtual_view_count 为展示基数（display = 真实 + 虚拟）
 */
class StoreProductService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return StoreProduct::class;
    }

    /**
     * 批量获取直播间移动端实物商品展示字段。
     *
     * @param int[] $ids 商品标识集合。
     * @return array<int, array> 以商品标识为键的直播卡片补充资料。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getLiveMobileDisplayMap(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $list = $this->getModel()
            ->whereIn('id', $ids)
            ->field(['id', 'description', 'ot_price', 'sales', 'virtual_sales'])
            ->select()
            ->toArray();
        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_PRODUCT);

        $displayMap = [];
        foreach ($list as $product) {
            $productId = (int)($product['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $tagList = is_array($product['tag_list'] ?? null) ? $product['tag_list'] : [];
            $displaySales = max(0, (int)($product['sales'] ?? 0) + (int)($product['virtual_sales'] ?? 0));
            $displayMap[$productId] = [
                'goods_original_price' => (string)($product['ot_price'] ?? '0.00'),
                'goods_subtitle' => (string)($product['description'] ?? ''),
                'goods_tag' => trim((string)($tagList[0]['name'] ?? '')),
                'display_sales' => $displaySales,
                'sales_text' => '已售' . $displaySales . '件',
            ];
        }

        return $displayMap;
    }

    /**
     * 店铺商品资源列表
     *
     * @param array $params 搜索参数（name/product_code/category_id/merchant_category_id/brand_id/spec_type/goods_type/status/audit_status/source_type/create_time）
     * @param array|string $fields 本次查询需要返回的字段集合。
     * @param array $with 需要预加载的关联名称。
     * @param array|string|null $sort 业务排序字段、方向或预定义排序规则。
     * @return array 列表 + 分页
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], array|string|null $sort = null)
    {
        $merchantId = (int) ($params['_merchant_id'] ?? 0);
        $query = $this->getModel()->order('sort desc, id desc');

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }

        $sourceType = (string) ($params['source_type'] ?? 'all');
        if ($sourceType === 'platform') {
            $query->where('merchant_id', 0);
        } elseif (in_array($sourceType, ['merchant', 'pending'], true)) {
            $query->where('merchant_id', '>', 0);
        }
        if ($sourceType === 'pending') {
            $query->where('audit_status', ProductEnum::AUDIT_STATUS_PENDING);
        }
        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . $params['name'] . '%');
        }
        if (!empty($params['product_code'])) {
            $query->where('product_code', $params['product_code']);
        }
        if (isset($params['category_id']) && $params['category_id'] !== '' && (int)$params['category_id'] > 0) {
            $categoryId = (int)$params['category_id'];
            if ((int)($params['include_child_categories'] ?? 0) === 1) {
                $categoryIds = app()->make(ProductCategoryService::class)->getCategoryAndChildIds($categoryId, 0);
                $query->whereIn('category_id', $categoryIds === [] ? [0] : $categoryIds);
            } else {
                $query->where('category_id', $categoryId);
            }
        }
        if ($merchantId > 0 && isset($params['merchant_category_id']) && (int)$params['merchant_category_id'] > 0) {
            $productIds = app()->make(ProductCategoryRelationService::class)
                ->getProductIdsByCategoryId((int) $params['merchant_category_id']);
            $query->whereIn('id', $productIds === [] ? [0] : $productIds);
        }
        if (isset($params['brand_id']) && $params['brand_id'] !== '' && (int)$params['brand_id'] > 0) {
            $query->where('brand_id', (int)$params['brand_id']);
        }
        if (isset($params['tag_id']) && $params['tag_id'] !== '' && (int)$params['tag_id'] > 0) {
            $productIds = app()->make(TagRelationService::class)->getRelationIdsByTagId(
                TagService::RELATION_TYPE_PRODUCT,
                (int)$params['tag_id']
            );
            $query->whereIn('id', $productIds === [] ? [0] : $productIds);
        }
        if (isset($params['spec_type']) && $params['spec_type'] !== '') {
            $query->where('spec_type', (int)$params['spec_type']);
        }
        if (isset($params['goods_type']) && $params['goods_type'] !== '' && (int)$params['goods_type'] > 0) {
            $query->where('goods_type', (int)$params['goods_type']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }
        if ($sourceType !== 'pending' && isset($params['audit_status']) && $params['audit_status'] !== '') {
            $query->where('audit_status', (int)$params['audit_status']);
        }
        if (!empty($params['create_time']) && is_array($params['create_time'])) {
            $query->whereBetweenTime('create_time', $params['create_time'][0], $params['create_time'][1]);
        }

        [$page, $limit] = $this->getPagesValue();
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_PRODUCT);

        $merchantNameMap = app()->make(MerchantService::class)->getNameMapByIds(array_column($list, 'merchant_id'));

        // 补充 SKU 数量、商户名称与审核状态文本
        $skuService = app()->make(StoreProductSkuService::class);
        foreach ($list as &$item) {
            $itemMerchantId = (int) ($item['merchant_id'] ?? 0);
            $item['sku_count'] = count($skuService->getSkuList((int) $item['id']));
            $item['merchant_name'] = $itemMerchantId > 0 ? ($merchantNameMap[$itemMerchantId] ?? '商户#' . $itemMerchantId) : '平台';
            $item['source_type_text'] = $itemMerchantId > 0 ? '商户' : '平台';
            $item['audit_status_text'] = ProductEnum::getAuditStatusName((int) ($item['audit_status'] ?? 0));
            $item['display_sales'] = (int) ($item['sales'] ?? 0) + (int) ($item['virtual_sales'] ?? 0);
            $item['display_view_count'] = (int) ($item['view_count'] ?? 0) + (int) ($item['virtual_view_count'] ?? 0);
        }
        unset($item);

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 创建店铺商品（商品库单选/多选统一走 SKU 组合流程）
     *
     * @param array $data 店铺商品数据
     * @param int $merchantId 运营商户ID
     * @return \think\model\contract\Modelable 创建结果
     */
    public function resourceSave(array $data, int $merchantId = 0)
    {
        $this->assertNotHotelProductInput($data);
        $this->assertWritablePlatformCategory($data, $merchantId, true);
        $data = $this->applyMerchantProductAuditMode($data, $merchantId);

        $isCollect = (int) ($data['is_collect'] ?? 0) === 1;
        unset($data['is_collect']);

        $libraryIds = array_values(array_filter(array_map('intval', $data['library_product_ids'] ?? [])));
        $product = $libraryIds === []
            ? $this->assembleFromCustom($merchantId, $data)
            : $this->assembleFromLibrary($merchantId, $data);

        if ($isCollect) {
            $this->dispatchCollectedImageLocalize((int) ($product->id ?? 0));
        }

        $this->publishProductEvent('product.created', $product, [
            'operation' => $libraryIds === [] ? 'custom' : 'library',
            'merchant_id' => $merchantId,
            'source' => $isCollect ? 'collect' : 'create',
            'library_product_count' => count($libraryIds),
        ]);

        return $product;
    }

    /**
     * 上架组合（多选单规格库商品 → 自动组合多规格店铺商品）★ 核心
     *
     * 流程：
     * 1. 权限校验：每个库商品 ∈ (平台商品 ∪ 自己自建 ∪ 他人共享)
     * 2. 1 个库商品 → spec_type=0，1 个 SKU（uniqid=库 product_code）
     * 3. N 个库商品 → spec_type=1：
     * a. 每个库商品生成一个 SKU
     * b. SKU 名称使用提交值，未提交时使用库商品名称
     * 4. 重选（已有店铺商品）：与现有 SKU（withTrashed）diff：新增=复制生成，移除=软删，保留=更新
     *
     * @param int $merchantId 运营商户ID
     * @param array $data 组合数据（library_product_ids + library_skus + 店铺自定义字段）
     * @return \think\model\contract\Modelable 店铺商品
     * @throws AdminResponseException 权限/参数校验失败时抛出
     */
    public function assembleFromLibrary(int $merchantId, array $data)
    {
        $this->assertNotHotelProductInput($data, (int)($data['id'] ?? 0));
        $this->assertWritablePlatformCategory($data, $merchantId, true);

        $libraryProductIds = array_values(array_unique(array_filter(array_map('intval', $data['library_product_ids'] ?? []))));
        if ($libraryProductIds === []) {
            throw new AdminResponseException('请选择商品库商品');
        }

        // 1. 权限校验
        $libraryList = app()->make(ProductLibraryService::class)->assertAvailable($libraryProductIds, $merchantId);
        $libraryMap = [];
        foreach ($libraryList as $item) {
            $libraryMap[(int) $item['id']] = $item;
        }
        // 保持传入顺序
        $libraryList = [];
        foreach ($libraryProductIds as $id) {
            if (isset($libraryMap[$id])) {
                $libraryList[] = $libraryMap[$id];
            }
        }
        $skuOverrides = $this->normalizeLibrarySkuOverrides($data['library_skus'] ?? [], $libraryProductIds);

        $productId = (int) ($data['id'] ?? 0);
        $specType = count($libraryList) > 1 ? ProductEnum::SPEC_TYPE_MULTI : ProductEnum::SPEC_TYPE_SINGLE;
        $first = $libraryList[0];

        return $this->transaction(function () use ($productId, $libraryList, $libraryProductIds, $skuOverrides, $data, $merchantId, $specType, $first) {
            $product = null;
            if ($productId > 0) {
                $this->assertMerchantOwned($productId, $merchantId);
                $product = $this->update($productId, $this->buildProductFields($first, $data, $merchantId, $specType));
            } else {
                $product = $this->create($this->buildProductFields($first, $data, $merchantId, $specType));
                $productId = (int) $product->id;
            }

            // 3. 生成 SKU 行
            $skuRows = [];
            $skuTextMap = $this->buildSkuTextMap($libraryList);
            foreach ($libraryList as $index => $item) {
                $libraryId = (int) $item['id'];
                $override = $skuOverrides[$libraryId] ?? [];
                $skuRows[] = [
                    'library_product_id' => $libraryId,
                    'uniqid' => (string) ($item['product_code'] ?? ''),
                    'spec_value_ids' => '',
                    'sku' => (string) ($override['sku'] ?? ($skuTextMap[$index] ?? ($item['name'] ?? ''))),
                    'price' => (float) ($override['price'] ?? ($item['price'] ?? 0)),
                    'ot_price' => (float) ($override['ot_price'] ?? ($item['ot_price'] ?? 0)),
                    'store_price' => (float) ($override['store_price'] ?? ($item['store_price'] ?? 0)),
                    'stock' => (int) ($override['stock'] ?? ($item['stock'] ?? 0)),
                    'stock_version' => $override['stock_version'] ?? null,
                    'weight' => (float) ($override['weight'] ?? 0),
                    'pics' => (string) ($override['pics'] ?? ($item['cover'] ?? '')),
                    'sort' => (int) ($override['sort'] ?? (100 - $index)),
                    'is_default' => (int) ($override['is_default'] ?? ($index === 0 ? 1 : 0)),
                    'status' => (int) ($override['status'] ?? ProductEnum::SKU_STATUS_OPEN),
                ];
            }
            $defaultIndex = 0;
            foreach ($skuRows as $index => $row) {
                if ((int) ($row['is_default'] ?? 0) === 1) {
                    $defaultIndex = $index;
                    break;
                }
            }
            foreach ($skuRows as $index => &$row) {
                $row['is_default'] = $index === $defaultIndex ? 1 : 0;
            }
            unset($row);

            // 4. SKU diff（withTrashed 比较，新增=创建，移除=软删，保留=更新）
            $this->diffSkuRows($productId, $skuRows);

            $this->saveSpecConfig($productId, [
                'spec_mode' => 'library',
                'custom_spec_type' => $specType === ProductEnum::SPEC_TYPE_MULTI ? 1 : 0,
                'library_product_ids' => $libraryProductIds,
                'custom_specs' => [],
            ]);
            $this->syncProductParamConfig($productId, $data, $merchantId, (int) ($data['category_id'] ?? $first['category_id'] ?? 0));

            // 5. 商品级快照汇总
            $this->syncProductSnapshot($productId);
            $this->syncProductTags($productId, $data, $merchantId);

            return $product;
        });
    }

    /**
     * 新增自定义规格/SKU 商品：每个自定义 SKU 先同步为库商品，再生成店铺 SKU。
     *
     * @param int $merchantId 运营商户ID
     * @param array $data 商品与自定义 SKU 数据
     * @return \think\model\contract\Modelable 店铺商品
     */
    public function assembleFromCustom(int $merchantId, array $data)
    {
        $this->assertNotHotelProductInput($data, (int)($data['id'] ?? 0));
        $this->assertWritablePlatformCategory($data, $merchantId, true);

        $customSkus = array_values(array_filter($data['custom_skus'] ?? [], static fn($item): bool => is_array($item)));
        if ($customSkus === []) {
            throw new AdminResponseException('请至少填写一个SKU');
        }

        $productId = (int) ($data['id'] ?? 0);
        $specType = count($customSkus) > 1 ? ProductEnum::SPEC_TYPE_MULTI : ProductEnum::SPEC_TYPE_SINGLE;

        return $this->transaction(function () use ($productId, $customSkus, $data, $merchantId, $specType) {
            $libraryRows = [];
            $libraryProductIds = [];
            foreach ($customSkus as $index => $sku) {
                $row = $this->syncCustomLibrarySku($sku, $data, $merchantId, $index);
                $libraryRows[] = $row;
                $libraryProductIds[] = (int) ($row['library']['id'] ?? 0);
            }

            $first = $libraryRows[0]['library'];
            if ($productId > 0) {
                $this->assertMerchantOwned($productId, $merchantId);
                $product = $this->update($productId, $this->buildProductFields($first, $data, $merchantId, $specType));
            } else {
                $product = $this->create($this->buildProductFields($first, $data, $merchantId, $specType));
                $productId = (int) $product->id;
            }

            $skuRows = [];
            $configCustomSkus = [];
            foreach ($libraryRows as $index => $row) {
                $libraryId = (int) ($row['library']['id'] ?? 0);
                $configCustomSkus[] = array_merge($row['sku'], [
                    'library_product_id' => $libraryId,
                    'uniqid' => (string) ($row['library']['product_code'] ?? ''),
                ]);
                $skuRows[] = [
                    'library_product_id' => $libraryId,
                    'uniqid' => (string) ($row['library']['product_code'] ?? ''),
                    'spec_value_ids' => '',
                    'sku' => (string) ($row['sku']['sku'] ?? ''),
                    'pics' => (string) ($row['sku']['pics'] ?? ($row['library']['cover'] ?? '')),
                    'price' => (float) ($row['sku']['price'] ?? 0),
                    'ot_price' => (float) ($row['sku']['ot_price'] ?? 0),
                    'store_price' => (float) ($row['sku']['store_price'] ?? 0),
                    'stock' => (int) ($row['sku']['stock'] ?? 0),
                    'stock_version' => $row['sku']['stock_version'] ?? null,
                    'weight' => (float) ($row['sku']['weight'] ?? ($data['weight'] ?? 0)),
                    'sort' => (int) ($row['sku']['sort'] ?? (100 - $index)),
                    'is_default' => $index === 0 ? 1 : (int) ($row['sku']['is_default'] ?? 0),
                    'status' => (int) ($row['sku']['status'] ?? ProductEnum::SKU_STATUS_OPEN),
                ];
            }

            $this->diffSkuRows($productId, $skuRows);
            $this->deleteRemovedCustomLibraries($data, $merchantId);
            $this->saveSpecConfig($productId, [
                'spec_mode' => 'custom',
                'custom_spec_type' => (int) ($data['custom_spec_type'] ?? ($specType === ProductEnum::SPEC_TYPE_MULTI ? 1 : 0)),
                'library_product_ids' => array_values(array_filter($libraryProductIds)),
                'custom_specs' => $data['custom_specs'] ?? [],
                'custom_skus' => $configCustomSkus,
            ]);
            $this->syncProductParamConfig($productId, $data, $merchantId, (int) ($data['category_id'] ?? $first['category_id'] ?? 0));
            $this->syncProductSnapshot($productId);
            $this->syncProductTags($productId, $data, $merchantId);

            return $product;
        });
    }

    /**
     * 更新自定义规格商品（仅更新店铺商品与店铺 SKU，不回写商品库）。
     *
     * @param int $productId 店铺商品ID
     * @param array $data 商品与自定义 SKU 数据
     * @param int $merchantId 当前操作商户标识，为零时使用平台操作范围。
     * @return void 无返回值。
     */
    protected function updateCustomProduct(int $productId, array $data, int $merchantId = 0): void
    {
        $customSkus = array_values(array_filter($data['custom_skus'] ?? [], static fn($item): bool => is_array($item)));
        if ($customSkus === []) {
            throw new AdminResponseException('请至少填写一个SKU');
        }

        $this->transaction(function () use ($productId, $data, $customSkus, $merchantId) {
            $this->update($productId, $this->buildCustomProductUpdatePayload($data, $customSkus));

            $skuRows = [];
            $configCustomSkus = [];
            foreach ($customSkus as $index => $sku) {
                $normalized = $this->normalizeCustomSkuRow($sku, $index);
                $skuRows[] = [
                    'library_product_id' => (int) $normalized['library_product_id'],
                    'uniqid' => (string) $normalized['uniqid'],
                    'spec_value_ids' => '',
                    'sku' => (string) $normalized['sku'],
                    'pics' => (string) $normalized['pics'],
                    'price' => (float) $normalized['price'],
                    'ot_price' => (float) $normalized['ot_price'],
                    'store_price' => (float) $normalized['store_price'],
                    'stock' => (int) $normalized['stock'],
                    'stock_version' => $normalized['stock_version'],
                    'weight' => (float) $normalized['weight'],
                    'sort' => (int) $normalized['sort'],
                    'is_default' => (int) $normalized['is_default'],
                    'status' => (int) $normalized['status'],
                ];
                $configCustomSkus[] = array_merge($normalized, [
                    'library_product_id' => (int) $normalized['library_product_id'],
                    'uniqid' => (string) $normalized['uniqid'],
                ]);
            }

            $this->diffSkuRows($productId, $skuRows);
            $this->saveSpecConfig($productId, [
                'spec_mode' => 'custom',
                'custom_spec_type' => (int) ($data['custom_spec_type'] ?? (count($customSkus) > 1 ? ProductEnum::SPEC_TYPE_MULTI : ProductEnum::SPEC_TYPE_SINGLE)),
                'library_product_ids' => array_values(array_unique(array_filter(array_map(
                    static fn(array $row): int => (int) ($row['library_product_id'] ?? 0),
                    $configCustomSkus
                )))),
                'custom_specs' => $data['custom_specs'] ?? [],
                'custom_skus' => $configCustomSkus,
            ]);
            $currentCategoryId = (int) ($data['category_id'] ?? $this->getModel()->where('id', $productId)->value('category_id') ?? 0);
            $this->syncProductParamConfig($productId, $data, $merchantId, $currentCategoryId);
            $this->syncProductSnapshot($productId);
            $this->syncProductTags($productId, $data, $merchantId);
        });
    }

    /**
     * 同步自定义 SKU 对应的库商品。
     *
     * @param array $sku 自定义 SKU
     * @param array $productData 商品数据
     * @param int $merchantId 运营商户ID
     * @param int $index SKU 序号
     * @return array{library: array, sku: array} 已同步的来源商品库记录和补齐名称后的自定义规格数据。
     */
    protected function syncCustomLibrarySku(array $sku, array $productData, int $merchantId, int $index): array
    {
        $skuName = trim((string) ($sku['sku'] ?? ''));
        if ($skuName === '') {
            $skuName = count($productData['custom_skus'] ?? []) > 1 ? '规格' . ($index + 1) : '默认';
            $sku['sku'] = $skuName;
        }

        $libraryData = $this->buildCustomLibraryData($sku, $productData, $skuName);
        $libraryService = app()->make(ProductLibraryService::class);
        $libraryId = (int) ($sku['library_product_id'] ?? 0);
        if ($libraryId > 0) {
            $libraryService->resourceUpdate($libraryId, $libraryData, $merchantId);
            $library = $libraryService->resourceRead($libraryId, $merchantId);
        } else {
            $created = $libraryService->resourceSave($libraryData, $merchantId);
            $library = is_object($created) && method_exists($created, 'toArray') ? $created->toArray() : (array) $created;
        }

        return [
            'library' => $library,
            'sku' => $sku,
        ];
    }

    /**
     * 构建自定义 SKU 对应库商品数据。
     *
     * @param array $sku 自定义 SKU
     * @param array $productData 商品数据
     * @param string $skuName SKU 名称
     * @return array 用于自定义规格商品库保存的字段。
     */
    protected function buildCustomLibraryData(array $sku, array $productData, string $skuName): array
    {
        $productName = trim((string) ($productData['name'] ?? '商品'));
        $libraryName = trim($productName . ' ' . $skuName);
        $pics = $productData['pics'] ?? [];
        $cover = (string) ($sku['pics'] ?? ($productData['cover'] ?? ''));
        if ($cover === '' && is_array($pics) && $pics !== []) {
            $cover = (string) ($pics[0] ?? '');
        }

        return [
            'name' => $libraryName,
            'keyword' => (string) ($productData['keyword'] ?? ''),
            'category_id' => (int) ($productData['category_id'] ?? 0),
            'brand_id' => (int) ($productData['brand_id'] ?? 0),
            'group_id' => (int) ($productData['group_id'] ?? 0),
            'spec_value_id' => 0,
            'price' => (float) ($sku['price'] ?? 0),
            'ot_price' => (float) ($sku['ot_price'] ?? 0),
            'store_price' => (float) ($sku['store_price'] ?? 0),
            'stock' => (int) ($sku['stock'] ?? 0),
            'unit' => '',
            'goods_type' => (int) ($productData['goods_type'] ?? ProductEnum::GOODS_TYPE_PHYSICAL),
            'cover' => $cover,
            'pics' => is_array($pics) ? $pics : [],
            'video' => (string) ($productData['video'] ?? ''),
            'description' => (string) ($productData['description'] ?? ''),
            'detail' => (string) ($productData['detail'] ?? ''),
            'attr_values' => $productData['attr_values'] ?? [],
            'plugin_code' => strtolower(trim((string)($productData['plugin_code'] ?? ''))),
            // 自定义 SKU 先同步到商品库；保留前端提交的 plugin_payload，
            // 由 ProductLibraryService 统一做命名空间和启用状态校验。
            'plugin_payload' => is_array($productData['plugin_payload'] ?? null)
                ? $productData['plugin_payload']
                : (is_array($productData['plugin_payload_json'] ?? null) ? $productData['plugin_payload_json'] : []),
            'service_ids' => (string) ($productData['service_ids'] ?? ''),
            'freight_template_id' => (int) ($productData['freight_template_id'] ?? 0),
            'gift_points' => (int) ($productData['gift_points'] ?? 0),
            'first_commission_rate' => (float) ($productData['first_commission_rate'] ?? 0),
            'second_commission_rate' => (float) ($productData['second_commission_rate'] ?? 0),
            'is_share' => ProductEnum::IS_SHARE_NO,
            'status' => (int) ($sku['status'] ?? ProductEnum::STATUS_OPEN),
            'audit_status' => (int) ($productData['audit_status'] ?? ProductEnum::AUDIT_STATUS_PASSED),
            'sort' => (int) ($sku['sort'] ?? 0),
        ];
    }

    /**
     * 规范化自定义 SKU 行，供编辑时直接写入店铺 SKU 与规格快照。
     *
     * @param array $sku 自定义 SKU
     * @param int $index SKU 序号
     * @return array<string,mixed> 经类型转换并补齐默认字段的自定义规格数据，金额校验由保存链路负责。
     */
    protected function normalizeCustomSkuRow(array $sku, int $index): array
    {
        $rowKey = trim((string) ($sku['rowKey'] ?? ''));
        $key = trim((string) ($sku['key'] ?? ''));
        $uniqid = trim((string) ($sku['uniqid'] ?? ''));
        if ($uniqid === '') {
            $suffix = $rowKey !== '' ? $rowKey : ($key !== '' ? $key : (string) ($index + 1));
            $uniqid = 'custom_' . $suffix;
        }

        return [
            'rowKey' => $rowKey,
            'key' => $key,
            'library_product_id' => (int) ($sku['library_product_id'] ?? 0),
            'uniqid' => $uniqid,
            'stock_version' => $sku['stock_version'] ?? null,
            'sku' => trim((string) ($sku['sku'] ?? '')),
            'price' => (float) ($sku['price'] ?? 0),
            'ot_price' => (float) ($sku['ot_price'] ?? 0),
            'store_price' => (float) ($sku['store_price'] ?? 0),
            'stock' => (int) ($sku['stock'] ?? 0),
            'weight' => (float) ($sku['weight'] ?? 0),
            'pics' => trim((string) ($sku['pics'] ?? '')),
            'sort' => (int) ($sku['sort'] ?? 0),
            'is_default' => (int) ($sku['is_default'] ?? 0),
            'status' => (int) ($sku['status'] ?? ProductEnum::SKU_STATUS_OPEN),
        ];
    }

    /**
     * 构建自定义商品更新的基础字段。
     *
     * @param array $data 请求数据
     * @param array $customSkus 自定义 SKU 行
     * @return array<string,mixed> 由商品输入和规格列表组装的主表更新字段。
     */
    protected function buildCustomProductUpdatePayload(array $data, array $customSkus): array
    {
        $allowed = [
            'name', 'keyword', 'category_id', 'brand_id', 'group_id',
            'goods_type', 'cover', 'pics', 'video', 'description', 'detail',
            'is_free_shipping', 'attr_values', 'service_ids', 'freight_template_id',
            'gift_points', 'first_commission_rate', 'second_commission_rate',
            'status', 'audit_status', 'on_sale_time', 'off_sale_time', 'sort',
            'plugin_code', 'plugin_payload', 'plugin_payload_json',
        ];
        $payload = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        $payload['spec_type'] = (int) ($data['custom_spec_type'] ?? (count($customSkus) > 1 ? ProductEnum::SPEC_TYPE_MULTI : ProductEnum::SPEC_TYPE_SINGLE));
        $this->normalisePluginFields($payload, $data);

        return $payload;
    }

    /**
     * 规范化商品插件字段并校验插件启用状态。
     *
     * @param array<string, mixed> $payload 待保存的商品主表字段，按引用补写插件归属及载荷。
     * @param array<string, mixed> $data 店铺商品主表、规格、标签及参数配置。
     * @return void 无返回值。
     */
    protected function normalisePluginFields(array &$payload, array $data): void
    {
        if (!array_key_exists('plugin_code', $data) && !array_key_exists('plugin_payload', $data) && !array_key_exists('plugin_payload_json', $data)) {
            return;
        }
        $pluginCode = strtolower(trim((string)($data['plugin_code'] ?? '')));
        $pluginPayload = is_array($data['plugin_payload'] ?? null)
            ? $data['plugin_payload']
            : (is_array($data['plugin_payload_json'] ?? null) ? $data['plugin_payload_json'] : []);
        if ($pluginCode !== '') {
            if (!app()->make(PluginRegistry::class)->isPluginEnabled($pluginCode)) {
                throw new AdminResponseException('插件未启用，无法保存插件商品');
            }
            $pluginPayload = $this->normalisePluginPayload($pluginCode, $pluginPayload);
        } else {
            $pluginPayload = [];
        }
        $payload['plugin_code'] = $pluginCode;
        $payload['plugin_payload_json'] = $pluginPayload;
        unset($payload['plugin_payload']);
    }

    /**
     * 校验店铺商品插件载荷只能包含商品归属插件的命名空间。
     *
     * @param string $pluginCode 已验证启用的插件编码。
     * @param array<string, mixed> $payload 请求载荷。
     * @return array<string, array<string, mixed>> 仅包含商品归属插件命名空间的载荷。
     * @throws AdminResponseException 命名空间越权或载荷格式错误时抛出。
     */
    protected function normalisePluginPayload(string $pluginCode, array $payload): array
    {
        if ($payload === []) {
            return [$pluginCode => []];
        }
        if (count($payload) !== 1 || !array_key_exists($pluginCode, $payload)) {
            throw new AdminResponseException('插件载荷命名空间无效：' . $pluginCode);
        }
        if (!is_array($payload[$pluginCode])) {
            throw new AdminResponseException('插件载荷格式错误：' . $pluginCode);
        }

        return [$pluginCode => $payload[$pluginCode]];
    }

    /**
     * 软删前端明确移除的自定义库商品。
     *
     * @param array $data 请求数据
     * @param int $merchantId 运营商户ID
     * @return void 无返回值。
     */
    protected function deleteRemovedCustomLibraries(array $data, int $merchantId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $data['removed_library_product_ids'] ?? []))));
        if ($ids === []) {
            return;
        }

        $productId = (int) ($data['id'] ?? 0);
        if ($productId > 0) {
            $config = app()->make(StoreProductSpecConfigService::class)->findByProductId($productId);
            $allowedIds = array_values(array_filter(array_map('intval', $config['library_product_ids'] ?? [])));
            if ($allowedIds === []) {
                return;
            }
            $ids = array_values(array_intersect($ids, $allowedIds));
            if ($ids === []) {
                return;
            }
        }

        $libraryService = app()->make(ProductLibraryService::class);
        foreach ($ids as $id) {
            $libraryService->resourceDelete($id, $merchantId);
        }
    }

    /**
     * 保存商品规格配置。
     *
     * @param int $productId 店铺商品ID
     * @param array $config 配置数据
     * @return void 无返回值。
     */
    protected function saveSpecConfig(int $productId, array $config): void
    {
        app()->make(StoreProductSpecConfigService::class)->saveByProductId($productId, $config);
    }

    /**
     * 同步商品参数快照与检索索引。
     *
     * @param int $productId 店铺商品ID
     * @param array $data 请求数据
     * @param int $merchantId 商户ID
     * @param int $categoryId 分类ID
     * @return void 无返回值。
     */
    protected function syncProductParamConfig(int $productId, array $data, int $merchantId = 0, int $categoryId = 0): void
    {
        if ($productId <= 0) {
            return;
        }

        $hasParamPayload = array_key_exists('param_attribute_id', $data) || array_key_exists('param_snapshot', $data);
        $specConfigService = app()->make(StoreProductSpecConfigService::class);
        $existing = $specConfigService->findByProductId($productId);
        $existingParamAttributeId = (int) ($existing['param_attribute_id'] ?? 0);
        $existingParamSnapshot = $this->normalizeParamSnapshot(is_array($existing['param_snapshot'] ?? null) ? $existing['param_snapshot'] : []);

        if (!$hasParamPayload && $existingParamAttributeId <= 0 && $existingParamSnapshot === []) {
            return;
        }

        $paramAttributeId = $hasParamPayload
            ? (int) ($data['param_attribute_id'] ?? 0)
            : $existingParamAttributeId;
        $paramSnapshot = $hasParamPayload
            ? $this->normalizeParamSnapshot(is_array($data['param_snapshot'] ?? null) ? $data['param_snapshot'] : [])
            : $existingParamSnapshot;

        $payload = array_merge($existing, [
            'param_attribute_id' => $paramAttributeId,
            'param_snapshot' => $paramSnapshot,
        ]);
        $specConfigService->saveByProductId($productId, $payload);

        $merchantId = $merchantId > 0 ? $merchantId : (int) ($this->getModel()->where('id', $productId)->value('merchant_id') ?? 0);
        $categoryId = $categoryId > 0 ? $categoryId : (int) ($this->getModel()->where('id', $productId)->value('category_id') ?? 0);
        app()->make(StoreProductParamIndexService::class)->syncByProductId(
            $productId,
            $merchantId,
            $categoryId,
            $paramAttributeId,
            $paramSnapshot
        );
    }

    /**
     * 标准化商品参数快照。
     *
     * @param array<int, array<string, mixed>> $snapshot 参数快照
     * @return array<int, array<string, mixed>> 去除无效项并按排序值、行键排序的参数快照列表。
     */
    protected function normalizeParamSnapshot(array $snapshot): array
    {
        $rows = [];
        foreach ($snapshot as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? $row['label'] ?? ''));
            if ($name === '') {
                continue;
            }

            $selectedValues = $row['selected_values'] ?? $row['values'] ?? [];
            if (is_string($selectedValues)) {
                $selectedValues = array_filter(array_map('trim', explode('|', $selectedValues)));
            }
            $selectedValues = is_array($selectedValues) ? $selectedValues : [];
            $selectedValues = array_values(array_unique(array_filter(array_map(static function ($value): string {
                return trim((string) $value);
            }, $selectedValues))));
            if ($selectedValues === []) {
                $singleValue = trim((string) ($row['value'] ?? ''));
                if ($singleValue !== '') {
                    $selectedValues = [$singleValue];
                }
            }
            if ($selectedValues === []) {
                continue;
            }

            $rows[] = [
                'row_key' => trim((string) ($row['row_key'] ?? $row['key'] ?? ('param_' . ($index + 1)))),
                'param_id' => (int) ($row['param_id'] ?? $row['attribute_id'] ?? 0),
                'name' => $name,
                'is_required' => (int) ($row['is_required'] ?? 0),
                'sort' => max(0, (int) ($row['sort'] ?? 0)),
                'selected_values' => $selectedValues,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $sort = (int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0);
            return $sort !== 0 ? $sort : strcmp((string) ($left['row_key'] ?? ''), (string) ($right['row_key'] ?? ''));
        });

        return array_values($rows);
    }

    /**
     * 构建店铺商品落库字段（继承库商品与商品基础信息）
     *
     * @param array $library 库商品
     * @param array $data 自定义覆盖字段
     * @param int $merchantId 运营商户ID
     * @param int $specType 规格类型
     * @return array 店铺商品字段
     */
    protected function buildProductFields(array $library, array $data, int $merchantId, int $specType): array
    {
        $requestedPluginCode = strtolower(trim((string)($data['plugin_code'] ?? '')));
        $libraryPluginCode = strtolower(trim((string)($library['plugin_code'] ?? '')));
        // 上架/编辑接口的兼容字段默认是空字符串时，不能覆盖商品库已有的插件归属。
        $pluginCode = $requestedPluginCode !== '' ? $requestedPluginCode : $libraryPluginCode;
        $pluginPayload = is_array($data['plugin_payload'] ?? null) && $data['plugin_payload'] !== []
            ? $data['plugin_payload']
            : (is_array($data['plugin_payload_json'] ?? null) && $data['plugin_payload_json'] !== []
                ? $data['plugin_payload_json']
                : ($library['plugin_payload_json'] ?? []));
        if ($pluginCode !== '') {
            if (!app()->make(PluginRegistry::class)->isPluginEnabled($pluginCode)) {
                throw new AdminResponseException('插件未启用，无法保存插件商品');
            }
            $pluginPayload = $this->normalisePluginPayload($pluginCode, $pluginPayload);
        } else {
            $pluginPayload = [];
        }
        $fields = [
            'merchant_id' => $merchantId,
            'spec_type' => $specType,
            'name' => (string) ($data['name'] ?? $library['name'] ?? ''),
            'keyword' => (string) ($data['keyword'] ?? $library['keyword'] ?? ''),
            'product_code' => $specType === ProductEnum::SPEC_TYPE_SINGLE ? (string) ($library['product_code'] ?? '') : '',
            'category_id' => (int) ($data['category_id'] ?? $library['category_id'] ?? 0),
            'brand_id' => (int) ($data['brand_id'] ?? $library['brand_id'] ?? 0),
            'group_id' => (int) ($data['group_id'] ?? $library['group_id'] ?? 0),
            'price' => (float) ($library['price'] ?? 0),
            'ot_price' => (float) ($library['ot_price'] ?? 0),
            'store_price' => (float) ($library['store_price'] ?? 0),
            'stock' => (int) ($library['stock'] ?? 0),
            'unit' => (string) ($library['unit'] ?? ''),
            'goods_type' => (int) ($data['goods_type'] ?? $library['goods_type'] ?? ProductEnum::GOODS_TYPE_PHYSICAL),
            'cover' => (string) ($data['cover'] ?? $library['cover'] ?? ''),
            'pics' => $data['pics'] ?? ($library['pics'] ?? []),
            'video' => (string) ($data['video'] ?? $library['video'] ?? ''),
            'description' => (string) ($data['description'] ?? $library['description'] ?? ''),
            'detail' => (string) ($data['detail'] ?? $library['detail'] ?? ''),
            'is_free_shipping' => (int) ($data['is_free_shipping'] ?? 0),
            'attr_values' => $data['attr_values'] ?? ($library['attr_values'] ?? []),
            'plugin_code' => $pluginCode,
            'plugin_payload_json' => $pluginPayload,
            'service_ids' => (string) ($data['service_ids'] ?? $library['service_ids'] ?? ''),
            'freight_template_id' => (int) ($data['freight_template_id'] ?? $library['freight_template_id'] ?? 0),
            'gift_points' => (int) ($data['gift_points'] ?? $library['gift_points'] ?? 0),
            'first_commission_rate' => (float) ($data['first_commission_rate'] ?? $library['first_commission_rate'] ?? 0),
            'second_commission_rate' => (float) ($data['second_commission_rate'] ?? $library['second_commission_rate'] ?? 0),
            'status' => (int) ($data['status'] ?? ProductEnum::STATUS_OPEN),
            'audit_status' => (int) ($data['audit_status'] ?? ProductEnum::AUDIT_STATUS_PASSED),
            'sort' => (int) ($data['sort'] ?? 0),
        ];

        // 多规格店铺商品引用库商品集合时 library_product_id=0（SKU 行各自带来源）
        $fields['library_product_id'] = $specType === ProductEnum::SPEC_TYPE_SINGLE
            ? (int) ($library['id'] ?? 0)
            : 0;

        return $fields;
    }

    /**
     * 构建 SKU 展示文本。
     *
     * @param array $libraryList 库商品列表
     * @return array 索引 => 文本
     */
    protected function buildSkuTextMap(array $libraryList): array
    {
        $map = [];
        foreach ($libraryList as $index => $item) {
            $map[$index] = (string) ($item['name'] ?? '');
        }

        return $map;
    }

    /**
     * 规范化商品库 SKU 的店铺覆盖值。
     *
     * SKU 唯一值始终由商品库 product_code 生成，前端只能覆盖店铺副本字段。
     *
     * @param array $rows 前端提交的 SKU 覆盖行
     * @param array $libraryProductIds 当前已选商品库 ID
     * @return array<int, array<string, mixed>> 商品库 ID => 覆盖字段
     */
    protected function normalizeLibrarySkuOverrides(array $rows, array $libraryProductIds): array
    {
        $allowedIds = array_fill_keys(array_map('intval', $libraryProductIds), true);
        $overrides = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $libraryId = (int) ($row['library_product_id'] ?? 0);
            if ($libraryId <= 0 || !isset($allowedIds[$libraryId])) {
                continue;
            }

            $override = [];
            if (array_key_exists('sku', $row)) {
                $sku = trim((string) $row['sku']);
                if ($sku !== '') {
                    $override['sku'] = $sku;
                }
            }
            foreach (['price', 'ot_price', 'store_price', 'weight'] as $field) {
                if (array_key_exists($field, $row)) {
                    $override[$field] = max(0, (float) $row[$field]);
                }
            }
            foreach (['stock', 'sort'] as $field) {
                if (array_key_exists($field, $row)) {
                    $override[$field] = max(0, (int) $row[$field]);
                }
            }
            if (array_key_exists('stock_version', $row)) {
                $override['stock_version'] = $row['stock_version'];
            }
            foreach (['is_default', 'status'] as $field) {
                if (array_key_exists($field, $row)) {
                    $override[$field] = (int) $row[$field] === 1 ? 1 : 0;
                }
            }
            if (array_key_exists('pics', $row)) {
                $override['pics'] = trim((string) $row['pics']);
            }
            $overrides[$libraryId] = $override;
        }

        return $overrides;
    }

    /**
     * SKU 行 diff：新增=创建，移除=软删，保留=更新（withTrashed 比较，含已删除 SKU）
     *
     * @param int $productId 店铺商品ID
     * @param array $skuRows 期望的 SKU 行
     * @return void 无返回值。
     */
    protected function diffSkuRows(int $productId, array $skuRows): void
    {
        $skuService = app()->make(StoreProductSkuService::class);
        // withTrashed：软删的 SKU 也在比较范围内，保证重选时 uniqid 不释放
        $existing = $skuService->getModel()
            ->withTrashed()
            ->where('store_product_id', $productId)
            ->select()
            ->toArray();

        $existingByUniqid = [];
        foreach ($existing as $row) {
            $existingByUniqid[(string) ($row['uniqid'] ?? '')] = $row;
        }

        $wantedUniqids = [];
        foreach ($skuRows as $row) {
            $uniqid = (string) ($row['uniqid'] ?? '');
            $wantedUniqids[$uniqid] = $uniqid;

            $old = $existingByUniqid[$uniqid] ?? null;
            if ($old === null) {
                // 新增：复制生成
                $skuService->saveSku($productId, $row);
                continue;
            }

            // 保留：更新（含软删恢复）
            $oldId = (int) $old['id'];
            $data = array_merge($row, [
                'id' => $oldId,
                'delete_time' => null,
            ]);
            $skuService->saveSku($productId, $data);
        }

        // 移除：软删（不在期望列表中的 SKU，包括已软删的保持软删）
        foreach ($existing as $row) {
            $uniqid = (string) ($row['uniqid'] ?? '');
            if (!isset($wantedUniqids[$uniqid]) && (int) ($row['delete_time'] ?? 0) === 0) {
                $skuService->deleteSku((int) $row['id'], $productId);
            }
        }
    }

    /**
     * 同步商品级快照（价格/库存 = 可售 SKU 汇总）
     *
     * price/ot_price/store_price=各 SKU 对应字段的最小值、
     * stock=status=1 的 SKU 库存之和。
     *
     * @param int $productId 店铺商品ID
     * @return void 无返回值。
     */
    public function syncProductSnapshot(int $productId): void
    {
        $this->assertNotHotelProductInput([], $productId);
        $skus = app()->make(StoreProductSkuService::class)
            ->getModel()
            ->where('store_product_id', $productId)
            ->where('status', ProductEnum::SKU_STATUS_OPEN)
            ->select()
            ->toArray();

        $price = null;
        $otPrice = null;
        $storePrice = null;
        $stock = 0;
        foreach ($skus as $sku) {
            $skuPrice = (float) ($sku['price'] ?? 0);
            $skuStorePrice = (float) ($sku['store_price'] ?? 0);
            $skuOtPrice = (float) ($sku['ot_price'] ?? 0);
            $price = $price === null ? $skuPrice : min($price, $skuPrice);
            $storePrice = $storePrice === null ? $skuStorePrice : min($storePrice, $skuStorePrice);
            $otPrice = $otPrice === null ? $skuOtPrice : min($otPrice, $skuOtPrice);
            $stock += (int) ($sku['stock'] ?? 0);
        }

        $this->getModel()->where('id', $productId)->update([
            'price' => $price ?? 0,
            'ot_price' => $otPrice ?? 0,
            'store_price' => $storePrice ?? 0,
            'stock' => $stock,
        ]);
    }

    /**
     * 批量保存 SKU（事务内：逐条判重 + 唯一值不可改校验，完成后重算商品级快照）
     *
     * @param int $productId 店铺商品ID
     * @param array $skus SKU 数据数组
     * @return void 无返回值。
     * @throws AdminResponseException 唯一值不可修改或参数为空时抛出
     */
    public function saveSkuBatch(int $productId, array $skus): void
    {
        $this->assertNotHotelProductInput([], $productId);
        if ($skus === []) {
            throw new AdminResponseException('SKU数据不能为空');
        }

        $this->transaction(function () use ($productId, $skus) {
            // 与普通销售、活动库存预留和退货保持同一锁顺序：先商品，再规格。
            if (!$this->getModel()->where('id', $productId)->lock(true)->find()) {
                throw new AdminResponseException('商品不存在');
            }
            $skuService = app()->make(StoreProductSkuService::class);
            foreach ($skus as $sku) {
                if (is_array($sku)) {
                    $skuService->saveSku($productId, $sku);
                }
            }
            $this->syncProductSnapshot($productId);
        });
        $this->publishProductEvent('product.sku.changed', $productId, [
            'operation' => 'save_sku_batch',
            'sku_count' => count($skus),
        ]);
    }

    /**
     * 发布商品事件，只携带最小必要信息。
     *
     * @param array|object|int $product 商品模型、数组或商品 ID。
     * @param array<string,mixed> $context 补充上下文。
     * @param string $eventName 需要发布的业务事件名称。
     * @return void 无返回值。
     */
    private function publishProductEvent(string $eventName, array|object|int $product, array $context = []): void
    {
        $snapshot = [];
        if (is_int($product)) {
            $row = $this->get($product);
            if (is_object($row) && method_exists($row, 'toArray')) {
                $snapshot = $row->toArray();
            } elseif (is_array($row)) {
                $snapshot = $row;
            }
        } elseif (is_object($product) && method_exists($product, 'toArray')) {
            $snapshot = $product->toArray();
        } elseif (is_array($product)) {
            $snapshot = $product;
        }

        $productId = (int) ($snapshot['id'] ?? $context['product_id'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        $payload = [
            'product_id' => $productId,
            'merchant_id' => (int) ($snapshot['merchant_id'] ?? $context['merchant_id'] ?? 0),
            'product_code' => (string) ($snapshot['product_code'] ?? ''),
            'name' => (string) ($snapshot['name'] ?? ''),
            'status' => (int) ($snapshot['status'] ?? $context['status'] ?? 0),
            'audit_status' => (int) ($snapshot['audit_status'] ?? $context['audit_status'] ?? 0),
            'spec_type' => (int) ($snapshot['spec_type'] ?? 0),
            'goods_type' => (int) ($snapshot['goods_type'] ?? 0),
            'source_type' => (int) ($snapshot['merchant_id'] ?? 0) > 0 ? 'merchant' : 'platform',
            'operation' => (string) ($context['operation'] ?? ''),
            'meta' => array_diff_key($context, array_flip(['merchant_id', 'status', 'audit_status', 'operation'])),
        ];

        $meta = $payload['meta'];
        if (is_array($meta)) {
            ksort($meta);
        }
        $metaHash = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        app()->make(PluginBusinessEventService::class)->publish(
            $eventName,
            $payload,
            [
                'subject_type' => 'product',
                'subject_id' => (string) $productId,
                'merchant_id' => (int) ($payload['merchant_id'] ?? 0),
                'idempotency_key' => 'product:' . substr(hash('sha256', implode('|', [
                    $eventName,
                    (string) $productId,
                    (string) ($snapshot['update_time'] ?? $snapshot['create_time'] ?? ''),
                    $metaHash === false ? '' : $metaHash,
                ])), 0, 64),
            ]
        );
    }

    /**
     * 保存已有商品库 SKU 的店铺覆盖值。
     *
     * @param int $productId 店铺商品ID
     * @param array $rows 前端提交的商品库 SKU 覆盖行
     * @return void 无返回值。
     */
    protected function saveExistingLibrarySkuOverrides(int $productId, array $rows): void
    {
        $skuService = app()->make(StoreProductSkuService::class);
        $currentRows = $skuService->getSkuList($productId);
        $currentMap = [];
        foreach ($currentRows as $current) {
            $libraryId = (int) ($current['library_product_id'] ?? 0);
            if ($libraryId > 0) {
                $currentMap[$libraryId] = $current;
            }
        }

        $payload = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $libraryId = (int) ($row['library_product_id'] ?? 0);
            $current = $currentMap[$libraryId] ?? null;
            if ($current === null) {
                continue;
            }

            $payload[] = array_merge($current, [
                'sku' => trim((string) ($row['sku'] ?? $current['sku'])) ?: (string) $current['sku'],
                'price' => $row['price'] ?? $current['price'],
                'ot_price' => $row['ot_price'] ?? $current['ot_price'],
                'store_price' => $row['store_price'] ?? $current['store_price'],
                'stock' => $row['stock'] ?? $current['stock'],
                'stock_version' => $row['stock_version'] ?? null,
                'weight' => $row['weight'] ?? $current['weight'],
                'sort' => $row['sort'] ?? $current['sort'],
                'is_default' => $row['is_default'] ?? $current['is_default'],
                'status' => $row['status'] ?? $current['status'],
                'pics' => $row['pics'] ?? $current['pics'],
            ]);
        }

        if ($payload !== []) {
            $this->saveSkuBatch($productId, $payload);
        }
    }

    /**
     * 投递采集商品图片本地化队列。
     *
     * @param int $productId 店铺商品ID
     * @return void 无返回值。
     */
    protected function dispatchCollectedImageLocalize(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        StoreProductImageLocalizeJob::dispatch($productId);
    }

    /**
     * 更新店铺商品（含 SKU 编辑入口的聚合）
     *
     * @param int|array $id 主键
     * @param array $data 更新数据
     * @param int $merchantId 当前商户ID
     * @return bool 更新结果
     */
    public function resourceUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertNotHotelProductInput($data, (int)$id);
        $isCollect = (int) ($data['is_collect'] ?? 0) === 1;
        unset($data['is_collect']);

        $this->assertMerchantOwned((int) $id, $merchantId);
        $this->assertWritablePlatformCategory($data, $merchantId, array_key_exists('category_id', $data));
        $data = $this->applyMerchantProductAuditMode($data, $merchantId);

        // 重新选品（assemble 入口），否则普通字段更新
        $libraryIds = array_values(array_filter(array_map('intval', $data['library_product_ids'] ?? [])));
        if ($libraryIds !== []) {
            $data['id'] = (int) $id;
            $this->assembleFromLibrary($merchantId, $data);
            if ($isCollect) {
                $this->dispatchCollectedImageLocalize((int) $id);
            }
            $this->publishProductEvent('product.updated', (int) $id, [
                'operation' => 'library',
                'merchant_id' => $merchantId,
                'library_product_count' => count($libraryIds),
                'is_collect' => $isCollect,
            ]);
            return true;
        }
        if (($data['spec_mode'] ?? '') === 'custom' || !empty($data['custom_skus'])) {
            $this->updateCustomProduct((int) $id, $data, $merchantId);
            if ($isCollect) {
                $this->dispatchCollectedImageLocalize((int) $id);
            }
            $this->syncProductParamConfig((int) $id, $data, $merchantId, (int) ($data['category_id'] ?? 0));
            $this->publishProductEvent('product.updated', (int) $id, [
                'operation' => 'custom',
                'merchant_id' => $merchantId,
                'is_collect' => $isCollect,
            ]);
            return true;
        }
        if (($data['spec_mode'] ?? '') === 'library' && !empty($data['library_skus'])) {
            $this->saveExistingLibrarySkuOverrides((int) $id, $data['library_skus']);
        }

        // 仅更新商品基础字段，SKU 汇总字段不允许通过商品级请求覆盖
        $allowed = [
            'name', 'keyword', 'category_id', 'brand_id', 'group_id',
            'goods_type', 'spec_type',
            'cover', 'pics', 'video', 'description', 'detail',
            'is_free_shipping', 'attr_values', 'service_ids', 'freight_template_id',
            'gift_points', 'first_commission_rate', 'second_commission_rate',
            'status', 'audit_status', 'on_sale_time', 'off_sale_time', 'sort',
            'plugin_code', 'plugin_payload', 'plugin_payload_json',
        ];
        $payload = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        if (array_key_exists('custom_spec_type', $data)) {
            $payload['spec_type'] = (int) $data['custom_spec_type'];
        }
        $this->normalisePluginFields($payload, $data);
        if ($payload === []) {
            $this->syncProductTags((int) $id, $data, $merchantId);
            $this->syncProductParamConfig((int) $id, $data, $merchantId, (int) ($data['category_id'] ?? 0));
            if ($isCollect) {
                $this->dispatchCollectedImageLocalize((int) $id);
            }
            $this->publishProductEvent('product.updated', (int) $id, [
                'operation' => 'meta',
                'merchant_id' => $merchantId,
                'is_collect' => $isCollect,
            ]);
            return true;
        }

        $result = $this->update($id, $payload);
        $this->syncProductTags((int) $id, $data, $merchantId);
        $this->syncProductParamConfig((int) $id, $data, $merchantId, (int) ($data['category_id'] ?? 0));
        if ($isCollect) {
            $this->dispatchCollectedImageLocalize((int) $id);
        }
        $this->publishProductEvent('product.updated', (int) $id, [
            'operation' => 'basic',
            'merchant_id' => $merchantId,
            'is_collect' => $isCollect,
        ]);

        return $result;
    }

    /**
     * 同步商品标签关系；未传 tag_ids 时保持原关系不变。
     *
     * @param int $productId 店铺商品 ID
     * @param array $data 商品请求数据
     * @param int $merchantId 当前商户 ID
     * @return void 无返回值。
     */
    protected function syncProductTags(int $productId, array $data, int $merchantId = 0): void
    {
        if ($productId <= 0 || !array_key_exists('tag_ids', $data)) {
            return;
        }

        app()->make(TagRelationService::class)->syncRelation(
            TagService::RELATION_TYPE_PRODUCT,
            $productId,
            (array) $data['tag_ids'],
            $merchantId,
            TagService::TAG_TYPE_PRODUCT
        );
    }

    /**
     * 按商户商品审核配置决定店铺商品审核状态。
     *
     * @param array $data 店铺商品主表、规格、标签及参数配置。
     * @param int $merchantId 当前操作商户标识，为零时使用平台操作范围。
     * @return array 附带当前商户商品审核状态的保存字段。
     */
    protected function applyMerchantProductAuditMode(array $data, int $merchantId): array
    {
        if ($merchantId <= 0) {
            return $data;
        }

        $data['audit_status'] = $this->resolveMerchantProductAuditStatus($merchantId);
        return $data;
    }

    /**
     * 获取商户商品审核结果状态：平台审核进入待审，免审核直接通过。
     *
     * @param int $merchantId 当前操作商户标识，为零时使用平台操作范围。
     * @return int 按平台配置确定的待审核或审核通过状态。
     */
    protected function resolveMerchantProductAuditStatus(int $merchantId): int
    {
        $merchant = app()->make(MerchantService::class)->get($merchantId, ['id', 'product_audit_mode']);
        $auditMode = (int)($merchant['product_audit_mode'] ?? 1);

        return $auditMode === 2 ? ProductEnum::AUDIT_STATUS_PASSED : ProductEnum::AUDIT_STATUS_PENDING;
    }

    /**
     * 删除店铺商品（软删）
     *
     * @param int $id 主键
     * @param int $merchantId 当前商户ID
     * @return bool 删除结果
     */
    public function resourceDelete(int $id, int $merchantId = 0)
    {
        $this->assertMerchantOwned($id, $merchantId);
        $this->assertNotHotelProductInput([], $id);
        $snapshot = $this->get($id);
        $result = (bool) $this->getModel()->destroy($id);
        if ($result) {
            $this->publishProductEvent('product.deleted', $snapshot ?? $id, [
                'operation' => 'delete',
                'merchant_id' => $merchantId,
            ]);
        }
        return $result;
    }

    /**
     * 校验商品操作权限后读取主表，并附加规格配置、自定义分类及标签。
     *
     * @param int|array $id 店铺商品标识，按整数标识处理；联合类型仅沿用资源接口签名。
     * @param int $merchantId 当前操作商户标识，为零时使用平台操作范围。
     * @return array 商品主表以及附加的规格配置、自定义分类和标签。
     */
    public function resourceRead(int|array $id, int $merchantId = 0)
    {
        $this->assertMerchantOwned((int) $id, $merchantId);
        $row = $this->get((int) $id);
        if (!$row) {
            throw new AdminResponseException('商品不存在');
        }
        $data = $row->toArray();
        $data['spec_config'] = app()->make(StoreProductSpecConfigService::class)->findByProductId((int) $id);
        $data['category_ids'] = app()->make(ProductCategoryRelationService::class)->getCategoryIdsByProductId((int) $id);
        $data = app()->make(TagService::class)->attachTagsToDetail($data, TagService::RELATION_TYPE_PRODUCT);
        return $data;
    }

    /**
     * 修改状态（上下架）
     *
     * @param int|array $id 主键
     * @param array $data 状态数据
     * @param int $merchantId 当前商户ID
     * @return bool 更新结果
     */
    public function resourceShowUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertMerchantOwned((int) $id, $merchantId);
        $this->assertNotHotelProductInput($data, (int)$id);
        $result = $this->update($id, $data);
        $this->publishProductEvent('product.status.changed', (int) $id, [
            'operation' => 'status',
            'merchant_id' => $merchantId,
            'status' => (int) ($data['status'] ?? 0),
        ]);
        return $result;
    }

    /**
     * 审核商户商品。
     *
     * @param int $id 商品 ID。
     * @param int $auditStatus 审核状态。
     * @return bool 商户商品审核状态是否更新成功。
     */
    public function auditMerchantProduct(int $id, int $auditStatus): bool
    {
        if (!array_key_exists($auditStatus, ProductEnum::auditStatusList())) {
            throw new AdminResponseException('审核状态不正确');
        }
        if ($auditStatus === ProductEnum::AUDIT_STATUS_PENDING) {
            throw new AdminResponseException('请选择审核通过或审核驳回');
        }

        $product = $this->get($id);
        if (!$product) {
            throw new AdminResponseException('商品不存在');
        }
        $this->assertNotHotelProductInput([], $id);
        if ((int) ($product['merchant_id'] ?? 0) <= 0) {
            throw new AdminResponseException('平台商品无需审核');
        }

        $data = ['audit_status' => $auditStatus];
        if ($auditStatus === ProductEnum::AUDIT_STATUS_REJECTED) {
            $data['status'] = ProductEnum::STATUS_HIDDEN;
        }

        $result = (bool) $this->update($id, $data);
        if ($result) {
            $this->publishProductEvent('product.audit.changed', $id, [
                'operation' => 'audit',
                'merchant_id' => (int) ($product['merchant_id'] ?? 0),
                'audit_status' => $auditStatus,
                'status' => (int) ($data['status'] ?? ($product['status'] ?? 0)),
            ]);
        }
        return $result;
    }

    /**
     * 校验店铺商品归属（平台或归属商户可操作，公开入口供控制器/服务调用）
     *
     * @param int $id 店铺商品ID
     * @param int $merchantId 当前商户ID（0=平台不校验）
     * @return void 无返回值。
     * @throws AdminResponseException 无权限时抛出
     */
    public function assertOperatable(int $id, int $merchantId): void
    {
        $this->assertMerchantOwned($id, $merchantId);
    }

    /**
     * 只有酒店专用录入流程可创建无 SKU 的酒店商品。
     *
     * @param array $data 服务端验证过的酒店名称和图片。
     * @param int $merchantId 登录商户归属，平台自营为 0。
     * @param int $productId 已有酒店商品 ID，新增时为 0。
     * @return int 店铺商品 ID。
     */
    public function saveHotelProduct(array $data, int $merchantId, int $productId = 0): int
    {
        $fields = [
            'name' => $data['name'], 'description' => $data['description'],
            'cover' => $data['cover'], 'pics' => $data['pics'],
            'price' => $data['price'], 'ot_price' => $data['price'],
            'goods_type' => ProductEnum::GOODS_TYPE_VIRTUAL, 'plugin_code' => 'hotel',
            'plugin_payload_json' => ['hotel' => ['timezone' => 'Asia/Shanghai']],
            'merchant_id' => $merchantId, 'library_product_id' => 0,
            'spec_type' => ProductEnum::SPEC_TYPE_SINGLE, 'stock' => 0,
            'is_free_shipping' => 1, 'gift_points' => 0,
        ];
        if ($productId > 0) {
            $product = $this->get($productId);
            if (!$product || (string)$product['plugin_code'] !== 'hotel'
                || (int)$product['goods_type'] !== ProductEnum::GOODS_TYPE_VIRTUAL
                || (int)$product['merchant_id'] !== $merchantId) {
                throw new AdminResponseException('酒店商品归属或类型无效');
            }
            $this->update($productId, $fields);
            return $productId;
        }
        $fields['status'] = ProductEnum::STATUS_HIDDEN;
        $fields['audit_status'] = $merchantId > 0
            ? ProductEnum::AUDIT_STATUS_PENDING : ProductEnum::AUDIT_STATUS_PASSED;
        return (int)$this->create($fields)->id;
    }

    /**
     * 普通商品及 SKU 表单不能伪造酒店商品或更改既有酒店商品。
     *
     * @param array $data 普通商品保存入参。
     * @param int $productId 编辑时的商品 ID。
     * @return void 无返回值。
     */
    public function assertNotHotelProductInput(array $data, int $productId = 0): void
    {
        $payload = $data['plugin_payload'] ?? $data['plugin_payload_json'] ?? [];
        if (strtolower(trim((string)($data['plugin_code'] ?? ''))) === 'hotel'
            || (is_array($payload) && array_key_exists('hotel', $payload))
            || $productId > 0 && (string)($this->get($productId, ['plugin_code'])['plugin_code'] ?? '') === 'hotel') {
            throw new AdminResponseException('酒店商品请在酒店管理页面录入或修改');
        }
    }

    protected function assertWritablePlatformCategory(array $data, int $merchantId, bool $required): void
    {
        if ($merchantId <= 0) {
            return;
        }

        if (!array_key_exists('category_id', $data)) {
            if ($required) {
                throw new AdminResponseException('请选择平台分类');
            }
            return;
        }

        app()->make(ProductCategoryService::class)->assertPlatformCategory((int) $data['category_id']);
    }

    /**
     * 校验店铺商品归属（平台或归属商户可操作）
     *
     * @param int $id 店铺商品ID
     * @param int $merchantId 当前商户ID
     * @return void 无返回值。
     * @throws AdminResponseException 无权限时抛出
     */
    protected function assertMerchantOwned(int $id, int $merchantId): void
    {
        if ($id <= 0) {
            throw new AdminResponseException('参数错误');
        }
        if ($merchantId <= 0) {
            return;
        }

        $ownerId = (int) $this->getModel()->where('id', $id)->value('merchant_id');
        if ($ownerId !== $merchantId) {
            throw new AdminResponseException('无权操作该商品');
        }
    }

    /**
     * 获取可售店铺商品（供购物车/下单链路使用）
     *
     * @param int $id 店铺商品ID
     * @return array 商品数据
     * @throws AdminResponseException 不存在或不可售时抛出
     */
    public function findAvailableById(int $id): array
    {
        $product = $this->getModel()
            ->where('id', $id)
            ->where('status', ProductEnum::STATUS_OPEN)
            ->where('audit_status', ProductEnum::AUDIT_STATUS_PASSED)
            ->find();

        if (!$product) {
            throw new AdminResponseException('商品不存在或已下架');
        }

        return $product->toArray();
    }

    /**
     * 按 ID 批量获取店铺商品映射（购物车上下文用）
     *
     * @param array $ids 店铺商品ID列表
     * @return array id => 商品
     */
    public function getMapByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $list = $this->getModel()
            ->whereIn('id', array_values(array_unique(array_filter(array_map('intval', $ids)))))
            ->select()
            ->toArray();

        $map = [];
        foreach ($list as $item) {
            $map[(int) $item['id']] = $item;
        }
        return $map;
    }

    /**
     * 移动端商品搜索列表。
     *
     * @param array $params 查询参数（keyword/page/limit/sort/category_id/brand_id/price_min/price_max/merchant_type/is_free_shipping/param_filters）
     * @return array 列表、总数与分页信息
     */
    public function getMobileSearchList(array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(30, (int)($params['limit'] ?? 10)));
        $keyword = $this->normalizeMobileSearchKeyword((string)($params['keyword'] ?? ''));
        $sort = trim((string)($params['sort'] ?? 'default'));

        $query = $this->getModel()
            ->where('status', ProductEnum::STATUS_OPEN)
            ->where('audit_status', ProductEnum::AUDIT_STATUS_PASSED);

        $this->applyMobileSearchFilters($query, $params);

        if ($keyword !== '') {
            $likeKeyword = $this->buildSafeLikeKeyword($keyword);
            $query->where(function ($subQuery) use ($keyword, $likeKeyword): void {
                $subQuery->whereLike('name', $likeKeyword)
                    ->whereOr('keyword', 'like', $likeKeyword)
                    ->whereOr('description', 'like', $likeKeyword);

                if (mb_strlen($keyword) <= 64) {
                    $subQuery->whereOr('product_code', $keyword);
                }
            });
        }

        $total = (int)(clone $query)->count();
        $list = $this->applyMobileSearchSort($query, $sort)
            ->field([
                'id',
                'merchant_id',
                'category_id',
                'brand_id',
                'name',
                'keyword',
                'product_code',
                'description',
                'cover',
                'price',
                'ot_price',
                'sales',
                'virtual_sales',
                'view_count',
                'virtual_view_count',
                'stock',
                'unit',
                'goods_type',
                'spec_type',
                'is_free_shipping',
                'sort',
                'create_time',
            ])
            ->page($page, $limit)
            ->select()
            ->toArray();
        $list = app()->make(TagService::class)->attachTagsToList($list, TagService::RELATION_TYPE_PRODUCT);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
            'has_more' => $page * $limit < $total,
        ];
    }

    /**
     * 应用移动端商品筛选条件。
     *
     * @param object $query 查询对象
     * @param array $params 查询参数（category_id/brand_id/price_min/price_max/merchant_type/is_free_shipping/include_child_categories/param_filters）
     * @return void 无返回值。
     */
    public function applyMobileSearchFilters(object $query, array $params = []): void
    {
        if (isset($params['category_id']) && (int) $params['category_id'] > 0) {
            $categoryId = (int) $params['category_id'];
            if ((int) ($params['include_child_categories'] ?? 0) === 1) {
                $categoryIds = app()->make(ProductCategoryService::class)->getCategoryAndChildIds($categoryId, 0);
                $query->whereIn('category_id', $categoryIds === [] ? [0] : $categoryIds);
            } else {
                $query->where('category_id', $categoryId);
            }
        }

        if (isset($params['brand_id']) && (int) $params['brand_id'] > 0) {
            $query->where('brand_id', (int) $params['brand_id']);
        }

        if (isset($params['price_min']) && $params['price_min'] !== '' && is_numeric($params['price_min'])) {
            $query->where('price', '>=', (float) $params['price_min']);
        }

        if (isset($params['price_max']) && $params['price_max'] !== '' && is_numeric($params['price_max'])) {
            $query->where('price', '<=', (float) $params['price_max']);
        }

        if (isset($params['merchant_type'])) {
            $merchantType = trim((string) $params['merchant_type']);
            if ($merchantType === 'self') {
                $query->where('merchant_id', 0);
            } elseif ($merchantType === 'non_self') {
                $query->where('merchant_id', '>', 0);
            }
        }

        if (isset($params['is_free_shipping']) && (int) $params['is_free_shipping'] > 0) {
            $query->where('is_free_shipping', 1);
        }

        $paramFilters = $params['param_filters'] ?? [];
        if (is_array($paramFilters) && $paramFilters !== []) {
            app()->make(StoreProductParamIndexService::class)->applyFilters(
                $query,
                $paramFilters,
                $this->getModel()->getTable(),
                (int) ($params['merchant_id'] ?? 0),
                (int) ($params['category_id'] ?? 0)
            );
        }
    }

    /**
     * 店铺商品选项（select 组件数据源）。
     *
     * @param array $params 搜索参数（name/category_id/brand_id/tag_id/include_child_categories/platform_only）
     * @param int $merchantId 当前商户ID，0=平台
     * @return array 选项列表
     */
    public function options(array $params = [], int $merchantId = 0): array
    {
        $query = $this->getModel()
            ->where('status', ProductEnum::STATUS_OPEN)
            ->where('audit_status', ProductEnum::AUDIT_STATUS_PASSED)
            ->field(['id', 'name', 'cover', 'price', 'stock', 'merchant_id', 'category_id'])
            ->order('sort desc, id desc');

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        } elseif (!empty($params['platform_only'])) {
            // 指定只选平台商品时，排除商户商品（平台商品 merchant_id = 0）
            $query->where('merchant_id', 0);
        }
        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . trim((string) $params['name']) . '%');
        }
        if (isset($params['category_id']) && (int) $params['category_id'] > 0) {
            $categoryId = (int)$params['category_id'];
            if ((int)($params['include_child_categories'] ?? 0) === 1) {
                $categoryIds = app()->make(ProductCategoryService::class)->getCategoryAndChildIds($categoryId, 0);
                $query->whereIn('category_id', $categoryIds === [] ? [0] : $categoryIds);
            } else {
                $query->where('category_id', $categoryId);
            }
        }
        if (isset($params['brand_id']) && (int)$params['brand_id'] > 0) {
            $query->where('brand_id', (int)$params['brand_id']);
        }
        if (isset($params['tag_id']) && (int)$params['tag_id'] > 0) {
            $productIds = app()->make(TagRelationService::class)->getRelationIdsByTagId(
                TagService::RELATION_TYPE_PRODUCT,
                (int)$params['tag_id']
            );
            $query->whereIn('id', $productIds === [] ? [0] : $productIds);
        }

        return $query->limit(200)->select()->toArray();
    }

    /**
     * 增加真实浏览量（商品详情访问累计）
     *
     * @param int $id 店铺商品ID
     * @return void 无返回值。
     */
    public function incViewCount(int $id): void
    {
        $this->getModel()->where('id', $id)->inc('view_count')->update();
    }

    /**
     * 应用移动端商品搜索排序。
     *
     * @param mixed $query 查询对象
     * @param string $sort 排序标识
     * @return mixed 已应用搜索排序规则的查询对象。
     */
    protected function applyMobileSearchSort(mixed $query, string $sort): mixed
    {
        return match ($sort) {
            'sales' => $query->order('sales', 'desc')->order('sort', 'desc')->order('id', 'desc'),
            'credit', 'view', 'hot' => $query->order('view_count', 'desc')->order('sort', 'desc')->order('id', 'desc'),
            'price_asc' => $query->order('price', 'asc')->order('sort', 'desc')->order('id', 'desc'),
            'price_desc' => $query->order('price', 'desc')->order('sort', 'desc')->order('id', 'desc'),
            'latest' => $query->order('id', 'desc'),
            default => $query->order('sort', 'desc')->order('id', 'desc'),
        };
    }

    /**
     * 标准化移动端商品搜索关键词。
     *
     * @param string $keyword 原始关键词
     * @return string 去除首尾空白并限制长度后的商品关键词。
     */
    protected function normalizeMobileSearchKeyword(string $keyword): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $keyword) ?: ''), 0, 50);
    }

    /**
     * 构建安全 LIKE 关键词，避免通配符输入放大匹配范围。
     *
     * @param string $keyword 已标准化关键词
     * @return string 转义查询通配符后的安全模糊匹配关键词。
     */
    protected function buildSafeLikeKeyword(string $keyword): string
    {
        return '%' . addcslashes($keyword, "\\%_") . '%';
    }

    /**
     * 更新虚拟展示统计基数（仅前台展示用）
     *
     * @param int $id 店铺商品ID
     * @param int $virtualSales 虚拟销量
     * @param int $virtualViewCount 虚拟浏览量
     * @return bool 更新结果
     */
    public function updateVirtualStats(int $id, int $virtualSales, int $virtualViewCount): bool
    {
        $this->assertNotHotelProductInput([], $id);
        return (bool) $this->getModel()->where('id', $id)->update([
            'virtual_sales' => max(0, $virtualSales),
            'virtual_view_count' => max(0, $virtualViewCount),
        ]);
    }

    /**
     * 检查店铺商品的上下架、审核及删除状态；规格和库存由交易链路另行校验。
     *
     * @param array $product 商品数据
     * @return bool 是否可售
     */
    public function isAvailable(array $product): bool
    {
        return (int) ($product['status'] ?? 0) === ProductEnum::STATUS_OPEN
            && (int) ($product['audit_status'] ?? 0) === ProductEnum::AUDIT_STATUS_PASSED
            && (int) ($product['delete_time'] ?? 0) === 0;
    }
}
