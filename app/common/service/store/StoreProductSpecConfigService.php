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

use app\common\model\store\StoreProductSpecConfig;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 店铺商品规格配置服务
 */
class StoreProductSpecConfigService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return StoreProductSpecConfig::class;
    }

    public function findByProductId(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $row = $this->getModel()->where('store_product_id', $productId)->find();
        return $row ? $row->toArray() : [];
    }

    public function saveByProductId(int $productId, array $data): void
    {
        if ($productId <= 0) {
            return;
        }

        $payload = [
            'store_product_id' => $productId,
            'spec_mode' => (string) ($data['spec_mode'] ?? 'library'),
            'custom_spec_type' => (int) ($data['custom_spec_type'] ?? 0),
            'library_product_ids' => $data['library_product_ids'] ?? [],
            'custom_specs' => $data['custom_specs'] ?? [],
            'custom_skus' => $data['custom_skus'] ?? [],
            'param_attribute_id' => (int) ($data['param_attribute_id'] ?? 0),
            'param_snapshot' => $data['param_snapshot'] ?? [],
        ];

        $row = $this->getModel()->where('store_product_id', $productId)->find();
        if ($row) {
            $this->update((int) $row->id, $payload);
            return;
        }

        $this->create($payload);
    }
}
