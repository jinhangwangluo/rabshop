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
use app\common\model\store\ProductLibrary;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\plugin\PluginRegistry;
use Rabbit\service\collector\CollectorException;
use Rabbit\service\collector\CollectorService;

/**
 * 商品库服务
 *
 * 商品库 = 单规格商品池（平台/商户共建）：
 * - 每个库商品恒为单规格（product_code 即其 uniqid）
 * - 商户可见范围 = 平台商品(owner_merchant_id=0) ∪ 自己自建的 ∪ 其他商户共享(is_share=1)的
 * - 规格名称保存在 attr_values 中的 spec_name 项，单规格展示与后续上架回填都从该快照读取
 */
class ProductLibraryService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return ProductLibrary::class;
    }

    /**
     * 获取库商品可用范围查询闭包（平台商品 + 自己自建 + 他人共享）
     *
     * @param int $merchantId 当前商户ID（0=平台，不限制）
     * @return \Closure|null 查询闭包，平台端返回 null 不限制
     */
    protected function buildAvailableWhere(int $merchantId): ?\Closure
    {
        if ($merchantId <= 0) {
            return null;
        }

        // 平台商品(owner=0) ∪ 自己自建(owner=merchantId) ∪ 他人共享(is_share=1)
        return function ($query) use ($merchantId) {
            $query->where('owner_merchant_id', 0)
                ->whereOr('owner_merchant_id', $merchantId)
                ->whereOr('is_share', ProductEnum::IS_SHARE_YES);
        };
    }

    /**
     * 库商品资源列表（带可用范围过滤）
     *
     * @param array $params 搜索参数（name/product_code/category_id/brand_id/is_share/status/create_time + _merchant_id）
     * @return array 列表 + 分页
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], array|string|null $sort = null)
    {
        $merchantId = (int) ($params['_merchant_id'] ?? 0);
        $scope = $this->buildAvailableWhere($merchantId);
        $query = $this->getModel();
        if ($scope !== null) {
            $query->where($scope);
        }
        $query->order('sort desc, id desc');

        $query = $query->where(function ($q) use ($params) {
            if (!empty($params['name'])) {
                $q->where('name', 'like', '%' . $params['name'] . '%');
            }
            if (!empty($params['product_code'])) {
                $q->where('product_code', $params['product_code']);
            }
            if (isset($params['category_id']) && $params['category_id'] !== '' && (int)$params['category_id'] > 0) {
                $q->where('category_id', (int)$params['category_id']);
            }
            if (isset($params['brand_id']) && $params['brand_id'] !== '' && (int)$params['brand_id'] > 0) {
                $q->where('brand_id', (int)$params['brand_id']);
            }
            if (isset($params['is_share']) && $params['is_share'] !== '') {
                $q->where('is_share', (int)$params['is_share']);
            }
            if (isset($params['status']) && $params['status'] !== '') {
                $q->where('status', (int)$params['status']);
            }
            if (!empty($params['create_time']) && is_array($params['create_time'])) {
                $q->whereBetweenTime('create_time', $params['create_time'][0], $params['create_time'][1]);
            }
        });

        [$page, $limit] = $this->getPagesValue();
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        foreach ($list as &$item) {
            $item = $this->normalizeLibraryItem($item);
        }
        unset($item);

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 创建库商品（恒单规格）
     *
     * @param array $data 库商品数据（不含 product_code，自动生成 get_sn('SP')）
     * @param int $merchantId 归属商户ID（0=平台自建）
     * @return \think\model\contract\Modelable 创建结果
     */
    public function resourceSave(array $data, int $merchantId = 0)
    {
        $data = $this->normalizeWriteData($data);
        $data['owner_merchant_id'] = $merchantId;
        // 平台商品默认共享，商户自建默认私有
        $data['is_share'] = (int) ($data['is_share'] ?? ($merchantId > 0 ? ProductEnum::IS_SHARE_NO : ProductEnum::IS_SHARE_YES));
        $data['product_code'] = get_sn('SP');
        $data['status'] = (int) ($data['status'] ?? ProductEnum::STATUS_OPEN);
        $data['audit_status'] = (int) ($data['audit_status'] ?? ProductEnum::AUDIT_STATUS_PASSED);

        return $this->create($data);
    }

    /**
     * 采集第三方商品并写入平台商品库。
     *
     * 多 SKU 源商品按 SKU 拆分为多条单规格库商品：店铺商品 SKU 与库商品是 1:1 引用，
     * 上架表单会按 SKU 回填 library_product_id 复用本次创建的行；若只建一条聚合行，
     * 上架时仍会按 SKU 另建一批，聚合行必成孤儿（表现为 14 SKU 采集后库里有 15 条）。
     *
     * @param string $sourceUrl 第三方商品链接或商品 ID。
     * @return array<string,mixed> 表单回填数据；collected_skus 每条附带 library_product_id。
     * @throws AdminResponseException 采集失败或商品信息不完整时抛出。
     */
    public function collectFromExternal(string $sourceUrl): array
    {
        $sourceUrl = trim($sourceUrl);
        if ($sourceUrl === '') {
            throw new AdminResponseException('请输入正确的商品链接');
        }

        try {
            $collector = app()->make(CollectorService::class);
            $platform = $collector->getPlatformByUrl($sourceUrl);
            $item = $collector->collectByUrl($sourceUrl);
        } catch (CollectorException $exception) {
            throw new AdminResponseException($exception->getMessage(), 0, $exception);
        }

        $collectedSkus = $this->extractCollectedSkus($item);
        if (count($collectedSkus) > 1) {
            return $this->collectFromExternalSplitSkus($item, $collectedSkus, $sourceUrl, $platform);
        }

        $product = $this->resourceSave($this->buildCollectedProductData($item, $sourceUrl, $platform), 0);
        $data = $this->resourceRead((int) $product->id, 0);
        $data['is_collect'] = 1;
        $data['collect_url'] = $sourceUrl;
        // 单 SKU：把库商品 id 回传给表单，上架时 resourceUpdate 复用该行而不是再建一条
        if (isset($data['collected_skus'][0]) && is_array($data['collected_skus'][0])) {
            $data['collected_skus'][0]['library_product_id'] = (int) $product->id;
        }
        $data['library_product_id'] = (int) $product->id;

        return $data;
    }

    /**
     * 多 SKU 采集：按 SKU 拆分写入多条单规格库商品。
     *
     * @param array $item 第三方商品详情。
     * @param array $collectedSkus 归一化后的 SKU 列表（extractCollectedSkus 结果）。
     * @param string $sourceUrl 第三方商品链接。
     * @param string $platform 来源平台标识。
     * @return array 表单回填数据，collected_skus 每条附带 library_product_id。
     */
    protected function collectFromExternalSplitSkus(
        array $item,
        array $collectedSkus,
        string $sourceUrl,
        string $platform
    ): array {
        // 复用聚合构建逻辑计算标题/图片/详情/价格兜底等公共字段
        $base = $this->buildCollectedProductData($item, $sourceUrl, $platform);
        $collectedSpecs = $this->extractCollectedSpecs($item);

        $libraryIds = [];
        $this->transaction(function () use ($base, $collectedSkus, &$libraryIds): void {
            foreach ($collectedSkus as $sku) {
                $created = $this->resourceSave($this->buildCollectedSkuProductData($base, $sku), 0);
                $libraryIds[] = (int) $created->id;
            }
        });

        // 回传给表单：每条 SKU 携带对应库商品 id，上架时按 id 更新复用
        $skusWithIds = [];
        foreach ($collectedSkus as $index => $sku) {
            $skusWithIds[] = $sku + ['library_product_id' => $libraryIds[$index]];
        }

        return [
            'id' => $libraryIds[0],
            'name' => $base['name'],
            'keyword' => $base['keyword'],
            'cover' => $base['cover'],
            'pics' => $base['pics'],
            'video' => $base['video'],
            'description' => $base['description'],
            'detail' => $base['detail'],
            'price' => $base['price'],
            'ot_price' => $base['ot_price'],
            'store_price' => 0,
            'stock' => $base['stock'],
            'collected_specs' => $collectedSpecs,
            'collected_skus' => $skusWithIds,
            'is_collect' => 1,
            'collect_url' => $sourceUrl,
        ];
    }

    /**
     * 构建单条 SKU 拆分库商品数据。
     *
     * 命名与上架同步（StoreProductService::buildCustomLibraryData）一致：
     * 商品名 + 空格 + SKU 名，避免上架复用时改名。
     *
     * @param array $base buildCollectedProductData 计算出的公共字段。
     * @param array $sku 单条 SKU（extractCollectedSkus 结果）。
     * @return array 库商品写入数据。
     */
    protected function buildCollectedSkuProductData(array $base, array $sku): array
    {
        $name = trim($base['name'] . ' ' . $sku['sku']);
        $price = (float) $sku['price'] > 0 ? (float) $sku['price'] : (float) $base['price'];
        $cover = (string) ($sku['pic'] ?? '');
        if ($cover === '') {
            $cover = (string) $base['cover'];
        }

        $data = $base;
        $data['name'] = mb_substr($name, 0, 100);
        $data['keyword'] = mb_substr($name, 0, 255);
        $data['price'] = $price;
        $data['ot_price'] = (float) $base['ot_price'] > 0 ? (float) $base['ot_price'] : $price;
        $data['stock'] = max(0, (int) $sku['stock']);
        $data['cover'] = $cover;
        // 拆分行的 attr 只保留本 SKU 的 collected_skus，避免每行冗余全量 SKU
        $data['attr_values'] = array_values(array_filter(
            $base['attr_values'],
            static fn(array $attr): bool => $attr['name'] !== 'collected_skus'
        ));
        $data['attr_values'][] = ['name' => 'collected_skus', 'value' => [$sku]];
        // 拆分行是单规格库商品，规格名称要用采集到的尺码/颜色，不能落成「默认」。
        $specName = $this->resolveCollectedSpecName($sku);
        if ($specName === '') {
            $specName = trim((string) ($sku['sku'] ?? ''));
        }
        if ($specName === '') {
            $specName = '默认';
        }
        $data['spec_name'] = $specName;
        $data['attr_values'] = $this->setAttrValue($data['attr_values'], 'spec_name', $specName);

        return $data;
    }

    /**
     * 更新库商品（product_code 不可修改）
     *
     * @param int|array $id 主键
     * @param array $data 更新数据
     * @param int $merchantId 当前商户ID（越权校验）
     * @return bool 更新结果
     */
    public function resourceUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $data = $this->normalizeWriteData($data);
        $this->assertOwned((int) $id, $merchantId);
        // 库编码即库商品 uniqid，存在时不可修改
        unset($data['product_code']);

        return $this->update($id, $data);
    }

    /**
     * 删除库商品（软删）
     *
     * 已上架的店铺商品副本为独立快照，不受影响。
     *
     * @param int $id 主键
     * @param int $merchantId 当前商户ID（越权校验）
     * @return bool 删除结果
     */
    public function resourceDelete(int $id, int $merchantId = 0)
    {
        $this->assertOwned($id, $merchantId);
        return $this->getModel()->destroy($id);
    }

    public function resourceRead(int|array $id, int $merchantId = 0)
    {
        $row = $this->get((int) $id);
        if (!$row || !$this->isAvailableToMerchant($row->toArray(), $merchantId)) {
            throw new AdminResponseException('库商品不存在或无权查看');
        }
        return $this->normalizeLibraryItem($row->toArray());
    }

    /**
     * 设置共享状态（仅归属商户或平台可操作）
     *
     * @param int $id 库商品ID
     * @param int $isShare 共享状态（0/1）
     * @param int $merchantId 当前商户ID
     * @return bool 更新结果
     */
    public function share(int $id, int $isShare, int $merchantId = 0): bool
    {
        $this->assertOwned($id, $merchantId);
        return (bool) $this->update($id, ['is_share' => (int) $isShare]);
    }

    /**
     * 修改状态（上下架）
     *
     * @param int|array $id 主键
     * @param array $data 状态数据
     * @return bool 更新结果
     */
    public function resourceShowUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertOwned((int) $id, $merchantId);
        return $this->update($id, $data);
    }

    /**
     * 校验库商品归属（平台或归属商户可操作）
     *
     * @param int $id 库商品ID
     * @param int $merchantId 当前商户ID
     * @return void
     * @throws AdminResponseException 无权限时抛出
     */
    public function assertOwned(int $id, int $merchantId): void
    {
        if ($merchantId <= 0) {
            return;
        }

        $ownerId = (int) $this->getModel()->where('id', $id)->value('owner_merchant_id');
        if ($ownerId !== $merchantId) {
            throw new AdminResponseException('无权操作该库商品');
        }
    }

    protected function isAvailableToMerchant(array $item, int $merchantId): bool
    {
        if ($merchantId <= 0) {
            return true;
        }

        $ownerId = (int) ($item['owner_merchant_id'] ?? 0);
        return $ownerId === 0 || $ownerId === $merchantId || (int) ($item['is_share'] ?? 0) === ProductEnum::IS_SHARE_YES;
    }

    /**
     * 规范化写入数据，兼容将规格名称落到 attr_values。
     *
     * @param array $data 原始提交
     * @return array 规范化数据
     */
    protected function normalizeWriteData(array $data): array
    {
        $attrValues = [];
        if (isset($data['attr_values']) && is_array($data['attr_values'])) {
            $attrValues = array_values(array_filter($data['attr_values'], static fn($item): bool => is_array($item)));
        }
        $specName = trim((string) ($data['spec_name'] ?? $this->extractSpecName($attrValues)));
        $attrValues = $this->setAttrValue($attrValues, 'spec_name', $specName);
        $data['attr_values'] = $attrValues;
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
        $data['plugin_code'] = $pluginCode;
        $data['plugin_payload_json'] = $pluginPayload;
        unset($data['plugin_payload']);
        unset($data['spec_name']);

        return $data;
    }

    /**
     * 校验商品库插件载荷只能包含商品归属插件的命名空间。
     *
     * @param string $pluginCode 已验证启用的插件编码。
     * @param array<string, mixed> $payload 请求载荷。
     * @return array<string, array<string, mixed>>
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
     * 将第三方商品详情映射为商品库写入字段。
     *
     * 不把第三方分类/品牌 ID 直接写入本地字段，避免外部 ID 被误解释为本地关联 ID。
     *
     * @param array<string,mixed> $item 第三方商品详情。
     * @return array<string,mixed>
     */
    protected function buildCollectedProductData(
        array $item,
        string $sourceUrl = '',
        string $platform = 'taobao'
    ): array
    {
        $name = trim((string) ($item['title'] ?? ''));
        if ($name === '') {
            throw new AdminResponseException('采集结果缺少商品标题');
        }

        $pics = $this->extractCollectedImages($item);
        $detail = $this->extractCollectedDetail($item);
        $collectedSpecs = $this->extractCollectedSpecs($item);
        $collectedSkus = $this->extractCollectedSkus($item);
        $price = $this->normalizeCollectedPrice($item['promotion_price'] ?? null);
        if ($price <= 0) {
            $price = $this->normalizeCollectedPrice($item['price'] ?? null);
        }
        if ($price <= 0 && $collectedSkus !== []) {
            $skuPrices = array_filter(
                array_column($collectedSkus, 'price'),
                static fn($value): bool => (float) $value > 0
            );
            $price = $skuPrices === [] ? 0 : min($skuPrices);
        }
        $originalPrice = $this->normalizeCollectedPrice($item['orginal_price'] ?? ($item['original_price'] ?? null));
        if ($originalPrice <= 0) {
            $originalPrice = $price;
        }
        $stock = max(0, (int) ($item['num'] ?? 0));
        if ($stock <= 0 && $collectedSkus !== []) {
            $stock = array_sum(array_map(static fn(array $sku): int => (int) ($sku['stock'] ?? 0), $collectedSkus));
        }

        $specName = '默认';
        if (count($collectedSkus) === 1) {
            $resolvedSpecName = $this->resolveCollectedSpecName($collectedSkus[0]);
            if ($resolvedSpecName !== '') {
                $specName = $resolvedSpecName;
            }
        }
        $attributes = [
            ['name' => 'spec_name', 'value' => $specName],
            ['name' => 'source_platform', 'value' => $platform],
            ['name' => 'source_num_iid', 'value' => (string) ($item['num_iid'] ?? '')],
        ];
        $this->appendCollectedAttribute($attributes, 'source_url', $sourceUrl ?: ($item['detail_url'] ?? ''));
        $this->appendCollectedAttribute($attributes, 'brand', $item['brand'] ?? '');
        $this->appendCollectedAttribute($attributes, 'location', $item['location'] ?? '');
        $this->appendCollectedAttribute($attributes, 'seller_nick', $item['nick'] ?? '');
        if ($collectedSpecs !== []) {
            $attributes[] = ['name' => 'collected_specs', 'value' => $collectedSpecs];
        }
        if ($collectedSkus !== []) {
            $attributes[] = ['name' => 'collected_skus', 'value' => $collectedSkus];
        }

        return [
            'name' => mb_substr($name, 0, 100),
            'keyword' => mb_substr($name, 0, 255),
            'category_id' => 0,
            'brand_id' => 0,
            'group_id' => 0,
            'spec_value_id' => 0,
            'price' => $price,
            'ot_price' => $originalPrice,
            'store_price' => 0,
            'stock' => $stock,
            'unit' => '',
            'goods_type' => ProductEnum::GOODS_TYPE_PHYSICAL,
            'cover' => $pics[0] ?? '',
            'pics' => $pics,
            'video' => $this->extractCollectedVideo($item),
            'description' => mb_substr(trim((string) ($item['desc_short'] ?? '')), 0, 255),
            'detail' => $detail,
            'attr_values' => $attributes,
            'service_ids' => '',
            'freight_template_id' => 0,
            'gift_points' => 0,
            'first_commission_rate' => 0,
            'second_commission_rate' => 0,
            'status' => ProductEnum::STATUS_OPEN,
            'audit_status' => ProductEnum::AUDIT_STATUS_PASSED,
            'sort' => 0,
            'is_share' => ProductEnum::IS_SHARE_YES,
        ];
    }

    /**
     * 提取第三方商品图片。
     *
     * @param array<string,mixed> $item 第三方商品详情。
     * @return string[]
     */
    protected function extractCollectedImages(array $item): array
    {
        $urls = [];
        $append = function (mixed $value) use (&$urls): void {
            $url = $this->normalizeCollectedUrl($value);
            if ($url !== '' && $this->isHttpUrl($url) && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        };

        $append($item['pic_url'] ?? '');
        $append($item['pic'] ?? '');
        $append($item['image'] ?? '');
        $append($item['main_pic'] ?? '');
        $append($item['sku_pic'] ?? '');
        $images = $item['item_imgs']['item_img'] ?? ($item['item_imgs'] ?? []);
        foreach (is_array($images) ? $images : [] as $image) {
            $append(is_array($image) ? ($image['url'] ?? '') : $image);
            if (count($urls) >= 9) {
                break;
            }
        }
        $propImages = $item['prop_imgs']['prop_img'] ?? ($item['prop_imgs'] ?? []);
        foreach (is_array($propImages) ? $propImages : [] as $image) {
            $append(is_array($image) ? ($image['url'] ?? '') : $image);
            if (count($urls) >= 9) {
                break;
            }
        }

        return array_slice($urls, 0, 9);
    }

    /**
     * 提取商品详情富文本。
     *
     * @param array<string,mixed> $item 第三方商品详情。
     * @return string
     */
    protected function extractCollectedDetail(array $item): string
    {
        $detail = $this->scalarToString($item['desc'] ?? ($item['detail'] ?? ($item['item_desc'] ?? '')));
        if ($detail === '') {
            $images = $item['desc_img'] ?? ($item['desc_imgs'] ?? []);
            if (is_array($images)) {
                $html = [];
                foreach ($images as $image) {
                    $url = $this->normalizeCollectedUrl(is_array($image) ? ($image['url'] ?? '') : $image);
                    if ($url !== '' && $this->isHttpUrl($url)) {
                        $html[] = '<p><img src="' . htmlspecialchars($url, ENT_QUOTES) . '" /></p>';
                    }
                }
                $detail = implode('', $html);
            }
        }

        if ($detail === '') {
            return '';
        }

        $detail = str_replace('data-lazyload', 'src', $detail);
        $detail = preg_replace('/\bsrc=(["\'])\/\//i', 'src=$1https://', $detail) ?: $detail;
        return (string) preg_replace(
            '/<img[^>]*\s*(?:src|data-lazyload)\s*=\s*["\'][^"\']*\/i\.php\?t\.png[^"\']*["\'][^>]*\s*style\s*=\s*["\'](?:[^"\']*display:\s*none|display:\s*none[^"\']*)["\'][^>]*>/i',
            '',
            $detail
        );
    }

    /**
     * 提取采集规格维度。
     *
     * @param array<string,mixed> $item 第三方商品详情。
     * @return array<int,array{name:string,values:string[]}>
     */
    protected function extractCollectedSpecs(array $item): array
    {
        $specs = [];
        foreach (is_array($item['props_list'] ?? null) ? $item['props_list'] : [] as $prop) {
            $text = is_array($prop) ? implode(':', array_filter(array_map([$this, 'scalarToString'], $prop))) : $this->scalarToString($prop);
            if (!str_contains($text, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $text, 2));
            if ($name === '' || $value === '') {
                continue;
            }
            $specs[$name] = array_values(array_unique([...(array) ($specs[$name] ?? []), $value]));
        }

        return array_map(
            static fn(string $name, array $values): array => ['name' => $name, 'values' => array_values($values)],
            array_keys($specs),
            array_values($specs)
        );
    }

    /**
     * 提取采集 SKU 行。
     *
     * @param array<string,mixed> $item 第三方商品详情。
     * @return array<int,array<string,mixed>>
     */
    protected function extractCollectedSkus(array $item): array
    {
        $skuRows = $item['skus']['sku'] ?? ($item['skus'] ?? ($item['sku'] ?? []));
        if (!is_array($skuRows)) {
            return [];
        }
        if (!array_is_list($skuRows)) {
            $skuRows = array_values($skuRows);
        }

        $skus = [];
        foreach ($skuRows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $values = $this->parseCollectedSkuValues($this->scalarToString($row['properties_name'] ?? ($row['sku_name'] ?? '')));
            $skuName = implode(',', $values);
            if ($skuName === '') {
                $skuName = $this->scalarToString($row['name'] ?? ($row['sku_id'] ?? ''));
            }
            if ($skuName === '') {
                $skuName = '默认';
            }
            $skus[] = [
                'sku_id' => $this->scalarToString($row['sku_id'] ?? ''),
                'sku' => $skuName,
                'spec_values' => $values,
                'price' => $this->normalizeCollectedPrice($row['promotion_price'] ?? ($row['price'] ?? 0)),
                'stock' => max(0, (int) ($row['quantity'] ?? ($row['stock'] ?? ($row['num'] ?? 0)))),
                'pic' => $this->findCollectedSkuImage($item, $this->scalarToString($row['properties'] ?? '')),
                'sort' => 100 - $index,
            ];
        }

        return $skus;
    }

    /**
     * 解析 SKU 规格值文本。
     *
     * @param string $propertiesName 第三方 SKU 属性名称。
     * @return string[]
     */
    protected function parseCollectedSkuValues(string $propertiesName): array
    {
        $values = [];
        foreach (array_filter(explode(';', $propertiesName)) as $segment) {
            $segment = trim((string) $segment);
            if (str_contains($segment, '--')) {
                $parts = explode('--', $segment);
                $segment = (string) end($parts);
            }
            if (str_contains($segment, ':')) {
                $parts = explode(':', $segment);
                $segment = (string) end($parts);
            }
            $segment = trim($segment);
            if ($segment !== '') {
                $values[] = $segment;
            }
        }

        return $values;
    }

    /**
     * 按 SKU 属性匹配规格图片。
     *
     * @param array<string,mixed> $item 第三方商品详情。
     * @param string $properties SKU 属性串。
     * @return string
     */
    protected function findCollectedSkuImage(array $item, string $properties): string
    {
        $propImages = $item['prop_imgs']['prop_img'] ?? ($item['prop_imgs'] ?? []);
        foreach (is_array($propImages) ? $propImages : [] as $image) {
            if (!is_array($image)) {
                continue;
            }
            $imageProperties = $this->scalarToString($image['properties'] ?? '');
            if ($imageProperties !== '' && ($imageProperties === $properties || str_contains($properties, $imageProperties))) {
                return $this->normalizeCollectedUrl($image['url'] ?? '');
            }
        }

        return '';
    }

    /**
     * 提取商品视频地址。
     *
     * @param array<string,mixed> $item 第三方商品详情。
     * @return string
     */
    protected function extractCollectedVideo(array $item): string
    {
        $video = $item['video']['video'] ?? ($item['video'] ?? []);
        if (is_array($video) && array_is_list($video)) {
            $video = $video[0] ?? [];
        }
        return $this->normalizeCollectedUrl(is_array($video) ? ($video['url'] ?? '') : '');
    }

    /**
     * 解析第三方价格。
     *
     * @param mixed $value 原始价格。
     * @return float
     */
    protected function normalizeCollectedPrice(mixed $value): float
    {
        return is_numeric($value) ? max(0, (float) $value) : 0;
    }

    /**
     * 追加非空采集属性。
     *
     * @param array<int,array{name:string,value:string}> $attributes 属性列表。
     * @param string $name 属性名。
     * @param mixed $value 属性值。
     * @return void
     */
    protected function appendCollectedAttribute(array &$attributes, string $name, mixed $value): void
    {
        $value = $name === 'source_url' ? $this->normalizeCollectedUrl($value) : $this->scalarToString($value);
        if ($value !== '') {
            $attributes[] = ['name' => $name, 'value' => $value];
        }
    }

    /**
     * 规范化第三方 URL，兼容 Markdown 链接和协议相对地址。
     *
     * @param mixed $value 原始 URL。
     * @return string
     */
    protected function normalizeCollectedUrl(mixed $value): string
    {
        $url = $this->scalarToString($value);
        if (preg_match('/^\[[^\]]*\]\(([^)]+)\)$/', $url, $matches) === 1) {
            $url = trim($matches[1]);
        }
        $url = html_entity_decode($url, ENT_QUOTES);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        return $url;
    }

    /**
     * 标量值转字符串，数组和对象视为空值。
     *
     * @param mixed $value 原始值。
     * @return string
     */
    protected function scalarToString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * 判断是否为 HTTP(S) URL。
     *
     * @param string $url URL。
     * @return bool
     */
    protected function isHttpUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//i', $url) === 1;
    }

    /**
     * 规范化读取结果，并派生 spec_name。
     *
     * @param array $item 库商品
     * @return array 规范化结果
     */
    protected function normalizeLibraryItem(array $item): array
    {
        $attrValues = $this->normalizeAttrValues($item['attr_values'] ?? []);
        $item['attr_values'] = $attrValues;
        $item['spec_name'] = $this->presentCollectedSpecName($attrValues);
        $item['collected_specs'] = $this->extractAttrValue($attrValues, 'collected_specs', []);
        $item['collected_skus'] = $this->extractAttrValue($attrValues, 'collected_skus', []);
        return $item;
    }

    /**
     * 统一 attr_values 类型。
     *
     * @param mixed $attrValues 属性数组或 JSON
     * @return array
     */
    protected function normalizeAttrValues(mixed $attrValues): array
    {
        if (is_string($attrValues)) {
            $decoded = json_decode($attrValues, true);
            $attrValues = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($attrValues)) {
            return [];
        }

        return array_values(array_filter($attrValues, static fn($item): bool => is_array($item)));
    }

    /**
     * 从属性数组中读取指定属性值。
     *
     * @param array $attrValues 属性数组。
     * @param string $name 属性名。
     * @param mixed $default 默认值。
     * @return mixed
     */
    protected function extractAttrValue(array $attrValues, string $name, mixed $default = null): mixed
    {
        foreach ($attrValues as $item) {
            if ((string) ($item['name'] ?? '') === $name) {
                return $item['value'] ?? $default;
            }
        }

        return $default;
    }

    /**
     * 把采集拆分出的库商品还原成自定义规格。
     *
     * 批量上架不能再走商品库组合：拆分行没有规格模板值，编辑页会把空的
     * spec_value_id 当成 0，再被规格选项里 id=0 的最后一个值（常见是「尺寸 / L」）覆盖。
     * 采集到的尺码、颜色应作为自定义规格回填，商品库选择框保持为空。
     *
     * @param array $libraries 按上架顺序排列的库商品。
     * @param array $storeSkus 已有店铺 SKU，用于回填价格和库存；新建上架可为空。
     * @return array<string,mixed>|null 无可还原的采集规格时返回 null。
     */
    public function buildCollectedCustomSpecConfig(array $libraries, array $storeSkus = []): ?array
    {
        $libraries = array_values(array_filter($libraries, static fn($item): bool => is_array($item)));
        if ($libraries === []) {
            return null;
        }

        $normalized = [];
        foreach ($libraries as $library) {
            $normalized[] = $this->normalizeLibraryItem($library);
        }

        $specCards = $this->collectSpecCards($normalized);
        if ($specCards === null) {
            return null;
        }

        $storeSkuMap = $this->indexStoreSkus($storeSkus);
        $customSpecs = $this->assignCollectedSpecKeys($specCards);
        $customSkus = [];
        $libraryIds = [];
        foreach ($normalized as $index => $library) {
            $libraryId = (int) ($library['id'] ?? 0);
            $productCode = (string) ($library['product_code'] ?? '');
            $storeSku = $storeSkuMap['library'][$libraryId] ?? ($storeSkuMap['uniqid'][$productCode] ?? []);
            $collectedSku = $this->firstCollectedSku($library);
            $specValues = array_values(array_filter(array_map(
                static fn($value): string => trim((string) $value),
                is_array($collectedSku['spec_values'] ?? null) ? $collectedSku['spec_values'] : []
            )));
            $skuText = $this->collectedSkuLabel($library, $specValues);
            $uniqid = trim((string) ($storeSku['uniqid'] ?? ''));
            if ($uniqid === '') {
                $uniqid = $productCode;
            }
            if ($uniqid === '') {
                $uniqid = 'collect_' . ($libraryId > 0 ? $libraryId : ($index + 1));
            }

            $libraryIds[] = $libraryId;
            $customSkus[] = [
                'rowKey' => 'sku-' . ($index + 1),
                'key' => $this->matchCollectedSpecKey($customSpecs, $specValues, $skuText),
                'library_product_id' => $libraryId,
                'uniqid' => $uniqid,
                'sku' => $skuText,
                'price' => (float) ($storeSku['price'] ?? ($collectedSku['price'] ?? ($library['price'] ?? 0))),
                'ot_price' => (float) ($storeSku['ot_price'] ?? ($library['ot_price'] ?? 0)),
                'store_price' => (float) ($storeSku['store_price'] ?? ($library['store_price'] ?? 0)),
                'stock' => (int) ($storeSku['stock'] ?? ($collectedSku['stock'] ?? ($library['stock'] ?? 0))),
                'weight' => (float) ($storeSku['weight'] ?? 0),
                'pics' => (string) ($storeSku['pics'] ?? ($collectedSku['pic'] ?? ($library['cover'] ?? ''))),
                'sort' => (int) ($storeSku['sort'] ?? ($collectedSku['sort'] ?? (100 - $index))),
                'is_default' => $index === 0 ? 1 : (int) ($storeSku['is_default'] ?? 0),
                'status' => (int) ($storeSku['status'] ?? ProductEnum::SKU_STATUS_OPEN),
            ];
        }
        if ($customSkus === []) {
            return null;
        }

        $defaultIndex = 0;
        foreach ($customSkus as $index => $sku) {
            if ((int) ($sku['is_default'] ?? 0) === 1) {
                $defaultIndex = $index;
                break;
            }
        }
        foreach ($customSkus as $index => &$sku) {
            $sku['is_default'] = $index === $defaultIndex ? 1 : 0;
        }
        unset($sku);

        return [
            'spec_mode' => 'custom',
            'custom_spec_type' => count($customSkus) > 1 ? ProductEnum::SPEC_TYPE_MULTI : ProductEnum::SPEC_TYPE_SINGLE,
            'library_product_ids' => array_values(array_filter($libraryIds)),
            'custom_specs' => $customSpecs,
            'custom_skus' => $customSkus,
        ];
    }

    /**
     * 编辑和前台读取时，把已按商品库组合上架的采集商品改回自定义规格展示。
     *
     * 只改返回结构，不回写数据库。没有采集规格的普通商品库组合保持原样。
     *
     * @param array $config 已保存的规格配置。
     * @param array $storeSkus 店铺 SKU，用于保留已编辑的价格和库存。
     * @return array 可直接回填的规格配置。
     */
    public function presentCollectedSpecConfig(array $config, array $storeSkus = []): array
    {
        if (($config['spec_mode'] ?? '') === 'custom' && !empty($config['custom_specs'])) {
            return $config;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($config['library_product_ids'] ?? null) ? $config['library_product_ids'] : []
        ))));
        if ($ids === []) {
            return $config;
        }

        $libraryMap = $this->getAvailableMapByIds($ids, 0);
        $libraries = [];
        foreach ($ids as $id) {
            if (isset($libraryMap[$id])) {
                $libraries[] = $libraryMap[$id];
            }
        }
        $built = $this->buildCollectedCustomSpecConfig($libraries, $storeSkus);
        if ($built === null) {
            return $config;
        }

        return array_merge($config, $built);
    }

    /**
     * 从采集库商品提取规格维度；没有采集快照时返回 null。
     *
     * @param array $libraries 已规范化的库商品。
     * @return array<int,array{name:string,values:string[]}>|null
     */
    protected function collectSpecCards(array $libraries): ?array
    {
        $hasCollected = false;
        $specCards = [];
        foreach ($libraries as $library) {
            $specs = is_array($library['collected_specs'] ?? null) ? $library['collected_specs'] : [];
            $skus = is_array($library['collected_skus'] ?? null) ? $library['collected_skus'] : [];
            if ($specs !== [] || $skus !== []) {
                $hasCollected = true;
            }
            if ($specCards === [] && $specs !== []) {
                foreach ($specs as $spec) {
                    if (!is_array($spec)) {
                        continue;
                    }
                    $name = trim((string) ($spec['name'] ?? ''));
                    $values = [];
                    foreach (is_array($spec['values'] ?? null) ? $spec['values'] : [] as $value) {
                        $text = trim((string) $value);
                        if ($text !== '' && !in_array($text, $values, true)) {
                            $values[] = $text;
                        }
                    }
                    if ($name !== '' && $values !== []) {
                        $specCards[] = ['name' => $name, 'values' => $values];
                    }
                }
            }
        }
        if (!$hasCollected) {
            return null;
        }

        foreach ($libraries as $library) {
            $specValues = $this->firstCollectedSku($library)['spec_values'] ?? [];
            if (!is_array($specValues)) {
                continue;
            }
            foreach ($specValues as $index => $raw) {
                $text = trim((string) $raw);
                if ($text === '' || $this->specCardsContain($specCards, $text)) {
                    continue;
                }
                if (!isset($specCards[$index])) {
                    $specCards[$index] = ['name' => '规格', 'values' => []];
                }
                $specCards[$index]['values'][] = $text;
            }
        }
        if ($specCards === []) {
            $values = [];
            foreach ($libraries as $library) {
                $label = $this->collectedSkuLabel($library, []);
                if ($label !== '' && !in_array($label, $values, true)) {
                    $values[] = $label;
                }
            }
            if ($values === []) {
                return null;
            }
            $specCards = [['name' => '规格', 'values' => $values]];
        }

        return array_values($specCards);
    }

    /**
     * 给采集规格维度和规格值分配稳定 rowKey。
     *
     * @param array $specCards 规格维度。
     * @return array<int,array<string,mixed>>
     */
    protected function assignCollectedSpecKeys(array $specCards): array
    {
        $specs = [];
        foreach (array_values($specCards) as $specIndex => $card) {
            $values = [];
            foreach (array_values($card['values'] ?? []) as $valueIndex => $value) {
                $values[] = [
                    'rowKey' => 'c' . $specIndex . 'v' . $valueIndex,
                    'value' => (string) $value,
                ];
            }
            $specs[] = [
                'rowKey' => 'c' . $specIndex,
                'name' => (string) ($card['name'] ?? '规格'),
                'values' => $values,
            ];
        }

        return $specs;
    }

    /**
     * 按规格值文本匹配维度，避免下标错位时全部落到第一个值。
     *
     * @param array $customSpecs 已分配 rowKey 的规格维度。
     * @param string[] $specValues SKU 规格值。
     * @param string $skuText SKU 展示文本，单维度兜底用。
     * @return string 以 || 连接的规格值 rowKey。
     */
    protected function matchCollectedSpecKey(array $customSpecs, array $specValues, string $skuText): string
    {
        $keys = [];
        $used = [];
        foreach ($customSpecs as $card) {
            $matched = '';
            foreach (is_array($card['values'] ?? null) ? $card['values'] : [] as $value) {
                $rowKey = (string) ($value['rowKey'] ?? '');
                $text = (string) ($value['value'] ?? '');
                if ($rowKey === '' || isset($used[$rowKey])) {
                    continue;
                }
                if (in_array($text, $specValues, true) || ($specValues === [] && $text === $skuText)) {
                    $matched = $rowKey;
                    $used[$rowKey] = true;
                    break;
                }
            }
            if ($matched !== '') {
                $keys[] = $matched;
            }
        }

        return implode('||', $keys);
    }

    /**
     * 读取拆分行上的单条采集 SKU。
     *
     * @param array $library 库商品。
     * @return array<string,mixed>
     */
    protected function firstCollectedSku(array $library): array
    {
        $skus = is_array($library['collected_skus'] ?? null) ? $library['collected_skus'] : [];
        $sku = $skus[0] ?? [];

        return is_array($sku) ? $sku : [];
    }

    /**
     * 采集 SKU 的展示文本，优先用规格值而不是带标题的库商品名。
     *
     * @param array $library 库商品。
     * @param string[] $specValues 已解析的规格值。
     * @return string
     */
    protected function collectedSkuLabel(array $library, array $specValues): string
    {
        if ($specValues !== []) {
            return implode(' / ', $specValues);
        }
        $sku = trim((string) ($this->firstCollectedSku($library)['sku'] ?? ''));
        if ($sku !== '') {
            return $sku;
        }

        return trim((string) ($library['name'] ?? ''));
    }

    /**
     * 判断规格值是否已经出现在某个维度中。
     *
     * @param array $specCards 规格维度。
     * @param string $text 规格值。
     * @return bool
     */
    protected function specCardsContain(array $specCards, string $text): bool
    {
        foreach ($specCards as $card) {
            if (in_array($text, $card['values'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 用库商品 ID 和唯一值索引店铺 SKU。
     *
     * @param array $storeSkus 店铺 SKU。
     * @return array{library:array<int,array>,uniqid:array<string,array>}
     */
    protected function indexStoreSkus(array $storeSkus): array
    {
        $byLibrary = [];
        $byUniqid = [];
        foreach ($storeSkus as $sku) {
            if (!is_array($sku)) {
                continue;
            }
            $libraryId = (int) ($sku['library_product_id'] ?? 0);
            if ($libraryId > 0 && !isset($byLibrary[$libraryId])) {
                $byLibrary[$libraryId] = $sku;
            }
            $uniqid = trim((string) ($sku['uniqid'] ?? ''));
            if ($uniqid !== '' && !isset($byUniqid[$uniqid])) {
                $byUniqid[$uniqid] = $sku;
            }
        }

        return ['library' => $byLibrary, 'uniqid' => $byUniqid];
    }

    /**
     * 采集 SKU 的规格名称。
     *
     * 优先用规格值，避免库商品编辑页把采集规格显示成「默认」。
     *
     * @param array $sku 采集 SKU。
     * @return string 空字符串表示没有可展示的规格名。
     */
    protected function resolveCollectedSpecName(array $sku): string
    {
        $values = [];
        foreach (is_array($sku['spec_values'] ?? null) ? $sku['spec_values'] : [] as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $values[] = $text;
            }
        }
        $name = $values !== [] ? implode(',', $values) : trim((string) ($sku['sku'] ?? ''));
        if ($name === '' || $name === '默认') {
            return '';
        }

        return mb_substr($name, 0, 100);
    }

    /**
     * 读取时还原采集规格名。
     *
     * 历史采集把 spec_name 写成了「默认」或空字符串，编辑页会因此显示默认。
     * 这里只改返回值，真正落库发生在下次保存。
     *
     * @param array $attrValues 属性数组。
     * @return string
     */
    protected function presentCollectedSpecName(array $attrValues): string
    {
        $specName = $this->extractSpecName($attrValues);
        if ($specName !== '' && $specName !== '默认') {
            return $specName;
        }
        $collected = $this->extractAttrValue($attrValues, 'collected_skus', []);
        $sku = is_array($collected) ? ($collected[0] ?? []) : [];
        if (!is_array($sku)) {
            return $specName;
        }
        $resolved = $this->resolveCollectedSpecName($sku);

        return $resolved !== '' ? $resolved : $specName;
    }

    /**
     * 提取规格名称。
     *
     * @param array $attrValues 属性数组
     * @return string
     */
    protected function extractSpecName(array $attrValues): string
    {
        foreach ($attrValues as $item) {
            if ((string) ($item['name'] ?? '') === 'spec_name') {
                return trim((string) ($item['value'] ?? ''));
            }
        }
        return '';
    }

    /**
     * 设置/替换 attr_values 固定项。
     *
     * @param array $attrValues 属性数组
     * @param string $name 属性名
     * @param string $value 属性值
     * @return array
     */
    protected function setAttrValue(array $attrValues, string $name, string $value): array
    {
        $result = [];
        $replaced = false;
        foreach ($attrValues as $item) {
            if ((string) ($item['name'] ?? '') === $name) {
                $result[] = ['name' => $name, 'value' => $value];
                $replaced = true;
                continue;
            }
            $result[] = $item;
        }
        if (!$replaced) {
            $result[] = ['name' => $name, 'value' => $value];
        }
        return $result;
    }

    /**
     * 校验库商品是否对指定商户可用（平台 + 自己 + 共享）
     *
     * @param array $libraryProductIds 库商品ID列表
     * @param int $merchantId 当前商户ID
     * @return array 可用库商品列表（保持传入顺序）
     * @throws AdminResponseException 存在不可用商品时抛出
     */
    public function assertAvailable(array $libraryProductIds, int $merchantId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $libraryProductIds))));
        if ($ids === []) {
            throw new AdminResponseException('请选择商品库商品');
        }

        $list = $this->getModel()
            ->whereIn('id', $ids)
            ->where(function ($query) use ($merchantId) {
                $query->where('owner_merchant_id', 0)
                    ->whereOr('owner_merchant_id', $merchantId)
                    ->whereOr('is_share', ProductEnum::IS_SHARE_YES);
            })
            ->select()
            ->toArray();

        if (count($list) !== count($ids)) {
            throw new AdminResponseException('所选商品库商品包含不可用或已删除的商品');
        }

        return $list;
    }

    /**
     * 按 ID 获取可用库商品映射（供上架组合/选品使用）
     *
     * @param array $ids 库商品ID列表
     * @param int $merchantId 当前商户ID
     * @return array id => 库商品
     */
    public function getAvailableMapByIds(array $ids, int $merchantId = 0): array
    {
        if ($ids === []) {
            return [];
        }

        $list = $this->getModel()
            ->whereIn('id', array_values(array_unique(array_filter(array_map('intval', $ids)))))
            ->where(function ($query) use ($merchantId) {
                if ($merchantId > 0) {
                    $query->where('owner_merchant_id', 0)
                        ->whereOr('owner_merchant_id', $merchantId)
                        ->whereOr('is_share', ProductEnum::IS_SHARE_YES);
                }
            })
            ->select()
            ->toArray();

        $map = [];
        foreach ($list as $item) {
            $map[(int) $item['id']] = $item;
        }
        return $map;
    }

    /**
     * 获取商户可用的库商品选项（select 组件数据源）
     *
     * @param array $params 搜索参数（name/category_id）
     * @param int $merchantId 当前商户ID
     * @return array 选项列表
     */
    public function options(array $params = [], int $merchantId = 0): array
    {
        $query = $this->getModel();
        $scope = $this->buildAvailableWhere($merchantId);
        if ($scope !== null) {
            $query->where($scope);
        }
        $query->where('status', ProductEnum::STATUS_OPEN)
            ->field(['id', 'name', 'product_code', 'price', 'stock', 'unit', 'cover', 'spec_value_id'])
            ->order('sort desc, id desc');

        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . $params['name'] . '%');
        }
        if (isset($params['category_id']) && (int) $params['category_id'] > 0) {
            $query->where('category_id', (int) $params['category_id']);
        }

        return $query->limit(200)->select()->toArray();
    }
}
