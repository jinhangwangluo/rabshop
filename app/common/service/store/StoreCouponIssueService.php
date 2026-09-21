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
use app\common\model\order\Order;
use app\common\model\store\StoreCoupon;
use app\common\model\store\StoreCouponIssue;
use app\common\model\store\StoreCouponUser;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\order\OrderService;

/**
 * 优惠券发放记录服务
 */
class StoreCouponIssueService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return StoreCouponIssue::class;
    }

    /**
     * 下单时记录并预约新人/首单/消费满赠优惠券。
     *
     * @param array $order 订单快照，需包含 id、order_sn、user_id、merchant_id、order_role、pay_amount。
     * @return array 已创建的待发放记录。
     */
    public function recordOrderGiftCoupons(array $order): array
    {
        $orderSn = (string)($order['order_sn'] ?? '');
        $userId = (int)($order['user_id'] ?? 0);
        $merchantId = (int)($order['merchant_id'] ?? 0);
        $orderRole = (int)($order['order_role'] ?? Order::ROLE_NORMAL);
        $orderAmount = (float)($order['pay_amount'] ?? 0);

        if ($orderSn === '' || $userId <= 0) {
            return [];
        }

        $merchantIds = $this->resolveGiftMerchantIds($merchantId, $orderRole);
        if ($merchantIds === []) {
            return [];
        }

        $coupons = $this->getOrderGiftCandidateCoupons($merchantIds);
        if ($coupons === []) {
            return [];
        }

        $couponService = app()->make(StoreCouponService::class);
        $created = [];

        foreach ($coupons as $coupon) {
            $couponId = (int)($coupon['id'] ?? 0);
            $issueType = $this->getIssueTypeByDistributeType((int)($coupon['distribute_type'] ?? 0));
            if ($couponId <= 0 || $issueType <= 0) {
                continue;
            }
            if (!$this->isOrderGiftCouponMatched($coupon, $userId, $orderAmount)) {
                continue;
            }
            if ($this->hasOrderGiftIssue($couponId, $orderSn, $issueType)) {
                continue;
            }

            $reserved = false;
            try {
                // 下单时先占用库存，支付后只负责生成用户券；取消待支付订单时释放预约库存。
                $couponService->decrStock($couponId);
                $reserved = true;
                $issue = $this->create([
                    'coupon_id' => $couponId,
                    'merchant_id' => (int)($coupon['merchant_id'] ?? 0),
                    'batch_no' => $orderSn,
                    'issue_type' => $issueType,
                    'total_quantity' => 1,
                    'issued_quantity' => 0,
                    'status' => StoreCouponIssue::STATUS_PENDING,
                ]);
                $created[] = $issue->toArray();
            } catch (\Throwable $e) {
                if ($reserved) {
                    try {
                        $couponService->incrStock($couponId);
                    } catch (\Throwable $restoreException) {
                        \think\facade\Log::error('[coupon] restore order gift coupon stock failed', [
                            'order_sn' => $orderSn,
                            'coupon_id' => $couponId,
                            'message' => $restoreException->getMessage(),
                        ]);
                    }
                }
                \think\facade\Log::error('[coupon] record order gift coupon failed', [
                    'order_sn' => $orderSn,
                    'coupon_id' => $couponId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * 支付成功后发放订单预约赠券。
     *
     * @param array $order 订单支付成功快照。
     * @return int 成功发放数量。
     */
    public function grantOrderGiftCoupons(array $order): int
    {
        $orderSn = (string)($order['order_sn'] ?? '');
        $userId = (int)($order['user_id'] ?? 0);
        if ($orderSn === '' || $userId <= 0) {
            return 0;
        }

        $issues = $this->getModel()
            ->where('batch_no', $orderSn)
            ->whereIn('issue_type', [
                StoreCouponIssue::ISSUE_TYPE_FULL,
                StoreCouponIssue::ISSUE_TYPE_NEW,
                StoreCouponIssue::ISSUE_TYPE_FIRST,
            ])
            ->where('status', StoreCouponIssue::STATUS_PENDING)
            ->order('id asc')
            ->select()
            ->toArray();
        if ($issues === []) {
            return 0;
        }

        $successCount = 0;
        foreach ($issues as $issue) {
            try {
                $successCount += $this->grantGiftIssueToUser($issue, $order);
            } catch (\Throwable $e) {
                \think\facade\Log::error('[coupon] grant order gift coupon failed', [
                    'order_sn' => $orderSn,
                    'issue_id' => (int)($issue['id'] ?? 0),
                    'coupon_id' => (int)($issue['coupon_id'] ?? 0),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $successCount;
    }

    /**
     * 取消待支付订单时释放未发放赠券库存。
     */
    public function cancelOrderGiftCoupons(string $orderSn): int
    {
        if ($orderSn === '') {
            return 0;
        }

        $issues = $this->getModel()
            ->where('batch_no', $orderSn)
            ->whereIn('issue_type', [
                StoreCouponIssue::ISSUE_TYPE_FULL,
                StoreCouponIssue::ISSUE_TYPE_NEW,
                StoreCouponIssue::ISSUE_TYPE_FIRST,
            ])
            ->where('status', StoreCouponIssue::STATUS_PENDING)
            ->order('id asc')
            ->select()
            ->toArray();
        if ($issues === []) {
            return 0;
        }

        $couponService = app()->make(StoreCouponService::class);
        $cancelCount = 0;

        foreach ($issues as $issue) {
            $issueId = (int)$issue['id'];
            $releaseQuantity = max(0, (int)$issue['total_quantity'] - (int)$issue['issued_quantity']);
            try {
                if ($releaseQuantity > 0) {
                    $couponService->incrStock((int)$issue['coupon_id'], $releaseQuantity);
                }
                $this->update($issueId, ['status' => StoreCouponIssue::STATUS_CANCELLED]);
                $cancelCount++;
            } catch (\Throwable $e) {
                \think\facade\Log::error('[coupon] cancel order gift coupon failed', [
                    'order_sn' => $orderSn,
                    'issue_id' => $issueId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $cancelCount;
    }

    /**
     * 获取指定订单本次实际到账的赠送优惠券。
     *
     * @param int $userId 用户 ID。
     * @param array $orderSns 主支付单及子订单号列表。
     * @return array 已发放到用户账户的优惠券列表。
     */
    public function getGrantedOrderGiftCoupons(int $userId, array $orderSns): array
    {
        $orderSns = array_values(array_unique(array_filter(array_map(
            static fn($orderSn): string => trim((string)$orderSn),
            $orderSns
        ))));
        if ($userId <= 0 || $orderSns === []) {
            return [];
        }

        $issues = $this->getModel()
            ->whereIn('batch_no', $orderSns)
            ->whereIn('issue_type', [
                StoreCouponIssue::ISSUE_TYPE_FULL,
                StoreCouponIssue::ISSUE_TYPE_NEW,
                StoreCouponIssue::ISSUE_TYPE_FIRST,
            ])
            ->where('status', StoreCouponIssue::STATUS_COMPLETED)
            ->order('id asc')
            ->select()
            ->toArray();
        if ($issues === []) {
            return [];
        }

        $issueMap = [];
        foreach ($issues as $issue) {
            $issueMap[(int)($issue['id'] ?? 0)] = $issue;
        }

        $issueIds = array_values(array_filter(array_map('intval', array_keys($issueMap))));
        if ($issueIds === []) {
            return [];
        }

        $userCoupons = app()->make(StoreCouponUserService::class)
            ->getModel()
            ->where('user_id', $userId)
            ->whereIn('issue_id', $issueIds)
            ->order('id asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($userCoupons as $coupon) {
            $issueId = (int)($coupon['issue_id'] ?? 0);
            $issue = $issueMap[$issueId] ?? [];
            $issueType = (int)($issue['issue_type'] ?? 0);
            $minOrderAmount = (float)($coupon['min_order_amount'] ?? 0);
            $validEndTime = (string)($coupon['valid_end_time'] ?? '');

            $result[] = [
                'id' => (int)($coupon['id'] ?? 0),
                'coupon_id' => (int)($coupon['coupon_id'] ?? 0),
                'issue_id' => $issueId,
                'order_sn' => (string)($issue['batch_no'] ?? ''),
                'name' => (string)($coupon['name'] ?? ''),
                'discount_amount' => (float)($coupon['discount_amount'] ?? 0),
                'min_order_amount' => $minOrderAmount,
                'threshold_text' => $minOrderAmount > 0
                    ? '满' . $this->formatGiftCouponMoney($minOrderAmount) . '元可用'
                    : '无门槛可用',
                'receive_type' => (string)($coupon['receive_type'] ?? ''),
                'receive_type_text' => $this->getGiftCouponSourceText($issueType),
                'tag_text' => $this->getGiftCouponTagText($issueType),
                'valid_start_time' => (string)($coupon['valid_start_time'] ?? ''),
                'valid_end_time' => $validEndTime,
                'expire_text' => $validEndTime !== ''
                    ? '有效期至 ' . date('Y.m.d H:i:s', strtotime($validEndTime))
                    : '有效期以券面为准',
                'status' => (int)($coupon['status'] ?? StoreCouponUser::STATUS_UNUSED),
                'action_url' => '/pages/users/usecoupon/index',
            ];
        }

        return $result;
    }

    /**
     * 发放优惠券
     */
    public function issueCoupon(int $couponId, int $issueType, int $totalQuantity, int $merchantId = 0, array $userIds = []): array
    {
        $couponService = app()->make(StoreCouponService::class);
        $coupon = $couponService->get($couponId);

        if (!$coupon) {
            throw new AdminResponseException('优惠券不存在');
        }

        if ($coupon->status != StoreCoupon::STATUS_OPEN) {
            throw new AdminResponseException('优惠券已关闭或失效');
        }

        return $this->transaction(function () use ($couponId, $issueType, $totalQuantity, $merchantId, $userIds, $coupon, $couponService) {
            // 生成批次号
            $batchNo = $this->generateBatchNo();

            // 创建发放记录
            $issue = $this->create([
                'coupon_id' => $couponId,
                'merchant_id' => $merchantId ?: $coupon->merchant_id,
                'batch_no' => $batchNo,
                'issue_type' => $issueType,
                'total_quantity' => $totalQuantity,
                'issued_quantity' => 0,
                'status' => StoreCouponIssue::STATUS_ISSUING,
            ]);

            // 如果指定了用户列表，直接发放
            if (!empty($userIds)) {
                $this->issueToUsers($issue->id, $couponId, $userIds, $issueType, $merchantId ?: $coupon->merchant_id);
            }

            return $issue->toArray();
        });
    }

    /**
     * 批量发放给用户
     */
    public function issueToUsers(int $issueId, int $couponId, array $userIds, int $issueType, int $merchantId): int
    {
        $couponService = app()->make(StoreCouponService::class);
        $userCouponService = app()->make(StoreCouponUserService::class);
        $coupon = $couponService->get($couponId);

        if (!$coupon) {
            throw new AdminResponseException('优惠券不存在');
        }

        $successCount = 0;
        $receiveType = $this->getReceiveTypeByIssueType($issueType);

        foreach ($userIds as $userId) {
            try {
                $this->transaction(function () use ($issueId, $couponId, $userId, $merchantId, $coupon, $couponService, $userCouponService, $receiveType, &$successCount) {
                    // 扣减库存
                    $couponService->decrStock($couponId);

                    // 计算有效期
                    $validTime = $this->calculateValidTime($coupon);

                    // 创建用户优惠券
                    $userCouponService->create([
                        'coupon_id' => $couponId,
                        'issue_id' => $issueId,
                        'merchant_id' => $merchantId,
                        'user_id' => $userId,
                        'name' => $coupon->name,
                        'discount_amount' => $coupon->discount_amount,
                        'min_order_amount' => $coupon->min_order_amount,
                        'receive_type' => $receiveType,
                        'valid_start_time' => $validTime['start'],
                        'valid_end_time' => $validTime['end'],
                        'status' => StoreCouponUser::STATUS_UNUSED,
                    ]);

                    // 更新发放数量
                    $this->getModel()->where('id', $issueId)->inc('issued_quantity')->update();

                    $successCount++;
                });
            } catch (\Exception $e) {
                // 记录失败日志，继续处理下一个
                \think\facade\Log::error("发放优惠券失败: userId={$userId}, error=" . $e->getMessage());
            }
        }

        // 检查是否发放完成
        $issue = $this->get($issueId);
        if ($issue && $issue->issued_quantity >= $issue->total_quantity) {
            $this->update($issueId, ['status' => StoreCouponIssue::STATUS_COMPLETED]);
        }

        return $successCount;
    }

    /**
     * 生成批次号
     */
    protected function generateBatchNo(): string
    {
        return date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * 根据发放类型获取领取类型
     */
    protected function getReceiveTypeByIssueType(int $issueType): string
    {
        $map = [
            StoreCouponIssue::ISSUE_TYPE_SEND => StoreCouponUser::RECEIVE_TYPE_SEND,
            StoreCouponIssue::ISSUE_TYPE_FULL => StoreCouponUser::RECEIVE_TYPE_GIVE,
            StoreCouponIssue::ISSUE_TYPE_NEW => StoreCouponUser::RECEIVE_TYPE_NEW,
            StoreCouponIssue::ISSUE_TYPE_BUY => StoreCouponUser::RECEIVE_TYPE_BUY,
            StoreCouponIssue::ISSUE_TYPE_FIRST => StoreCouponUser::RECEIVE_TYPE_FIRST,
        ];

        return $map[$issueType] ?? StoreCouponUser::RECEIVE_TYPE_SEND;
    }

    /**
     * 根据优惠券发放类型获取发放记录类型。
     */
    protected function getIssueTypeByDistributeType(int $distributeType): int
    {
        return match ($distributeType) {
            StoreCoupon::DISTRIBUTE_TYPE_FULL => StoreCouponIssue::ISSUE_TYPE_FULL,
            StoreCoupon::DISTRIBUTE_TYPE_NEW => StoreCouponIssue::ISSUE_TYPE_NEW,
            StoreCoupon::DISTRIBUTE_TYPE_FIRST => StoreCouponIssue::ISSUE_TYPE_FIRST,
            default => 0,
        };
    }

    /**
     * 计算有效期
     */
    protected function calculateValidTime(StoreCoupon $coupon): array
    {
        if ($coupon->expire_type == StoreCoupon::EXPIRE_TYPE_DAYS) {
            // 有效天数
            $startTime = date('Y-m-d H:i:s');
            $endTime = date('Y-m-d H:i:s', strtotime("+{$coupon->valid_days} days"));
        } else {
            // 固定时间段
            $startTime = $coupon->valid_start_time;
            $endTime = $coupon->valid_end_time;
        }

        return [
            'start' => $startTime,
            'end' => $endTime,
        ];
    }

    /**
     * 解析当前订单需要匹配的平台/商户优惠券范围。
     */
    protected function resolveGiftMerchantIds(int $merchantId, int $orderRole): array
    {
        $merchantIds = [];
        if ($orderRole !== Order::ROLE_CHILD) {
            $merchantIds[] = 0;
        }
        if ($merchantId > 0 && $orderRole !== Order::ROLE_MAIN) {
            $merchantIds[] = $merchantId;
        }

        return array_values(array_unique($merchantIds));
    }

    /**
     * 获取订单赠券候选配置。
     */
    protected function getOrderGiftCandidateCoupons(array $merchantIds): array
    {
        $now = date('Y-m-d H:i:s');

        return app()->make(StoreCouponService::class)
            ->getModel()
            ->whereIn('merchant_id', $merchantIds)
            ->where('status', StoreCoupon::STATUS_OPEN)
            ->whereIn('distribute_type', [
                StoreCoupon::DISTRIBUTE_TYPE_FULL,
                StoreCoupon::DISTRIBUTE_TYPE_NEW,
                StoreCoupon::DISTRIBUTE_TYPE_FIRST,
            ])
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
            ->order('sort desc, id desc')
            ->select()
            ->toArray();
    }

    /**
     * 判断优惠券是否满足当前订单赠送条件。
     */
    protected function isOrderGiftCouponMatched(array $coupon, int $userId, float $orderAmount): bool
    {
        $distributeType = (int)($coupon['distribute_type'] ?? 0);
        if ($distributeType === StoreCoupon::DISTRIBUTE_TYPE_FULL) {
            return $orderAmount >= (float)($coupon['min_consume_amount'] ?? 0);
        }

        if (in_array($distributeType, [
            StoreCoupon::DISTRIBUTE_TYPE_NEW,
            StoreCoupon::DISTRIBUTE_TYPE_FIRST,
        ], true)) {
            $merchantId = (int)($coupon['merchant_id'] ?? 0);
            return !app()->make(OrderService::class)->hasPaidBusinessOrder($userId, $merchantId);
        }

        return false;
    }

    /**
     * 是否已有同一订单同一优惠券赠送记录。
     */
    protected function hasOrderGiftIssue(int $couponId, string $orderSn, int $issueType): bool
    {
        return (int)$this->getModel()
            ->where('coupon_id', $couponId)
            ->where('batch_no', $orderSn)
            ->where('issue_type', $issueType)
            ->where('status', '<>', StoreCouponIssue::STATUS_CANCELLED)
            ->value('id') > 0;
    }

    /**
     * 将待发放记录转为用户优惠券。
     */
    protected function grantGiftIssueToUser(array $issue, array $order): int
    {
        $issueId = (int)$issue['id'];
        $couponId = (int)$issue['coupon_id'];
        $userId = (int)($order['user_id'] ?? 0);
        if ($issueId <= 0 || $couponId <= 0 || $userId <= 0) {
            return 0;
        }

        $userCouponService = app()->make(StoreCouponUserService::class);
        $exists = $userCouponService->getModel()
            ->where('issue_id', $issueId)
            ->where('user_id', $userId)
            ->find();
        if ($exists) {
            $this->update($issueId, [
                'issued_quantity' => 1,
                'status' => StoreCouponIssue::STATUS_COMPLETED,
            ]);
            return 0;
        }

        /** @var StoreCoupon|null $coupon */
        $coupon = app()->make(StoreCouponService::class)->get($couponId);
        if (!$coupon) {
            $this->update($issueId, ['status' => StoreCouponIssue::STATUS_CANCELLED]);
            return 0;
        }

        if (in_array((int)($issue['issue_type'] ?? 0), [
            StoreCouponIssue::ISSUE_TYPE_NEW,
            StoreCouponIssue::ISSUE_TYPE_FIRST,
        ], true)
            && app()->make(OrderService::class)->hasEarlierPaidBusinessOrder($userId, (int)$coupon->merchant_id, $order)
        ) {
            $this->cancelPendingGiftIssue($issue, $couponId);
            return 0;
        }

        $validTime = $this->calculateValidTime($coupon);
        $userCouponService->create([
            'coupon_id' => $couponId,
            'issue_id' => $issueId,
            'merchant_id' => (int)$coupon->merchant_id,
            'user_id' => $userId,
            'name' => $coupon->name,
            'discount_amount' => $coupon->discount_amount,
            'min_order_amount' => $coupon->min_order_amount,
            'receive_type' => $this->getReceiveTypeByIssueType((int)$issue['issue_type']),
            'valid_start_time' => $validTime['start'],
            'valid_end_time' => $validTime['end'],
            'status' => StoreCouponUser::STATUS_UNUSED,
        ]);

        $this->update($issueId, [
            'issued_quantity' => 1,
            'status' => StoreCouponIssue::STATUS_COMPLETED,
        ]);

        return 1;
    }

    /**
     * 获取订单赠券来源文案。
     */
    protected function getGiftCouponSourceText(int $issueType): string
    {
        return match ($issueType) {
            StoreCouponIssue::ISSUE_TYPE_NEW => '新人专享',
            StoreCouponIssue::ISSUE_TYPE_FIRST => '首单赠送',
            StoreCouponIssue::ISSUE_TYPE_FULL => '消费满赠',
            default => '下单赠送',
        };
    }

    /**
     * 获取订单赠券标签文案。
     */
    protected function getGiftCouponTagText(int $issueType): string
    {
        return match ($issueType) {
            StoreCouponIssue::ISSUE_TYPE_NEW => '新人券',
            StoreCouponIssue::ISSUE_TYPE_FIRST => '首单礼',
            StoreCouponIssue::ISSUE_TYPE_FULL => '满赠券',
            default => '赠送券',
        };
    }

    /**
     * 格式化赠券门槛金额。
     */
    protected function formatGiftCouponMoney(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    /**
     * 取消仍未发放的订单赠券预约，并释放下单时占用的库存。
     */
    protected function cancelPendingGiftIssue(array $issue, int $couponId): void
    {
        $releaseQuantity = max(0, (int)($issue['total_quantity'] ?? 0) - (int)($issue['issued_quantity'] ?? 0));
        if ($releaseQuantity > 0) {
            app()->make(StoreCouponService::class)->incrStock($couponId, $releaseQuantity);
        }

        $this->update((int)$issue['id'], ['status' => StoreCouponIssue::STATUS_CANCELLED]);
    }

    /**
     * 获取发放统计
     */
    public function getIssueStatistics(int $issueId): array
    {
        $issue = $this->get($issueId);
        if (!$issue) {
            throw new AdminResponseException('发放记录不存在');
        }

        $userCouponService = app()->make(StoreCouponUserService::class);

        return [
            'total_quantity' => $issue->total_quantity,
            'issued_quantity' => $issue->issued_quantity,
            'used_quantity' => $userCouponService->getModel()->where('issue_id', $issueId)->where('status', StoreCouponUser::STATUS_USED)->count(),
            'expired_quantity' => $userCouponService->getModel()->where('issue_id', $issueId)->where('status', StoreCouponUser::STATUS_EXPIRED)->count(),
            'unused_quantity' => $userCouponService->getModel()->where('issue_id', $issueId)->where('status', StoreCouponUser::STATUS_UNUSED)->count(),
        ];
    }

    /**
     * 取消发放
     */
    public function cancelIssue(int $issueId): bool
    {
        $issue = $this->get($issueId);
        if (!$issue) {
            throw new AdminResponseException('发放记录不存在');
        }

        if ($issue->status == StoreCouponIssue::STATUS_COMPLETED) {
            throw new AdminResponseException('已发放完成，无法取消');
        }

        return $this->transaction(function () use ($issueId, $issue) {
            // 回退库存
            if ($issue->issued_quantity > 0) {
                $couponService = app()->make(StoreCouponService::class);
                $couponService->incrStock($issue->coupon_id, $issue->issued_quantity);
            }

            // 更新状态
            $this->update($issueId, ['status' => StoreCouponIssue::STATUS_CANCELLED]);

            return true;
        });
    }
}
