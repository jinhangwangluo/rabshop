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

use app\common\exception\AdminResponseException;
use app\common\model\store\StoreCoupon;
use app\common\model\store\StoreCouponRelation;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialService;

/**
 * 优惠券服务
 */
class StoreCouponService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return StoreCoupon::class;
    }

    /**
     * 校验商户优惠券归属
     */
    public function checkMerchantOwner(int $merchantId, int $id, string $label = '优惠券'): StoreCoupon
    {
        /** @var StoreCoupon|null $coupon */
        $coupon = $this->get($id);
        if (!$coupon) {
            throw new AdminResponseException($label . '不存在');
        }
        if ((int)$coupon['merchant_id'] !== $merchantId) {
            throw new AdminResponseException('无权操作该' . $label);
        }

        return $coupon;
    }

    /**
     * 预处理商户优惠券保存数据
     */
    public function prepareMerchantPayload(array $data, int $merchantId, int $couponId = 0): array
    {
        $coupon = $couponId > 0 ? $this->checkMerchantOwner($merchantId, $couponId) : null;
        $data['merchant_id'] = $merchantId;
        $data['status'] = (int)($data['status'] ?? StoreCoupon::STATUS_OPEN);
        $data['scope_type'] = (int)($data['scope_type'] ?? StoreCoupon::SCOPE_TYPE_SHOP);
        $data['relation_type'] = (int)($data['relation_type'] ?? StoreCouponRelation::RELATION_TYPE_ARTICLE);
        $data['is_time_limited'] = (int)($data['is_time_limited'] ?? 0);
        $data['is_quantity_limited'] = (int)($data['is_quantity_limited'] ?? 0);
        $data['distribute_type'] = (int)($data['distribute_type'] ?? StoreCoupon::DISTRIBUTE_TYPE_RECEIVE);
        $data['expire_type'] = (int)($data['expire_type'] ?? StoreCoupon::EXPIRE_TYPE_DAYS);
        $data['relations'] = array_values(array_unique(array_filter(array_map('intval', $data['relations'] ?? []))));

        if (!in_array($data['scope_type'], [StoreCoupon::SCOPE_TYPE_SHOP, StoreCoupon::SCOPE_TYPE_PRODUCT], true)) {
            throw new AdminResponseException('商户端仅支持店铺券和商品券');
        }

        $this->normalizeDistributeRules($data);
        $this->normalizeReceiveRules($data);
        $this->normalizeExpireRules($data);
        $this->normalizeQuantityRules($data, $coupon);
        $this->normalizeRelations($data, $merchantId);

        return $data;
    }

    /**
     * 创建优惠券（含关联数据）
     */
    public function resourceSave(array $data): mixed
    {
        return $this->transaction(function () use ($data) {
            $relations = $data['relations'] ?? [];
            $relationType = (int)($data['relation_type'] ?? StoreCouponRelation::RELATION_TYPE_ARTICLE);
            $scopeType = (int)($data['scope_type'] ?? StoreCoupon::SCOPE_TYPE_SHOP);
            unset($data['relations'], $data['relation_type']);

            $coupon = $this->create($data);

            if ($this->isRelationScope($scopeType) && $relations !== []) {
                $this->saveRelations((int)$coupon->id, $relations, $relationType);
            }

            return $coupon;
        });
    }

    /**
     * 更新优惠券
     */
    public function resourceUpdate(int|array $id, array $data): bool
    {
        return $this->transaction(function () use ($id, $data) {
            $relations = $data['relations'] ?? [];
            $relationType = (int)($data['relation_type'] ?? StoreCouponRelation::RELATION_TYPE_ARTICLE);

            /** @var StoreCoupon|null $coupon */
            $coupon = $this->get($id);
            if (!$coupon) {
                throw new AdminResponseException('优惠券不存在');
            }

            $originalScopeType = (int)$coupon->scope_type;
            $scopeType = (int)($data['scope_type'] ?? $originalScopeType);
            unset($data['relations'], $data['relation_type']);

            $this->update($id, $data);

            if ($this->isRelationScope($originalScopeType) || $this->isRelationScope($scopeType)) {
                app()->make(StoreCouponRelationService::class)->deleteByCouponId((int)$id);
            }

            if ($this->isRelationScope($scopeType) && $relations !== []) {
                $this->saveRelations((int)$id, $relations, $relationType);
            }

            return true;
        });
    }

    /**
     * 读取详情（含关联数据）
     */
    public function resourceRead(int|array $id): array
    {
        /** @var StoreCoupon|null $coupon */
        $coupon = $this->get($id);
        if (!$coupon) {
            throw new AdminResponseException('优惠券不存在');
        }

        $data = $coupon->toArray();
        if ($this->isRelationScope((int)($data['scope_type'] ?? StoreCoupon::SCOPE_TYPE_SHOP))) {
            $data['relations'] = app()->make(StoreCouponRelationService::class)->getRelationByCouponId((int)$id);
        } else {
            $data['relations'] = [];
        }

        return $data;
    }

    /**
     * 保存关联关系
     */
    protected function saveRelations(int $couponId, array $relations, int $relationType): void
    {
        app()->make(StoreCouponRelationService::class)->saveRelations($couponId, $relations, $relationType);
    }

    /**
     * 获取可领取优惠券列表
     */
    public function getAvailableCoupons(int $_userId, int $merchantId = 0): array
    {
        unset($_userId);

        $where = [
            'status' => StoreCoupon::STATUS_OPEN,
            'merchant_id' => $merchantId > 0 ? $merchantId : 0,
        ];
        $now = date('Y-m-d H:i:s');

        return $this->getModel()
            ->where($where)
            ->where(function ($query) {
                $query->where('is_quantity_limited', 0)
                    ->whereOr(function ($subQuery) {
                        $subQuery->where('is_quantity_limited', 1)
                            ->where('remain_quantity', '>', 0);
                    });
            })
            ->where(function ($query) use ($now) {
                $query->where('is_time_limited', 0)
                    ->whereOr(function ($subQuery) use ($now) {
                        $subQuery->where('is_time_limited', 1)
                            ->where('receive_start_time', '<=', $now)
                            ->where('receive_end_time', '>=', $now);
                    });
            })
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取移动端领取中心优惠券列表
     */
    public function getMobileReceiveList(int $userId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(20, (int)($params['limit'] ?? 10)));
        $scopeType = $params['scope_type'] ?? '';
        $now = date('Y-m-d H:i:s');

        $baseQuery = $this->getModel()
            ->where('status', StoreCoupon::STATUS_OPEN)
            ->where('scope_type', '<>', StoreCoupon::SCOPE_TYPE_PRODUCT)
            ->whereRaw('(is_quantity_limited = 0 OR (is_quantity_limited = 1 AND remain_quantity > 0))')
            ->whereRaw('(is_time_limited = 0 OR (is_time_limited = 1 AND receive_start_time <= ? AND receive_end_time >= ?))', [$now, $now]);

        if ($scopeType !== '' && is_numeric($scopeType) && (int)$scopeType !== StoreCoupon::SCOPE_TYPE_PRODUCT) {
            $baseQuery->where('scope_type', (int)$scopeType);
        }
        if ($scopeType !== '' && $scopeType === StoreCoupon::SCOPE_TYPE_PLATFORM) {
            $baseQuery->where('merchant_id', 0);
        }

        $list = (clone $baseQuery)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        $total = (int)(clone $baseQuery)->count();
        $couponIds = array_values(array_filter(array_map(static fn(array $item): int => (int)($item['id'] ?? 0), $list)));
        $receivedMap = app()->make(StoreCouponUserService::class)->getActiveReceivedCouponMap($userId, $couponIds, 0);

        $list = array_map(
            fn(array $item): array => $this->formatMobileReceiveCoupon(
                $item,
                isset($receivedMap[(int)($item['id'] ?? 0)]),
                (int)($receivedMap[(int)($item['id'] ?? 0)] ?? 0)
            ),
            $list
        );

        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 获取当前商品可领取优惠券。
     *
     * @param int $userId 用户ID，匿名为0
     * @param array $product 商品详情
     * @param int $limit 返回数量
     * @return array{list: array, total: int}
     */
    public function getMobileProductCoupons(int $userId, array $product, int $limit = 10): array
    {
        $productId = (int)($product['id'] ?? 0);
        $merchantId = (int)($product['merchant_id'] ?? 0);
        $categoryIds = app()->make(ProductCategoryRelationService::class)->getCategoryIdsByProductId($productId);
        $mainCategoryId = (int)($product['category_id'] ?? 0);
        if ($mainCategoryId > 0) {
            $categoryIds[] = $mainCategoryId;
        }
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));

        $relationCouponIds = $this->getProductRelationCouponIds($productId, $categoryIds);
        $now = date('Y-m-d H:i:s');
        $limit = max(1, min(20, $limit));

        $query = $this->getModel()
            ->where('status', StoreCoupon::STATUS_OPEN)
            ->whereRaw('(is_quantity_limited = 0 OR (is_quantity_limited = 1 AND remain_quantity > 0))')
            ->whereRaw('(is_time_limited = 0 OR (is_time_limited = 1 AND receive_start_time <= ? AND receive_end_time >= ?))', [$now, $now])
            ->where(function ($query) use ($now) {
                $query->where('expire_type', StoreCoupon::EXPIRE_TYPE_DAYS)
                    ->whereOr(function ($subQuery) use ($now) {
                        $subQuery->where('expire_type', StoreCoupon::EXPIRE_TYPE_RANGE)
                            ->where('valid_start_time', '<=', $now)
                            ->where('valid_end_time', '>=', $now);
                    });
            })
            ->where(function ($query) use ($merchantId, $relationCouponIds) {
                $query->where(function ($subQuery) use ($merchantId) {
                    $subQuery->where('scope_type', StoreCoupon::SCOPE_TYPE_PLATFORM)
                        ->where('merchant_id', 0);
                })->whereOr(function ($subQuery) use ($merchantId) {
                    $subQuery->where('scope_type', StoreCoupon::SCOPE_TYPE_SHOP)
                        ->where('merchant_id', $merchantId);
                });

                if ($relationCouponIds !== []) {
                    $query->whereOr('id', 'in', $relationCouponIds);
                }
            });

        $list = $query
            ->field([
                'id',
                'merchant_id',
                'name',
                'discount_amount',
                'min_order_amount',
                'scope_type',
                'expire_type',
                'valid_days',
                'valid_start_time',
                'valid_end_time',
                'is_quantity_limited',
                'remain_quantity',
                'sort',
            ])
            ->order('sort desc, id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $couponIds = array_values(array_filter(array_map(static fn(array $item): int => (int)($item['id'] ?? 0), $list)));
        $receivedMap = $userId > 0
            ? app()->make(StoreCouponUserService::class)->getActiveReceivedCouponMap($userId, $couponIds, 0)
            : [];

        return [
            'list' => array_map(
                fn(array $item): array => $this->formatMobileReceiveCoupon(
                    $item,
                    isset($receivedMap[(int)($item['id'] ?? 0)]),
                    (int)($receivedMap[(int)($item['id'] ?? 0)] ?? 0)
                ),
                $list
            ),
            'total' => count($list),
        ];
    }

    /**
     * 获取商品/品类关联优惠券 ID。
     *
     * @param int $productId 商品ID
     * @param array<int> $categoryIds 分类ID列表
     * @return array<int>
     */
    protected function getProductRelationCouponIds(int $productId, array $categoryIds): array
    {
        $relationService = app()->make(StoreCouponRelationService::class);
        $query = $relationService->getModel()
            ->where(function ($query) use ($productId, $categoryIds) {
                $query->where(function ($subQuery) use ($productId) {
                    $subQuery->where('relation_type', StoreCouponRelation::RELATION_TYPE_PRODUCT)
                        ->where('relation_id', $productId);
                });

                if ($categoryIds !== []) {
                    $query->whereOr(function ($subQuery) use ($categoryIds) {
                        $subQuery->where('relation_type', StoreCouponRelation::RELATION_TYPE_CATEGORY_PRODUCT)
                            ->whereIn('relation_id', $categoryIds);
                    });
                }
            });

        return array_values(array_unique(array_filter(array_map('intval', $query->column('coupon_id')))));
    }

    /**
     * 获取签到奖励可选的平台优惠券列表。
     *
     * @param array $params 查询参数：name/scope_type/page/limit
     * @return array
     */
    public function getSigninRewardOptions(array $params = []): array
    {
        [$page, $limit] = $this->getPagesValue();
        $page = $page ?: 1;
        $limit = $limit ?: 20;

        $scopeTypes = [StoreCoupon::SCOPE_TYPE_PLATFORM, StoreCoupon::SCOPE_TYPE_CATEGORY];
        $scopeType = $params['scope_type'] ?? '';

        $query = $this->getModel()
            ->where('merchant_id', 0)
            ->where('status', StoreCoupon::STATUS_OPEN)
            ->whereIn('scope_type', $scopeTypes)
            ->when(trim((string)($params['name'] ?? '')) !== '', function ($query) use ($params) {
                $query->whereLike('name', '%' . trim((string)$params['name']) . '%');
            })
            ->when($scopeType !== '' && in_array((int)$scopeType, $scopeTypes, true), function ($query) use ($scopeType) {
                $query->where('scope_type', (int)$scopeType);
            });

        $total = (int)(clone $query)->count();
        $list = $query
            ->field([
                'id',
                'name',
                'discount_amount',
                'min_order_amount',
                'scope_type',
                'expire_type',
                'valid_days',
                'valid_start_time',
                'valid_end_time',
                'is_quantity_limited',
                'remain_quantity',
                'sort',
            ])
            ->order('sort desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $list = array_map(function (array $coupon): array {
            $scopeType = (int)($coupon['scope_type'] ?? 0);
            $coupon['coupon_id'] = (int)($coupon['id'] ?? 0);
            $coupon['scope_type_text'] = $this->getMobileScopeTypeText($scopeType);
            $coupon['expire_text'] = $this->getMobileExpireText(
                (int)($coupon['expire_type'] ?? StoreCoupon::EXPIRE_TYPE_DAYS),
                (int)($coupon['valid_days'] ?? 0),
                (string)($coupon['valid_start_time'] ?? ''),
                (string)($coupon['valid_end_time'] ?? '')
            );

            return $coupon;
        }, $list);

        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 格式化移动端领取中心优惠券字段
     */
    protected function formatMobileReceiveCoupon(array $coupon, bool $received, int $userCouponId = 0): array
    {
        $scopeType = (int)($coupon['scope_type'] ?? StoreCoupon::SCOPE_TYPE_SHOP);
        $expireType = (int)($coupon['expire_type'] ?? StoreCoupon::EXPIRE_TYPE_DAYS);
        $validDays = (int)($coupon['valid_days'] ?? 0);
        $validStartTime = (string)($coupon['valid_start_time'] ?? '');
        $validEndTime = (string)($coupon['valid_end_time'] ?? '');

        return [
            'id' => (int)($coupon['id'] ?? 0),
            'coupon_id' => (int)($coupon['id'] ?? 0),
            'user_coupon_id' => $userCouponId,
            'merchant_id' => (int)($coupon['merchant_id'] ?? 0),
            'name' => (string)($coupon['name'] ?? ''),
            'title' => (string)($coupon['name'] ?? ''),
            'discount_amount' => (float)($coupon['discount_amount'] ?? 0),
            'min_order_amount' => (float)($coupon['min_order_amount'] ?? 0),
            'scope_type' => $scopeType,
            'scope_type_text' => $this->getMobileScopeTypeText($scopeType),
            'tag' => $this->getMobileScopeTypeText($scopeType),
            'expire_type' => $expireType,
            'valid_days' => $validDays,
            'valid_start_time' => $validStartTime,
            'valid_end_time' => $validEndTime,
            'expire_text' => $this->getMobileExpireText($expireType, $validDays, $validStartTime, $validEndTime),
            'received' => $received,
            'remain_quantity' => (int)($coupon['remain_quantity'] ?? 0),
            'is_quantity_limited' => (int)($coupon['is_quantity_limited'] ?? 0),
        ];
    }

    /**
     * 获取移动端优惠券范围文案
     */
    protected function getMobileScopeTypeText(int $scopeType): string
    {
        return match ($scopeType) {
            StoreCoupon::SCOPE_TYPE_PLATFORM => '通用券',
            StoreCoupon::SCOPE_TYPE_CATEGORY => '品类券',
            StoreCoupon::SCOPE_TYPE_SHOP => '店铺券',
            default => '优惠券',
        };
    }

    /**
     * 获取移动端优惠券有效期文案
     */
    protected function getMobileExpireText(int $expireType, int $validDays, string $validStartTime, string $validEndTime): string
    {
        if ($expireType === StoreCoupon::EXPIRE_TYPE_DAYS) {
            return $validDays > 0 ? '领取后 ' . $validDays . ' 天内有效' : '领取后有效';
        }

        if ($validEndTime !== '') {
            return '有效期至 ' . date('Y.m.d', strtotime($validEndTime));
        }

        if ($validStartTime !== '') {
            return '有效期自 ' . date('Y.m.d', strtotime($validStartTime));
        }

        return '有效期以券面为准';
    }

    /**
     * 扣减库存
     */
    public function decrStock(int $couponId, int $num = 1): bool
    {
        /** @var StoreCoupon|null $coupon */
        $coupon = $this->get($couponId);
        if (!$coupon) {
            throw new AdminResponseException('优惠券不存在');
        }
        if ((int)$coupon->is_quantity_limited !== 1) {
            return true;
        }
        if ((int)$coupon->remain_quantity < $num) {
            throw new AdminResponseException('优惠券库存不足');
        }

        $this->getModel()->where('id', $couponId)->dec('remain_quantity', $num)->update();
        return true;
    }

    /**
     * 增加库存
     */
    public function incrStock(int $couponId, int $num = 1): bool
    {
        /** @var StoreCoupon|null $coupon */
        $coupon = $this->get($couponId);
        if (!$coupon) {
            throw new AdminResponseException('优惠券不存在');
        }
        if ((int)$coupon->is_quantity_limited !== 1) {
            return true;
        }

        $this->getModel()->where('id', $couponId)->inc('remain_quantity', $num)->update();
        return true;
    }

    /**
     * 归一化发放规则
     */
    protected function normalizeDistributeRules(array &$data): void
    {
        if ($data['distribute_type'] === StoreCoupon::DISTRIBUTE_TYPE_FULL) {
            if ((float)($data['min_consume_amount'] ?? 0) <= 0) {
                throw new AdminResponseException('满赠优惠券必须设置消费门槛');
            }
            return;
        }

        $data['min_consume_amount'] = 0;
    }

    /**
     * 归一化领取时间规则
     */
    protected function normalizeReceiveRules(array &$data): void
    {
        if ($data['is_time_limited'] !== 1) {
            $data['receive_start_time'] = null;
            $data['receive_end_time'] = null;
            return;
        }

        if (empty($data['receive_start_time']) || empty($data['receive_end_time'])) {
            throw new AdminResponseException('请完整设置领取时间段');
        }
        if (strtotime((string)$data['receive_start_time']) >= strtotime((string)$data['receive_end_time'])) {
            throw new AdminResponseException('领取结束时间必须晚于开始时间');
        }
    }

    /**
     * 归一化有效期规则
     */
    protected function normalizeExpireRules(array &$data): void
    {
        if ($data['expire_type'] === StoreCoupon::EXPIRE_TYPE_DAYS) {
            if ((int)($data['valid_days'] ?? 0) < 1) {
                throw new AdminResponseException('有效天数必须大于 0');
            }
            $data['valid_start_time'] = null;
            $data['valid_end_time'] = null;
            return;
        }

        if ($data['expire_type'] !== StoreCoupon::EXPIRE_TYPE_RANGE) {
            throw new AdminResponseException('优惠券有效期类型不正确');
        }
        if (empty($data['valid_start_time']) || empty($data['valid_end_time'])) {
            throw new AdminResponseException('请完整设置有效期时间段');
        }
        if (strtotime((string)$data['valid_start_time']) >= strtotime((string)$data['valid_end_time'])) {
            throw new AdminResponseException('有效期结束时间必须晚于开始时间');
        }
        $data['valid_days'] = 0;
    }

    /**
     * 归一化库存规则
     */
    protected function normalizeQuantityRules(array &$data, ?StoreCoupon $coupon = null): void
    {
        if ($data['is_quantity_limited'] !== 1) {
            $data['total_quantity'] = 0;
            $data['remain_quantity'] = 0;
            return;
        }

        $totalQuantity = (int)($data['total_quantity'] ?? 0);
        if ($totalQuantity < 1) {
            throw new AdminResponseException('限量优惠券必须设置发放总量');
        }

        if ($coupon && (int)$coupon->is_quantity_limited === 1) {
            $issuedQuantity = max(0, (int)$coupon->total_quantity - (int)$coupon->remain_quantity);
            if ($totalQuantity < $issuedQuantity) {
                throw new AdminResponseException('发放总量不能小于已领取数量');
            }
            $data['remain_quantity'] = $totalQuantity - $issuedQuantity;
        } else {
            $data['remain_quantity'] = $totalQuantity;
        }

        $data['total_quantity'] = $totalQuantity;
    }

    /**
     * 归一化关联规则
     */
    protected function normalizeRelations(array &$data, int $merchantId): void
    {
        if ($data['scope_type'] === StoreCoupon::SCOPE_TYPE_SHOP) {
            $data['relations'] = [];
            $data['relation_type'] = StoreCouponRelation::RELATION_TYPE_ARTICLE;
            return;
        }

        if (!in_array($data['relation_type'], [StoreCouponRelation::RELATION_TYPE_ARTICLE, StoreCouponRelation::RELATION_TYPE_SPECIAL], true)) {
            throw new AdminResponseException('商户商品券仅支持关联内容或专题');
        }
        if ($data['relations'] === []) {
            throw new AdminResponseException('请至少选择一个适用内容');
        }

        $this->validateMerchantRelations($merchantId, $data['relations'], $data['relation_type']);
    }

    /**
     * 校验商户关联资源归属
     */
    protected function validateMerchantRelations(int $merchantId, array $relations, int $relationType): void
    {
        if ($relationType === StoreCouponRelation::RELATION_TYPE_ARTICLE) {
            $articleService = app()->make(ArticleService::class);
            foreach ($relations as $relationId) {
                $articleService->checkMerchantOwner($merchantId, (int)$relationId, '内容');
            }
            return;
        }

        $specialService = app()->make(SpecialService::class);
        foreach ($relations as $relationId) {
            $specialService->checkMerchantOwner($merchantId, (int)$relationId, '专题');
        }
    }

    /**
     * 是否为关联范围优惠券
     */
    protected function isRelationScope(int $scopeType): bool
    {
        return in_array($scopeType, [StoreCoupon::SCOPE_TYPE_PRODUCT, StoreCoupon::SCOPE_TYPE_CATEGORY], true);
    }
}
