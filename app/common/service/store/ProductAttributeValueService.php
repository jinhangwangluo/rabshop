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
use app\common\model\store\ProductAttributeValue;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 商品属性值服务
 *
 * 属性预设值（产地=云南/新疆），属性 input_type=2（预设）时使用。
 */
class ProductAttributeValueService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return ProductAttributeValue::class;
    }

    /**
     * 按属性获取预设值列表
     *
     * @param int $attributeId 属性ID
     * @return array 属性值列表（sort desc, id asc）
     */
    public function getValuesByAttributeId(int $attributeId, int $merchantId = 0): array
    {
        if ($attributeId <= 0) {
            return [];
        }

        $query = $this->getModel()
            ->where('attribute_id', $attributeId)
            ->order('sort desc, id asc');
        if ($merchantId > 0) {
            $query->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', 0)->whereOr('merchant_id', $merchantId);
            });
        }

        return $query->select()->toArray();
    }

    /**
     * 更新属性预设值（值名/图片/排序）
     *
     * @param int $id 属性值ID
     * @param array $data 更新字段（value/pic/sort）
     * @return bool 更新结果
     */
    public function updateValue(int $id, array $data): bool
    {
        $allowed = [];
        if (isset($data['value'])) {
            $allowed['value'] = (string) $data['value'];
        }
        if (isset($data['pic'])) {
            $allowed['pic'] = (string) $data['pic'];
        }
        if (isset($data['sort'])) {
            $allowed['sort'] = (int) $data['sort'];
        }
        if ($allowed === []) {
            return false;
        }
        return (bool) $this->update($id, $allowed);
    }

    public function assertOwned(int $id, int $merchantId): void
    {
        if ($merchantId <= 0) {
            return;
        }
        $ownerId = (int) $this->getModel()->where('id', $id)->value('merchant_id');
        if ($ownerId !== $merchantId) {
            throw new AdminResponseException('无权操作该属性值');
        }
    }

    public function resourceDelete(int $id, int $merchantId = 0)
    {
        $this->assertOwned($id, $merchantId);
        return $this->getModel()->destroy($id);
    }
}
