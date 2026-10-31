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
use app\common\model\order\OrderGoods;
use app\common\model\store\StoreCoupon;
use app\common\model\store\StoreCouponIssue;
use app\common\model\store\StoreCouponRelation;
use app\common\model\store\StoreCouponUser;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 用户优惠券服务
 */
class StoreCouponUserService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return StoreCouponUser::class;
    }

    /**
     * 校验商户优惠券领取记录归属
     */
    public function checkMerchantOwner(int $merchantId, int $id, string $label = '优惠券领取记录'): StoreCouponUser
    {
        /** @var StoreCouponUser|null $record */
        $record = $this->get($id);
        if (!$record) {
            throw new AdminResponseException($label . '不存在');
        }
        if ((int) $record['merchant_id'] !== $merchantId) {
            throw new AdminResponseException('无权查看该' . $label);
        }

        return $record;
    }

    /**
     * 用户领取优惠券
     */
    public function receiveCoupon(int $userId, int $couponId): array
    {
        $couponService = app()->make(StoreCouponService::class);
        $coupon = $couponService->get($couponId);

        if (!$coupon) {
            throw new AdminResponseException('优惠券不存在');
        }

        if ($coupon->status != StoreCoupon::STATUS_OPEN) {
            throw new AdminResponseException('优惠券已关闭或失效');
        }

        if ((int) $coupon->is_quantity_limited === 1 && (int) $coupon->remain_quantity <= 0) {
            throw new AdminResponseException('优惠券已领完');
        }

        if ($coupon->is_time_limited) {
            $now = time();
            if ($coupon->receive_start_time && strtotime((string) $coupon->receive_start_time) > $now) {
                throw new AdminResponseException('领取未开始');
            }
            if ($coupon->receive_end_time && strtotime((string) $coupon->receive_end_time) < $now) {
                throw new AdminResponseException('领取已结束');
            }
        }

        if ((int)$coupon->expire_type === StoreCoupon::EXPIRE_TYPE_RANGE) {
            $now = time();
            if ($coupon->valid_start_time && strtotime((string)$coupon->valid_start_time) > $now) {
                throw new AdminResponseException('优惠券使用时间未开始');
            }
            if ($coupon->valid_end_time && strtotime((string)$coupon->valid_end_time) < $now) {
                throw new AdminResponseException('优惠券已过期');
            }
        }

        $existsQuery = $this->getModel()
            ->where('user_id', $userId)
            ->where('coupon_id', $couponId);
        $this->applyUnexpiredUnusedFilter($existsQuery, date('Y-m-d H:i:s'));
        $exists = $existsQuery->find();

        if ($exists) {
            throw new AdminResponseException('您已领取过该优惠券');
        }

        return $this->transaction(function () use ($userId, $couponId, $coupon, $couponService) {
            $couponService->decrStock($couponId);

            $issueService = app()->make(StoreCouponIssueService::class);
            $issue = $issueService->create([
                'coupon_id' => $couponId,
                'merchant_id' => $coupon->merchant_id,
                'batch_no' => date('YmdHis') . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'issue_type' => StoreCouponIssue::ISSUE_TYPE_SEND,
                'total_quantity' => 1,
                'issued_quantity' => 1,
                'status' => StoreCouponIssue::STATUS_COMPLETED,
            ]);

            $validTime = $this->calculateValidTime($coupon);

            $this->create([
                'coupon_id' => $couponId,
                'issue_id' => $issue->id,
                'merchant_id' => $coupon->merchant_id,
                'user_id' => $userId,
                'name' => $coupon->name,
                'discount_amount' => $coupon->discount_amount,
                'min_order_amount' => $coupon->min_order_amount,
                'receive_type' => StoreCouponUser::RECEIVE_TYPE_RECEIVE,
                'valid_start_time' => $validTime['start'],
                'valid_end_time' => $validTime['end'],
                'status' => StoreCouponUser::STATUS_UNUSED,
            ]);

            $userCoupon = $this->getModel()
                ->where('coupon_id', $couponId)
                ->where('issue_id', $issue->id)
                ->where('user_id', $userId)
                ->order('id desc')
                ->find();

            return $userCoupon ? $userCoupon->toArray() : [];
        });
    }

    /**
     * 后台发放单张优惠券给指定用户。
     *
     * 不做主动领取场景的重复领取限制，适用于签到、后台派发等奖励场景。
     *
     * @param int $userId 用户ID
     * @param int $couponId 优惠券ID
     * @param string $receiveType 领取来源类型
     * @return array 用户优惠券记录
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function sendCouponToUser(
        int $userId,
        int $couponId,
        string $receiveType = StoreCouponUser::RECEIVE_TYPE_SEND
    ): array {
        if ($userId <= 0) {
            throw new AdminResponseException('用户不存在');
        }

        $couponService = app()->make(StoreCouponService::class);
        $coupon = $couponService->get($couponId);

        if (!$coupon) {
            throw new AdminResponseException('优惠券不存在');
        }

        if ($coupon->status != StoreCoupon::STATUS_OPEN) {
            throw new AdminResponseException('优惠券已关闭或失效');
        }

        return $this->transaction(function () use ($userId, $couponId, $coupon, $couponService, $receiveType) {
            $couponService->decrStock($couponId);

            $issue = app()->make(StoreCouponIssueService::class)->create([
                'coupon_id' => $couponId,
                'merchant_id' => $coupon->merchant_id,
                'batch_no' => date('YmdHis') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'issue_type' => StoreCouponIssue::ISSUE_TYPE_SEND,
                'total_quantity' => 1,
                'issued_quantity' => 1,
                'status' => StoreCouponIssue::STATUS_COMPLETED,
            ]);

            $validTime = $this->calculateValidTime($coupon);
            $userCoupon = $this->create([
                'coupon_id' => $couponId,
                'issue_id' => $issue->id,
                'merchant_id' => $coupon->merchant_id,
                'user_id' => $userId,
                'name' => $coupon->name,
                'discount_amount' => $coupon->discount_amount,
                'min_order_amount' => $coupon->min_order_amount,
                'receive_type' => $receiveType,
                'valid_start_time' => $validTime['start'],
                'valid_end_time' => $validTime['end'],
                'status' => StoreCouponUser::STATUS_UNUSED,
            ]);

            return $userCoupon->toArray();
        });
    }

    /**
     * 使用优惠券
     */
    public function useCoupon(int $userCouponId, int $orderId): bool
    {
        $userCoupon = $this->get($userCouponId, ['id', 'status', 'valid_end_time']);
        if (!$userCoupon) {
            throw new AdminResponseException('优惠券不存在');
        }

        if ($userCoupon->status != StoreCouponUser::STATUS_UNUSED) {
            throw new AdminResponseException('优惠券已使用或已过期');
        }

        $now = time();
        if ($userCoupon->valid_end_time && strtotime((string) $userCoupon->valid_end_time) < $now) {
            throw new AdminResponseException('优惠券已过期');
        }

        $this->update($userCouponId, [
            'status' => StoreCouponUser::STATUS_USED,
            'use_time' => date('Y-m-d H:i:s'),
            'order_id' => $orderId,
        ]);

        return true;
    }

    /**
     * 获取用户优惠券列表
     */
    public function getUserCoupons(int $userId, int $status = -1, int $merchantId = 0): array
    {
        $where = [
            'user_id' => $userId,
            'merchant_id' => $merchantId > 0 ? $merchantId : 0,
        ];

        if ($status >= 0) {
            $where['status'] = $status;
        }

        return $this->getModel()
            ->where($where)
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取移动端用户优惠券分页列表
     */
    public function getMobileUserList(int $userId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(20, (int)($params['limit'] ?? 10)));
        $status = $params['status'] ?? '';
        $now = date('Y-m-d H:i:s');

        $baseQuery = $this->getModel()
            ->where('user_id', $userId);

        if ($status !== '' && is_numeric($status)) {
            $statusValue = (int)$status;
            if (in_array($statusValue, [
                StoreCouponUser::STATUS_UNUSED,
                StoreCouponUser::STATUS_USED,
                StoreCouponUser::STATUS_EXPIRED,
            ], true)) {
                $this->applyMobileStatusFilter($baseQuery, $statusValue, $now);
            }
        }

        $list = (clone $baseQuery)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        $total = (int)(clone $baseQuery)->count();
        $list = array_map(fn(array $item): array => $this->formatMobileUserCoupon($item), $list);

        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 格式化移动端用户优惠券字段
     */
    protected function formatMobileUserCoupon(array $coupon): array
    {
        $status = $this->resolveMobileUserCouponStatus($coupon);
        $receiveType = (string)($coupon['receive_type'] ?? StoreCouponUser::RECEIVE_TYPE_RECEIVE);
        $validStartTime = (string)($coupon['valid_start_time'] ?? '');
        $validEndTime = (string)($coupon['valid_end_time'] ?? '');

        return [
            'id' => (int)($coupon['id'] ?? 0),
            'coupon_id' => (int)($coupon['coupon_id'] ?? 0),
            'name' => (string)($coupon['name'] ?? ''),
            'title' => (string)($coupon['name'] ?? ''),
            'discount_amount' => (float)($coupon['discount_amount'] ?? 0),
            'min_order_amount' => (float)($coupon['min_order_amount'] ?? 0),
            'status' => $status,
            'status_text' => $this->getMobileStatusText($status),
            'receive_type' => $receiveType,
            'receive_type_text' => $this->getMobileReceiveTypeText($receiveType),
            'valid_start_time' => $validStartTime,
            'valid_end_time' => $validEndTime,
            'expire_text' => $this->getMobileUserExpireText($validStartTime, $validEndTime),
            'use_time' => (string)($coupon['use_time'] ?? ''),
            'order_id' => (int)($coupon['order_id'] ?? 0),
        ];
    }

    /**
     * 应用移动端用户优惠券状态筛选
     */
    protected function applyMobileStatusFilter($query, int $status, string $now): void
    {
        if ($status === StoreCouponUser::STATUS_UNUSED) {
            $this->applyUnexpiredUnusedFilter($query, $now);
            return;
        }

        if ($status === StoreCouponUser::STATUS_EXPIRED) {
            $query->where(function ($subQuery) use ($now) {
                $subQuery->where('status', StoreCouponUser::STATUS_EXPIRED)
                    ->whereOr(function ($expiredQuery) use ($now) {
                        $expiredQuery->where('status', StoreCouponUser::STATUS_UNUSED)
                            ->where('valid_end_time', '<', $now);
                    });
            });
            return;
        }

        $query->where('status', $status);
    }

    /**
     * 应用未使用且未过期的优惠券筛选
     */
    protected function applyUnexpiredUnusedFilter($query, string $now): void
    {
        $query->where('status', StoreCouponUser::STATUS_UNUSED)
            ->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('valid_end_time')
                    ->whereOr('valid_end_time', '')
                    ->whereOr('valid_end_time', '>=', $now);
            });
    }

    /**
     * 解析移动端用户优惠券展示状态
     */
    protected function resolveMobileUserCouponStatus(array $coupon): int
    {
        $status = (int)($coupon['status'] ?? StoreCouponUser::STATUS_UNUSED);
        $validEndTime = (string)($coupon['valid_end_time'] ?? '');

        if ($status === StoreCouponUser::STATUS_UNUSED && $validEndTime !== '' && strtotime($validEndTime) < time()) {
            return StoreCouponUser::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * 获取移动端用户优惠券状态文案
     */
    protected function getMobileStatusText(int $status): string
    {
        return match ($status) {
            StoreCouponUser::STATUS_USED => '已使用',
            StoreCouponUser::STATUS_EXPIRED => '已过期',
            default => '未使用',
        };
    }

    /**
     * 获取移动端用户优惠券来源文案
     */
    protected function getMobileReceiveTypeText(string $receiveType): string
    {
        return match ($receiveType) {
            StoreCouponUser::RECEIVE_TYPE_SEND => '后台发送',
            StoreCouponUser::RECEIVE_TYPE_GIVE => '满赠',
            StoreCouponUser::RECEIVE_TYPE_NEW => '新人',
            StoreCouponUser::RECEIVE_TYPE_BUY => '买赠',
            StoreCouponUser::RECEIVE_TYPE_FIRST => '首单赠送',
            default => '领取',
        };
    }

    /**
     * 获取移动端用户优惠券有效期文案
     */
    protected function getMobileUserExpireText(string $validStartTime, string $validEndTime): string
    {
        if ($validStartTime !== '' && $validEndTime !== '') {
            return date('Y.m.d', strtotime($validStartTime)) . ' - ' . date('Y.m.d', strtotime($validEndTime));
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
     * 获取用户已领取且当前不可重复领取的优惠券 ID 集合
     */
    public function getActiveReceivedCouponIds(int $userId, array $couponIds, int $merchantId = 0): array
    {
        return array_keys($this->getActiveReceivedCouponMap($userId, $couponIds, $merchantId));
    }

    /**
     * 获取用户已领取且当前可使用的优惠券映射。
     *
     * @param int $userId 用户ID。
     * @param array<int> $couponIds 优惠券模板ID列表。
     * @param int $merchantId 商户ID，当前领取状态按券模板匹配。
     * @return array<int, int> coupon_id => store_coupon_user.id。
     */
    public function getActiveReceivedCouponMap(int $userId, array $couponIds, int $merchantId = 0): array
    {
        unset($merchantId);

        $couponIds = array_values(array_unique(array_filter(array_map('intval', $couponIds))));
        if ($userId <= 0 || $couponIds === []) {
            return [];
        }

        $query = $this->getModel()
            ->where('user_id', $userId)
            ->whereIn('coupon_id', $couponIds);
        $this->applyUnexpiredUnusedFilter($query, date('Y-m-d H:i:s'));

        $rows = $query
            ->field(['id', 'coupon_id'])
            ->order('id', 'desc')
            ->select()
            ->toArray();
        $map = [];
        foreach ($rows as $row) {
            $couponId = (int)($row['coupon_id'] ?? 0);
            $userCouponId = (int)($row['id'] ?? 0);
            if ($couponId > 0 && $userCouponId > 0 && !isset($map[$couponId])) {
                $map[$couponId] = $userCouponId;
            }
        }

        return $map;
    }

    /**
     * 获取用户可用优惠券
     */
    public function getUsableCoupons(int $userId, float $orderAmount, int $merchantId = 0): array
    {
        $now = date('Y-m-d H:i:s');

        return $this->getModel()
            ->where('user_id', $userId)
            ->where('merchant_id', $merchantId > 0 ? $merchantId : 0)
            ->where('status', StoreCouponUser::STATUS_UNUSED)
            ->where('min_order_amount', '<=', $orderAmount)
            ->where('valid_start_time', '<=', $now)
            ->where('valid_end_time', '>=', $now)
            ->order('discount_amount', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取订单场景可用优惠券
     */
    public function getAvailableOrderCoupons(int $userId, float $orderAmount, int $merchantId = 0, array $items = []): array
    {
        $now = date('Y-m-d H:i:s');

        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('status', StoreCouponUser::STATUS_UNUSED)
            ->where('min_order_amount', '<=', $orderAmount)
            ->where('valid_start_time', '<=', $now)
            ->where('valid_end_time', '>=', $now)
            ->where('merchant_id', $merchantId > 0 ? $merchantId : 0);

        $userCoupons = $query->order('discount_amount', 'desc')->select()->toArray();
        if ($userCoupons === []) {
            return [];
        }

        $couponIds = array_values(array_unique(array_map(static fn(array $item): int => (int) $item['coupon_id'], $userCoupons)));
        $couponMap = StoreCoupon::whereIn('id', $couponIds)->select()->toArray();
        $couponMap = array_column($couponMap, null, 'id');
        $relationService = app()->make(StoreCouponRelationService::class);
        $result = [];

        foreach ($userCoupons as $userCoupon) {
            $coupon = $couponMap[(int) $userCoupon['coupon_id']] ?? null;
            if ($coupon === null) {
                continue;
            }
            if ((int) ($coupon['status'] ?? 0) !== StoreCoupon::STATUS_OPEN) {
                continue;
            }
            if (!$this->checkCouponScope($coupon, $merchantId, $items, $relationService)) {
                continue;
            }

            $userCoupon['coupon'] = $coupon;
            $result[] = $userCoupon;
        }

        usort($result, function (array $left, array $right) {
            return ($right['discount_amount'] <=> $left['discount_amount'])
                ?: ($left['min_order_amount'] <=> $right['min_order_amount'])
                ?: ((int) $right['id'] <=> (int) $left['id']);
        });

        return $result;
    }

    /**
     * 获取指定用户优惠券详情
     */
    public function getUserCouponDetail(int $userId, int $userCouponId): ?array
    {
        $coupon = $this->getModel()
            ->where('user_id', $userId)
            ->where('id', $userCouponId)
            ->find();

        return $coupon ? $coupon->toArray() : null;
    }

    /**
     * 过期处理
     */
    public function expireCoupons(): int
    {
        $now = date('Y-m-d H:i:s');

        return $this->getModel()
            ->where('status', StoreCouponUser::STATUS_UNUSED)
            ->where('valid_end_time', '<', $now)
            ->update(['status' => StoreCouponUser::STATUS_EXPIRED]);
    }

    /**
     * 计算有效期
     */
    protected function calculateValidTime(StoreCoupon $coupon): array
    {
        if ($coupon->expire_type == StoreCoupon::EXPIRE_TYPE_DAYS) {
            $startTime = date('Y-m-d H:i:s');
            $endTime = date('Y-m-d H:i:s', strtotime('+'. (int) $coupon->valid_days . ' days'));
        } else {
            $startTime = $coupon->valid_start_time;
            $endTime = $coupon->valid_end_time;
        }

        return [
            'start' => $startTime,
            'end' => $endTime,
        ];
    }

    /**
     * 退回优惠券
     */
    public function returnCoupon(int $userCouponId): bool
    {
        $userCoupon = $this->get($userCouponId, ['id', 'status']);
        if (!$userCoupon) {
            throw new AdminResponseException('优惠券不存在');
        }

        if ($userCoupon->status != StoreCouponUser::STATUS_USED) {
            throw new AdminResponseException('只有已使用的优惠券可以退回');
        }

        $this->update($userCouponId, [
            'status' => StoreCouponUser::STATUS_UNUSED,
            'use_time' => null,
            'order_id' => 0,
        ]);

        return true;
    }

    /**
     * 校验优惠券适用范围
     */
    protected function checkCouponScope(array $coupon, int $merchantId, array $items, StoreCouponRelationService $relationService): bool
    {
        $couponMerchantId = (int) ($coupon['merchant_id'] ?? 0);
        $scopeType = (int) ($coupon['scope_type'] ?? StoreCoupon::SCOPE_TYPE_SHOP);

        if ($couponMerchantId !== ($merchantId > 0 ? $merchantId : 0)) {
            return false;
        }

        if (in_array($scopeType, [StoreCoupon::SCOPE_TYPE_SHOP, StoreCoupon::SCOPE_TYPE_PLATFORM], true)) {
            return true;
        }

        if ($items === []) {
            return false;
        }

        $relations = $relationService->getRelationByCouponId((int) ($coupon['id'] ?? 0));
        if ($relations === []) {
            return false;
        }

        foreach ($items as $item) {
            if ($this->matchCouponItemScope($scopeType, $relations, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 校验单个结算项是否命中优惠券范围
     */
    protected function matchCouponItemScope(int $scopeType, array $relations, array $item): bool
    {
        $orderType = (int) ($item['order_type'] ?? 0);
        $goodsId = (int) ($item['goods_id'] ?? 0);
        $product = (array) ($item['product'] ?? []);
        $categoryId = $this->resolveItemCategoryId($orderType, $product);

        foreach ($relations as $relation) {
            $relationType = (int) ($relation['relation_type'] ?? 0);
            $relationId = (int) ($relation['relation_id'] ?? 0);
            if ($relationId <= 0) {
                continue;
            }

            if ($scopeType === StoreCoupon::SCOPE_TYPE_PRODUCT) {
                if ($relationType === StoreCouponRelation::RELATION_TYPE_ARTICLE
                    && $orderType === OrderGoods::ORDER_TYPE_CONTENT
                    && $goodsId === $relationId) {
                    return true;
                }
                if ($relationType === StoreCouponRelation::RELATION_TYPE_SPECIAL
                    && $orderType === OrderGoods::ORDER_TYPE_SPECIAL
                    && $goodsId === $relationId) {
                    return true;
                }
                if ($relationType === StoreCouponRelation::RELATION_TYPE_PRODUCT
                    && $orderType === OrderGoods::ORDER_TYPE_GOODS
                    && $goodsId === $relationId) {
                    return true;
                }
            }

            if ($scopeType === StoreCoupon::SCOPE_TYPE_CATEGORY) {
                if ($categoryId <= 0) {
                    continue;
                }
                if ($relationType === StoreCouponRelation::RELATION_TYPE_CATEGORY_ARTICLE
                    && $orderType === OrderGoods::ORDER_TYPE_CONTENT
                    && $categoryId === $relationId) {
                    return true;
                }
                if ($relationType === StoreCouponRelation::RELATION_TYPE_CATEGORY_SPECIAL
                    && $orderType === OrderGoods::ORDER_TYPE_SPECIAL
                    && $categoryId === $relationId) {
                    return true;
                }
                if ($relationType === StoreCouponRelation::RELATION_TYPE_CATEGORY_PRODUCT
                    && $orderType === OrderGoods::ORDER_TYPE_GOODS
                    && $categoryId === $relationId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 解析结算项分类 ID
     */
    protected function resolveItemCategoryId(int $orderType, array $product): int
    {
        return match ($orderType) {
            OrderGoods::ORDER_TYPE_CONTENT => (int) ($product['article_category_id'] ?? 0),
            OrderGoods::ORDER_TYPE_SPECIAL => (int) ($product['category_id'] ?? 0),
            OrderGoods::ORDER_TYPE_GOODS => (int) ($product['category_id'] ?? ($product['cate_id'] ?? 0)),
            default => 0,
        };
    }
}
