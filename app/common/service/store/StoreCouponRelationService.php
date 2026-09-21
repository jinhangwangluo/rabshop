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

namespace app\common\service\store;

use app\common\model\store\StoreCouponRelation;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 优惠券关联服务
 */
class StoreCouponRelationService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return StoreCouponRelation::class;
    }

    /**
     * 保存关联关系
     */
    public function saveRelations(int $couponId, array $relationIds, int $relationType): bool
    {
        foreach ($relationIds as $relationId) {
            $this->create([
                'coupon_id' => $couponId,
                'relation_type' => $relationType,
                'relation_id' => $relationId,
            ]);
        }

        return true;
    }

    /**
     * 获取优惠券关联
     */
    public function getRelationByCouponId(int $couponId): array
    {
        return $this->getModel()
            ->where('coupon_id', $couponId)
            ->field('id, coupon_id, relation_type, relation_id')
            ->select()
            ->toArray();
    }

    /**
     * 获取优惠券关联的商品ID列表
     */
    public function getProductIds(int $couponId): array
    {
        return $this->getModel()
            ->where('coupon_id', $couponId)
            ->where('relation_type', StoreCouponRelation::RELATION_TYPE_PRODUCT)
            ->column('relation_id');
    }

    /**
     * 获取优惠券关联的品类ID列表
     */
    public function getCategoryIds(int $couponId): array
    {
        return $this->getModel()
            ->where('coupon_id', $couponId)
            ->whereIn('relation_type', [
                StoreCouponRelation::RELATION_TYPE_CATEGORY_ARTICLE,
                StoreCouponRelation::RELATION_TYPE_CATEGORY_SPECIAL,
                StoreCouponRelation::RELATION_TYPE_CATEGORY_PRODUCT,
            ])
            ->column('relation_id');
    }

    /**
     * 获取优惠券关联的内容ID列表
     */
    public function getArticleIds(int $couponId): array
    {
        return $this->getModel()
            ->where('coupon_id', $couponId)
            ->where('relation_type', StoreCouponRelation::RELATION_TYPE_ARTICLE)
            ->column('relation_id');
    }

    /**
     * 获取优惠券关联的专题ID列表
     */
    public function getSpecialIds(int $couponId): array
    {
        return $this->getModel()
            ->where('coupon_id', $couponId)
            ->where('relation_type', StoreCouponRelation::RELATION_TYPE_SPECIAL)
            ->column('relation_id');
    }

    /**
     * 根据 scope_type 返回对应的有效 relation_type 值数组
     */
    public function getRelationTypesByScope(int $scopeType): array
    {
        if ($scopeType === 1) {
            // 商品券子类型
            return [
                StoreCouponRelation::RELATION_TYPE_ARTICLE,
                StoreCouponRelation::RELATION_TYPE_SPECIAL,
                StoreCouponRelation::RELATION_TYPE_PRODUCT,
            ];
        }

        if ($scopeType === 11) {
            // 平台品类券子类型
            return [
                StoreCouponRelation::RELATION_TYPE_CATEGORY_ARTICLE,
                StoreCouponRelation::RELATION_TYPE_CATEGORY_SPECIAL,
                StoreCouponRelation::RELATION_TYPE_CATEGORY_PRODUCT,
            ];
        }

        return [];
    }

    /**
     * 删除优惠券关联
     */
    public function deleteByCouponId(int $couponId): bool
    {
        $this->getModel()->where('coupon_id', $couponId)->delete();
        return true;
    }

    /**
     * 检查商品是否在优惠券适用范围内
     */
    public function checkProductInScope(int $couponId, int $productId, int $categoryId = 0): bool
    {
        // 检查商品
        $productExists = $this->getModel()
            ->where('coupon_id', $couponId)
            ->where('relation_type', StoreCouponRelation::RELATION_TYPE_PRODUCT)
            ->where('relation_id', $productId)
            ->find();

        if ($productExists) {
            return true;
        }

        // 检查品类
        if ($categoryId > 0) {
            $categoryExists = $this->getModel()
                ->where('coupon_id', $couponId)
                ->where('relation_type', StoreCouponRelation::RELATION_TYPE_CATEGORY_PRODUCT)
                ->where('relation_id', $categoryId)
                ->find();

            if ($categoryExists) {
                return true;
            }
        }

        return false;
    }
}
