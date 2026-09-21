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
use app\common\model\store\StoreProductSku;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 商品SKU服务
 *
 * SKU 五项规则：
 * 1. 唯一值：uniqid = 来源库商品 product_code，保存时存在 id 的 SKU 不可修改
 * 2. 删除：软删 + 唯一值不释放（order_goods 快照/cart.product_attr_unique 持久引用）
 * 3. 隐藏：status=0，前台规格弹窗不展示
 * 4. 排序：sort 字段，查询统一 order('sort desc, id asc')
 * 5. 判重：代码层按 (store_product_id, uniqid) 查存在则 update 否则 insert
 */
class StoreProductSkuService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return StoreProductSku::class;
    }

    /**
     * 按 SKU 唯一值解析 SKU（未删除，含隐藏）
     *
     * @param int $productId 店铺商品ID
     * @param string $uniqid SKU唯一值
     * @return array|null SKU 数据，不存在返回 null
     */
    public function resolveSkuByUnique(int $productId, string $uniqid): ?array
    {
        if ($productId <= 0 || $uniqid === '') {
            return null;
        }

        $sku = $this->getModel()
            ->where('store_product_id', $productId)
            ->where('uniqid', $uniqid)
            ->find();

        return $sku ? $sku->toArray() : null;
    }

    /**
     * 获取店铺商品的默认 SKU（is_default=1 优先，否则取排序第一）
     *
     * @param int $productId 店铺商品ID
     * @return array|null SKU 数据
     */
    public function getDefaultSku(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $sku = $this->getModel()
            ->where('store_product_id', $productId)
            ->order('is_default desc, sort desc, id asc')
            ->find();

        return $sku ? $sku->toArray() : null;
    }

    /**
     * 获取店铺商品全部未删除 SKU（含隐藏）
     *
     * @param int $productId 店铺商品ID
     * @return array SKU 列表（sort desc, id asc）
     */
    public function getSkuList(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        return $this->getModel()
            ->where('store_product_id', $productId)
            ->order('sort desc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 保存 SKU（判重 + 唯一值不可改校验）
     *
     * 规则：
     * - 有 id 的 SKU：比对旧 uniqid，不一致抛"SKU唯一值不可修改"
     * - 无 id 的 SKU：按 (store_product_id, uniqid) 查存在则 update，否则 insert
     *
     * @param int $productId 店铺商品ID
     * @param array $skuData SKU 数据（含 id/uniqid/price/stock/status/sort 等）
     * @return int SKU 主键
     * @throws AdminResponseException 唯一值不可修改时抛出
     */
    public function saveSku(int $productId, array $skuData): int
    {
        return $this->transaction(function () use ($productId, $skuData): int {
            $product = app()->make(StoreProductService::class)->getModel()->where('id', $productId)->lock(true)->find();
            if (!$product) {
                throw new AdminResponseException('商品不存在');
            }
            if ((string)($product['plugin_code'] ?? '') === 'hotel') {
                throw new AdminResponseException('酒店商品不使用普通 SKU');
            }
            return $this->persistSku($productId, $skuData);
        });
    }

    /**
     * 在调用方事务已锁定所属商品后保存规格，保持统一库存锁顺序。
     *
     * @param int $productId 店铺商品标识。
     * @param array $skuData 规格可写字段，编辑库存时须同时提交读取时的库存版本。
     * @return int 已保存的商品规格标识。
     */
    protected function persistSku(int $productId, array $skuData): int
    {
        $id = (int) ($skuData['id'] ?? 0);
        $uniqid = trim((string) ($skuData['uniqid'] ?? ''));
        if ($uniqid === '') {
            throw new AdminResponseException('SKU唯一值不能为空');
        }

        if ($id > 0) {
            // 规则2：唯一值不可改
            $old = $this->getModel()->withTrashed()->where('id', $id)->find();
            if (!$old) {
                throw new AdminResponseException('SKU不存在');
            }
            if ((int) ($old['store_product_id'] ?? 0) !== $productId) {
                throw new AdminResponseException('SKU不属于当前商品');
            }
            if ((string) ($old['uniqid'] ?? '') !== $uniqid) {
                throw new AdminResponseException('SKU唯一值不可修改');
            }
            $this->saveExistingSku($id, $skuData, $productId);
            return $id;
        }

        // 规则5：判重，存在则 update 否则 insert
        $exists = $this->getModel()
            ->withTrashed()
            ->where('store_product_id', $productId)
            ->where('uniqid', $uniqid)
            ->find();
        if ($exists) {
            $this->saveExistingSku((int) $exists->id, $skuData, $productId);
            return (int) $exists->id;
        }

        $data = $this->filterSkuFields($skuData, $productId);
        $data['uniqid'] = $uniqid;
        $data['stock'] = (int) ($skuData['stock'] ?? 0);
        $data['stock_version'] = 0;
        $created = $this->getModel()->create($data);
        return (int) $created->id;
    }

    /**
     * 保存已有规格时校验库存版本，禁止旧表单覆盖库存计数。
     *
     * @param int $id 已有商品规格标识。
     * @param array $skuData 规格可写字段，编辑库存时须同时提交读取时的库存版本。
     * @param int $productId 店铺商品标识。
     * @return void 无返回值。
     */
    protected function saveExistingSku(int $id, array $skuData, int $productId): void
    {
        $data = $this->filterSkuFields($skuData, $productId, false);
        unset($data['sales']);
        $query = $this->getModel()->withTrashed()->where('id', $id)->where('store_product_id', $productId);
        if (array_key_exists('stock', $skuData)) {
            $version = $skuData['stock_version'] ?? null;
            if ((!is_int($version) && !is_string($version)) || !preg_match('/^\d{1,18}$/', (string) $version)) {
                throw new AdminResponseException('库存版本缺失，请刷新商品后重新保存');
            }
            if ((int) $skuData['stock'] < 0) {
                throw new AdminResponseException('商品库存不能为负数');
            }
            $affected = $query->where('stock_version', (int) $version)->inc('stock_version', 1)->update($data);
            if ($affected !== 1) {
                throw new AdminResponseException('商品库存已变化，请刷新后重新保存');
            }
            return;
        }
        $query->update($data);
    }

    /**
     * 过滤 SKU 可写字段并补齐默认值
     *
     * @param array $skuData 原始数据
     * @param int $productId 店铺商品ID
     * @param bool $withDefaults 新 SKU 补默认值；编辑只写显式提交字段
     * @return array 可写字段
     */
    protected function filterSkuFields(array $skuData, int $productId, bool $withDefaults = true): array
    {
        $fields = ['library_product_id', 'store_manage_id', 'spec_value_ids', 'sku', 'pics', 'bar_code', 'bar_number',
            'price', 'ot_price', 'store_price', 'stock', 'sales', 'unit', 'weight', 'status', 'sort', 'is_default', 'guard', 'guard_status'];
        $data = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $skuData)) {
                $data[$field] = $skuData[$field];
            }
        }
        $data['store_product_id'] = $productId;
        $data['store_manage_id'] = 0; // 前置仓预留，恒0
        foreach (['status' => ProductEnum::SKU_STATUS_OPEN, 'sort' => 0, 'is_default' => 0] as $field => $default) {
            if ($withDefaults || array_key_exists($field, $data)) {
                $data[$field] = (int) ($data[$field] ?? $default);
            }
        }
        if (array_key_exists('stock', $data)) {
            $data['stock'] = (int) $data['stock'];
            if ($data['stock'] < 0) {
                throw new AdminResponseException('商品库存不能为负数');
            }
        }
        foreach (['weight', 'price', 'ot_price', 'store_price'] as $field) {
            if ($withDefaults || array_key_exists($field, $data)) {
                $data[$field] = (float) ($data[$field] ?? 0);
            }
        }
        if (array_key_exists('delete_time', $skuData)) {
            $data['delete_time'] = $skuData['delete_time'];
        }
        return $data;
    }

    /**
     * 校验 SKU 是否属于指定店铺商品。
     *
     * @param int $productId 店铺商品ID
     * @param int $skuId SKU主键
     * @return array SKU 数据
     */
    public function assertSkuBelongs(int $productId, int $skuId): array
    {
        $sku = $this->getModel()->withTrashed()->where('id', $skuId)->find();
        if (!$sku || (int) ($sku['store_product_id'] ?? 0) !== $productId) {
            throw new AdminResponseException('SKU不属于当前商品');
        }
        app()->make(StoreProductService::class)->assertNotHotelProductInput([], $productId);

        return $sku->toArray();
    }

    /**
     * 删除 SKU（软删，唯一值不释放）
     *
     * @param int $skuId SKU主键
     * @param int $productId 所属店铺商品标识，为零时仅按规格标识定位。
     * @return bool 删除结果
     */
    public function deleteSku(int $skuId, int $productId = 0): bool
    {
        if ($productId > 0) {
            $this->assertSkuBelongs($productId, $skuId);
        }
        return (bool) $this->getModel()->destroy($skuId);
    }

    /**
     * 修改 SKU 状态（隐藏/显示）
     *
     * @param int $skuId SKU主键
     * @param int $status 状态（0隐藏/1正常）
     * @param int $productId 所属店铺商品标识，为零时仅按规格标识定位。
     * @return bool 更新结果
     */
    public function updateSkuStatus(int $skuId, int $status, int $productId = 0): bool
    {
        if ($productId > 0) {
            $this->assertSkuBelongs($productId, $skuId);
        }
        return (bool) $this->getModel()->where('id', $skuId)->update([
            'status' => (int) $status,
        ]);
    }

    /**
     * 修改 SKU 排序
     *
     * @param int $skuId SKU主键
     * @param int $sort 排序值
     * @param int $productId 所属店铺商品标识，为零时仅按规格标识定位。
     * @return bool 更新结果
     */
    public function updateSkuSort(int $skuId, int $sort, int $productId = 0): bool
    {
        if ($productId > 0) {
            $this->assertSkuBelongs($productId, $skuId);
        }
        return (bool) $this->getModel()->where('id', $skuId)->update([
            'sort' => (int) $sort,
        ]);
    }

    /**
     * 获取前台规格数据（6.4 数据结构，前端对接文档核心）
     *
     * 返回 spec_list（规格维度 + 可用性）+ sku_list（仅 status=1 未删，
     * 按 sort desc, id asc）+ default_sku。
     *
     * @param int $productId 店铺商品ID
     * @return array 规格数据结构
     */
    public function getSkuData(int $productId): array
    {
        $skus = $this->getModel()
            ->where('store_product_id', $productId)
            ->where('status', ProductEnum::SKU_STATUS_OPEN)
            ->order('sort desc, id asc')
            ->select()
            ->toArray();

        $config = app()->make(StoreProductSpecConfigService::class)->findByProductId($productId);
        $skuSpecKeyMap = $this->buildSkuSpecKeyMap($config);
        foreach ($skus as &$sku) {
            $sku['spec_key'] = $skuSpecKeyMap[(string)($sku['uniqid'] ?? '')] ?? '';
        }
        unset($sku);

        $specList = $this->buildSpecListFromConfig($config, $skus);

        // 默认 SKU：is_default=1 优先，否则第一个
        $defaultSku = null;
        foreach ($skus as $sku) {
            if ((int) ($sku['is_default'] ?? 0) === 1) {
                $defaultSku = $sku;
                break;
            }
        }
        if ($defaultSku === null && $skus !== []) {
            $defaultSku = $skus[0];
        }

        return [
            'product_id' => $productId,
            'spec_type' => (int) ($config['custom_spec_type'] ?? ProductEnum::SPEC_TYPE_SINGLE),
            'spec_list' => array_values($specList),
            'sku_list' => $skus,
            'default_sku' => $defaultSku,
        ];
    }

    /**
     * 根据规格配置生成 SKU 唯一值到规格键的映射。
     *
     * @param array $config 商品规格配置
     * @return array<string, string> 以规格唯一值为键的规格组合键映射。
     */
    protected function buildSkuSpecKeyMap(array $config): array
    {
        $configSkus = is_array($config['custom_skus'] ?? null) ? $config['custom_skus'] : [];
        $map = [];
        foreach ($configSkus as $configSku) {
            $uniqid = (string)($configSku['uniqid'] ?? '');
            $key = (string)($configSku['key'] ?? '');
            if ($uniqid === '' || $key === '') {
                continue;
            }
            $map[$uniqid] = $key;
        }

        return $map;
    }

    /**
     * 从商品规格 JSON 快照构建前台规格列表。
     *
     * @param array $config 商品规格配置
     * @param array $skus 有效 SKU 列表
     * @return array 规格维度列表
     */
    protected function buildSpecListFromConfig(array $config, array $skus): array
    {
        $specs = is_array($config['custom_specs'] ?? null) ? $config['custom_specs'] : [];
        $configSkus = is_array($config['custom_skus'] ?? null) ? $config['custom_skus'] : [];
        if ($specs === []) {
            return [];
        }

        $skuMap = [];
        foreach ($skus as $sku) {
            $skuMap[(string) ($sku['uniqid'] ?? '')] = $sku;
        }
        $availableValueKeys = [];
        foreach ($configSkus as $configSku) {
            $sku = $skuMap[(string) ($configSku['uniqid'] ?? '')] ?? null;
            if (!$sku || (int) ($sku['stock'] ?? 0) <= 0) {
                continue;
            }
            foreach (explode('||', (string) ($configSku['key'] ?? '')) as $valueKey) {
                if ($valueKey !== '') {
                    $availableValueKeys[$valueKey] = true;
                }
            }
        }

        $specList = [];
        foreach ($specs as $specIndex => $spec) {
            $values = [];
            foreach (($spec['values'] ?? []) as $valueIndex => $value) {
                $valueKey = (string) ($value['rowKey'] ?? '');
                $values[] = [
                    'id' => $valueKey,
                    'value' => (string) ($value['value'] ?? ''),
                    'pic' => (string) ($value['pic'] ?? ''),
                    'sort' => 100 - $valueIndex,
                    'status' => 1,
                    'available' => isset($availableValueKeys[$valueKey]),
                ];
            }
            $specList[] = [
                'spec_id' => (string) ($spec['rowKey'] ?? ''),
                'name' => (string) ($spec['name'] ?? ''),
                'sort' => 100 - $specIndex,
                'values' => $values,
            ];
        }

        return $specList;
    }
}
