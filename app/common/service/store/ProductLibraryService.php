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
     * @param string $sourceUrl 第三方商品链接或商品 ID。
     * @return array<string,mixed> 新建商品库商品。
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

        $product = $this->resourceSave($this->buildCollectedProductData($item, $sourceUrl, $platform), 0);
        $data = $this->resourceRead((int) $product->id, 0);
        $data['is_collect'] = 1;
        $data['collect_url'] = $sourceUrl;

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

        $attributes = [
            ['name' => 'spec_name', 'value' => '默认'],
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
        $item['spec_name'] = $this->extractSpecName($attrValues);
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
