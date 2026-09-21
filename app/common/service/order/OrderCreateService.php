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

namespace app\common\service\order;

use app\common\enum\PayTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\job\plugin\PluginEventDispatchJob;
use app\common\model\order\Order;
use app\common\model\order\OrderGoods;
use app\common\service\BaseService;
use app\common\service\merchant\MerchantService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\plugin\PluginBusinessEventService;
use app\common\service\plugin\PluginOrderQuoteService;
use app\common\service\plugin\PluginCheckoutCapabilityService;
use app\common\service\store\StoreCouponIssueService;
use app\common\service\store\StoreCouponUserService;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserDistributionService;
use app\common\service\user\UserService;
use think\facade\Log;

/**
 * 下单事务服务
 */
class OrderCreateService extends BaseService
{
    /**
     * 设置模型
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return Order::class;
    }

    /**
     * 创建订单
     *
     * @param int $userId 下单用户标识。
     * @param array $cartIds 购物车标识列表，购物车下单时传入。
     * @param bool $isNew 是否为立即购买场景。
     * @param array $options 下单选项，透传给确认单服务，例如商品、地址、优惠券、积分、备注等。
     * @return array 移动端下单接口响应，包含订单标识、订单号、支付金额和订单状态。
     * @throws AdminResponseException 商品、确认单、库存、积分或优惠券等业务校验失败时抛出。
     */
    public function createOrder(int $userId, array $cartIds = [], bool $isNew = false, array $options = []): array
    {
        // 创建订单前先复用确认单服务重算价格、优惠、积分和商品快照，避免前端提交金额被信任。
        $confirmData = app()->make(OrderConfirmService::class)->confirm($userId, $cartIds, $isNew, $options);
        $settlement = $confirmData['settlement'];
        $confirm = $confirmData['confirm'];
        $group = $settlement['groups'][0] ?? null;
        $confirmGroups = array_values($confirm['groups'] ?? []);
        $confirmAddress = $confirm['address'] ?? [];
        $splitGroups = $this->buildOrderSplitGroups($confirmGroups, $confirmAddress);

        if (!$group || empty($group['items'])) {
            throw new AdminResponseException('未找到可下单的商品');
        }

        if (count($splitGroups) > 1) {
            return $this->createSplitOrder($userId, $confirmData, $cartIds, $options);
        }

        $items = $confirm['items'] ?? [];
        if ($items === []) {
            throw new AdminResponseException('未找到可下单的商品明细');
        }
        $quoteService = app()->make(PluginOrderQuoteService::class);
        $creatingCalculation = $quoteService->calculate(
            'order.creating',
            $userId,
            $confirmGroups,
            array_merge($options, ['surface' => 'mobile'])
        );
        if (($creatingCalculation['quotes'] ?? []) !== []) {
            $confirm = $quoteService->applyToConfirm($confirm, $creatingCalculation);
            $confirmData['confirm'] = $confirm;
            $confirmGroups = array_values($confirm['groups'] ?? []);
            $splitGroups = $this->buildOrderSplitGroups($confirmGroups, $confirmAddress);
            $items = $confirm['items'] ?? [];
        }
        $commissionSnapshot = app()->make(UserDistributionService::class)->buildOrderCommissionSnapshot(
            $userId,
            $items,
            (float)($confirm['summary']['pay_amount'] ?? 0)
        );
        $items = $commissionSnapshot['items'];
        $orderCommission = $commissionSnapshot['order'];

        $user = app()->make(UserService::class)->get($userId, ['phone', 'spread_uid']);
        $userData = $user ? $user->toArray() : [];
        $selectedCartIds = $group['cart_ids'] ?? $cartIds;

        $orderTypes = $this->extractOrderTypes($items);
        $creatingPayload = $this->buildOrderCreatingPayload($userId, $cartIds, $isNew, $options, $confirmData, $splitGroups, $items, $orderTypes);
        $creatingPayload['plugin_quotes'] = $creatingCalculation['quotes'] ?? [];
        $creatingResult = app()->make(PluginBusinessEventService::class)->dispatchSync(
            'order.creating',
            $creatingPayload,
            [
                'surface' => 'mobile',
                'merchant_id' => (int) ($creatingPayload['merchant_id'] ?? 0),
            ]
        );
        if (is_array($creatingResult['payload'] ?? null)) {
            $creatingPayload = $creatingResult['payload'];
        }

        $transactionResult = $this->transaction(function () use (
            $userId,
            $items,
            $group,
            $confirm,
            $userData,
            $selectedCartIds,
            $orderCommission,
            $orderTypes,
            $creatingPayload
        ) {
            $summary = $confirm['summary'] ?? [];
            $groupConfirm = $confirm['groups'][0] ?? [];
            $coupon = $groupConfirm['coupon'] ?? ($confirm['coupon'] ?? []);
            $platformCoupon = $confirm['platform_coupon'] ?? [];
            $points = $confirm['points'] ?? [];
            $address = $confirm['address'] ?? [];
            $merchantId = (int) ($group['merchant_id'] ?? 0);
            $orderType = (int) ($items[0]['order_type'] ?? Order::ORDER_TYPE_GOODS);
            $isVirtualOrder = in_array($orderType, [Order::ORDER_TYPE_CONTENT, Order::ORDER_TYPE_SPECIAL], true)
                || app()->make(PluginCheckoutCapabilityService::class)->isVirtualGoodsItem($items[0]);
            $orderSn = get_sn('PD');
            $merchantSettlementService = app()->make(OrderMerchantSettlementService::class);
            $merchantSettlement = $merchantSettlementService->calculateSettlement(
                $merchantId,
                (float) ($groupConfirm['summary']['settlement_pay_amount'] ?? $summary['pay_amount'] ?? 0)
            );

            // 内容/专栏等虚拟订单不需要物流地址，但订单主表字段仍需写入空值保持结构完整。
            $addressPayload = $isVirtualOrder
                ? [
                    'address_id' => 0,
                    'real_name' => '',
                    'user_phone' => '',
                    'province' => '',
                    'city' => '',
                    'district' => '',
                    'detail' => '',
                    'user_address' => '',
                ]
                : [
                    'address_id' => (int) ($address['address_id'] ?? 0),
                    'real_name' => (string) ($address['real_name'] ?? ''),
                    'user_phone' => (string) ($address['user_phone'] ?? ($userData['phone'] ?? '')),
                    'province' => (string) ($address['province'] ?? ''),
                    'city' => (string) ($address['city'] ?? ''),
                    'district' => (string) ($address['district'] ?? ''),
                    'detail' => (string) ($address['detail'] ?? ''),
                    'user_address' => (string) ($address['user_address'] ?? ''),
                ];

            $order = $this->create([
                'pid' => 0,
                'order_sn' => $orderSn,
                'main_order_sn' => $orderSn,
                'order_role' => Order::ROLE_NORMAL,
                'user_id' => $userId,
                'merchant_id' => $merchantId,
                'original_amount' => (float) ($summary['original_amount'] ?? 0),
                'total_amount' => (float) ($summary['total_amount'] ?? 0),
                'discount_amount' => (float) ($summary['discount_amount'] ?? 0),
                'use_points' => (int) ($points['use_points'] ?? 0),
                'points_deduct_amount' => (float) ($summary['points_deduct_amount'] ?? 0),
                'member_discount_amount' => (float)($summary['member_discount']['discount_amount'] ?? 0),
                'member_discount_rate' => (float)($summary['member_discount']['discount_rate'] ?? 1.00),
                'postage' => (float) ($summary['postage'] ?? 0),
                'pay_amount' => (float) ($summary['pay_amount'] ?? 0),
                'order_type' => $orderType,
                'merchant_coupon_id' => $merchantId > 0 ? (int) ($coupon['selected_coupon_id'] ?? 0) : 0,
                'merchant_coupon_amount' => (float)($summary['merchant_coupon_amount'] ?? 0),
                'platform_coupon_id' => (int) ($platformCoupon['selected_coupon_id'] ?? 0),
                'platform_coupon_amount' => (float)($summary['platform_coupon_amount'] ?? 0),
                'referrer_id' => (int)($orderCommission['referrer_id'] ?? 0),
                'second_referrer_id' => (int)($orderCommission['second_referrer_id'] ?? 0),
                'first_commission_amount' => (float)($orderCommission['first_commission_amount'] ?? 0),
                'second_commission_amount' => (float)($orderCommission['second_commission_amount'] ?? 0),
                'commission_base_amount' => (float)($orderCommission['commission_base_amount'] ?? 0),
                'first_commission_rate' => (float)($orderCommission['first_commission_rate'] ?? 0),
                'second_commission_rate' => (float)($orderCommission['second_commission_rate'] ?? 0),
                'commission_level' => (int)($orderCommission['commission_level'] ?? 0),
                'commission_mode' => (string)($orderCommission['commission_mode'] ?? ''),
                'platform_commission_amount' => (float) ($merchantSettlement['commission_amount'] ?? 0),
                'merchant_income_amount' => (float) ($merchantSettlement['merchant_income_amount'] ?? 0),
                'commission_status' => Order::COMMISSION_STATUS_PENDING,
                'address_id' => (int) $addressPayload['address_id'],
                'real_name' => (string) $addressPayload['real_name'],
                'user_phone' => (string) $addressPayload['user_phone'],
                'province' => (string) $addressPayload['province'],
                'city' => (string) $addressPayload['city'],
                'district' => (string) $addressPayload['district'],
                'detail' => (string) $addressPayload['detail'],
                'user_address' => (string) $addressPayload['user_address'],
                'pay_type' => '',
                'status' => Order::STATUS_PENDING,
                'pay_expire_time' => app()->make(OrderPromotionService::class)->paymentDeadline($items),
                'refund_status' => 0,
                'refund_amount' => 0,
                'refund_reason' => '',
                'remark' => (string) ($confirm['remark'] ?? ''),
                'plugin_payload_json' => is_array($creatingPayload['plugin_payload'] ?? null) ? $creatingPayload['plugin_payload'] : [],
            ]);

            $orderId = (int) $order->id;

            // 下单副作用必须在同一事务内完成，任一环节失败都会回滚订单和关联数据。
            $this->saveOrderGoods($orderId, $items, $merchantId);
            $orderGoods = app()->make(OrderGoodsService::class)->getByOrderId($orderId);
            app()->make(PluginBusinessEventService::class)->dispatchTransactional(
                'order.creating',
                array_merge($creatingPayload, [
                    'order_id' => $orderId,
                    'order_sn' => $orderSn,
                    'order' => $order->toArray(),
                    'order_goods' => is_array($orderGoods) ? $orderGoods : [],
                    'orders' => [[
                        'order_id' => $orderId,
                        'order_sn' => $orderSn,
                        'merchant_id' => $merchantId,
                        'order_goods' => is_array($orderGoods) ? $orderGoods : [],
                    ]],
                ]),
                [
                    'surface' => 'mobile',
                    'merchant_id' => $merchantId,
                ]
            );
            $this->recordOrderGiftPoints([
                'id' => $orderId,
                'order_sn' => $orderSn,
                'user_id' => $userId,
                'order_type' => $orderType,
            ]);
            $merchantSettlementService->createOrderSettlement([
                'id' => $orderId,
                'order_sn' => $orderSn,
            ], $merchantSettlement);
            $this->decreaseStock($items);
            $this->useCoupon((int) ($coupon['selected_coupon_id'] ?? 0), $orderId);
            $this->useCoupon((int) ($platformCoupon['selected_coupon_id'] ?? 0), $orderId);
            $this->deductPoints($userId, (int) ($points['use_points'] ?? 0), $orderSn);
            $this->markCartOrdered($userId, $selectedCartIds);
            $this->recordOrderGiftCoupons([
                'id' => $orderId,
                'order_sn' => $orderSn,
                'user_id' => $userId,
                'merchant_id' => $merchantId,
                'order_role' => Order::ROLE_NORMAL,
                'pay_amount' => (float)($summary['pay_amount'] ?? 0),
            ]);

            $createdOrder = [
                'order_id' => $orderId,
                'order_sn' => $orderSn,
                'merchant_id' => $merchantId,
                'merchant_name' => (string) ($group['merchant_name'] ?? ''),
                'status' => Order::STATUS_PENDING,
                'status_text' => '待支付',
                'pay_amount' => (float) ($summary['pay_amount'] ?? 0),
                'order_types' => $orderTypes,
                'order_type_text' => count($orderTypes) > 1
                    ? '合单订单'
                    : $this->resolveOrderTypeText((int) ($orderTypes[0] ?? $orderType)),
                'confirm' => $confirm,
            ];

            $pluginOutboxIds = $this->publishOrderEvent('order.created', $createdOrder, [
                'operation' => 'create_order',
                'merchant_id' => $merchantId,
                'merchant_ids' => $merchantId > 0 ? [$merchantId] : [],
                'order_types' => $orderTypes,
                'order_type_text' => $createdOrder['order_type_text'],
                'plugin_payload' => is_array($creatingPayload['plugin_payload'] ?? null) ? $creatingPayload['plugin_payload'] : [],
            ]);

            return [
                'order' => $createdOrder,
                'plugin_outbox_ids' => $pluginOutboxIds,
            ];
        });
        $result = is_array($transactionResult['order'] ?? null)
            ? $transactionResult['order']
            : [];

        // 只有事务成功提交后才启动观察者；派发失败时保留 Outbox（事件发件箱）的待处理状态，
        // 由后台扫描器负责后续重试，不影响已经提交的订单。
        $this->dispatchDeferredPluginEvents($transactionResult['plugin_outbox_ids'] ?? []);

        app()->make(BackendMessageEventService::class)->orderCreated($result);

        // 支付金额大于 0 时返回待支付订单；0 元订单直接走免费支付完成后续支付成功流程。
        if ((float) ($result['pay_amount'] ?? 0) > 0) {
            $this->logCreateOrderDebugPayload($userId, $result);
            return $this->buildCreateOrderResponse($result);
        }

        $paidResult = app()->make(OrderService::class)->markOrderPaid(
            (int) $result['order_id'],
            PayTypeEnum::PAY_TYPE_FREE
        );

        $result = array_merge($result, $paidResult);
        $this->logCreateOrderDebugPayload($userId, $result);

        return $this->buildCreateOrderResponse($result);
    }

    /**
     * 创建主支付单与多张商户子单。
     *
     * 多商户合单采用“下单即预拆”：先创建一张主支付单承接用户支付，再创建多张待支付子单。
     * 支付成功后由主单驱动子单完成权益、结算和通知；子单才是后续发货、退款、评价的业务订单。
     *
     * @param int $userId 下单用户标识。
     * @param array $confirmData 确认单完整数据。
     * @param array $cartIds 前端提交的购物车标识。
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @return array 已创建主支付单及业务子单的轻量下单响应。
     */
    protected function createSplitOrder(int $userId, array $confirmData, array $cartIds = [], array $options = []): array
    {
        $confirm = $confirmData['confirm'] ?? [];
        $confirmAddress = $confirm['address'] ?? [];
        $groups = $this->buildOrderSplitGroups(array_values($confirm['groups'] ?? []), $confirmAddress);
        if ($groups === []) {
            throw new AdminResponseException('未找到可拆分的商户订单');
        }

        $allItems = $this->flattenGroupItems($groups);
        if ($allItems === []) {
            throw new AdminResponseException('未找到可下单的商品明细');
        }
        $orderTypes = $this->extractOrderTypes($allItems);

        $quoteService = app()->make(PluginOrderQuoteService::class);
        $creatingCalculation = $quoteService->calculate(
            'order.creating',
            $userId,
            $groups,
            array_merge($options, ['surface' => 'mobile'])
        );
        if (($creatingCalculation['quotes'] ?? []) !== []) {
            $creatingConfirm = $quoteService->applyToConfirm([
                'groups' => $groups,
                'items' => $allItems,
                'summary' => $confirm['summary'] ?? [],
            ], $creatingCalculation);
            $groups = $creatingConfirm['groups'] ?? $groups;
            // 创建阶段的报价是最终权威结果，后续拆单、佣金和落库全部使用这份快照。
            $confirm['groups'] = $groups;
            $confirm['items'] = $groups[0]['items'] ?? $allItems;
            $confirm['summary'] = $creatingConfirm['summary'] ?? ($confirm['summary'] ?? []);
            $confirm['plugin_quotes'] = $creatingCalculation['quotes'];
            $allItems = $this->flattenGroupItems($groups);
            $orderTypes = $this->extractOrderTypes($allItems);
        }

        $distributionService = app()->make(UserDistributionService::class);
        foreach ($groups as $index => $group) {
            $groupItems = array_values($group['items'] ?? []);
            $groupSnapshot = $distributionService->buildOrderCommissionSnapshot(
                $userId,
                $groupItems,
                (float)($group['summary']['pay_amount'] ?? 0)
            );
            $groups[$index]['items'] = $groupSnapshot['items'];
            $groups[$index]['commission_snapshot'] = $groupSnapshot['order'];
        }
        $allItems = $this->flattenGroupItems($groups);
        $creatingPayload = $this->buildOrderCreatingPayload($userId, $cartIds, false, $options, $confirmData, $groups, $allItems, $orderTypes);
        $creatingPayload['plugin_quotes'] = $creatingCalculation['quotes'] ?? [];
        $creatingResult = app()->make(PluginBusinessEventService::class)->dispatchSync(
            'order.creating',
            $creatingPayload,
            [
                'surface' => 'mobile',
                'merchant_id' => (int) ($creatingPayload['merchant_id'] ?? 0),
            ]
        );
        if (is_array($creatingResult['payload'] ?? null)) {
            $creatingPayload = $creatingResult['payload'];
        }

        $user = app()->make(UserService::class)->get($userId, ['phone', 'spread_uid']);
        $userData = $user ? $user->toArray() : [];
        $summary = $confirm['summary'] ?? [];
        $points = $confirm['points'] ?? [];
        $platformCoupon = $confirm['platform_coupon'] ?? [];
        $address = $confirm['address'] ?? [];
        $groups = $this->allocateGroupUsePoints($groups, $points);
        $orderType = (int)($orderTypes[0] ?? Order::ORDER_TYPE_GOODS);
        $isVirtualOrder = !$this->hasPhysicalOrderItems($allItems);
        $selectedCartIds = $this->collectGroupCartIds($groups, $cartIds);

        $transactionResult = $this->transaction(function () use (
            $userId,
            $confirm,
            $groups,
            $allItems,
            $summary,
            $points,
            $platformCoupon,
            $address,
            $orderType,
            $orderTypes,
            $isVirtualOrder,
            $userData,
            $selectedCartIds,
            $creatingPayload
        ) {
            $mainOrderSn = get_sn('PD');
            $addressPayload = $this->buildOrderAddressPayload($address, $userData, $isVirtualOrder);

            /*
             * 主单是聚合支付入口，不挂具体商户，不保存订单商品明细。
             * 它记录整单实付、平台券和积分，用于待支付列表、支付网关回调和主流水归集。
             */
            $mainOrder = $this->create([
                'pid' => 0,
                'order_sn' => $mainOrderSn,
                'main_order_sn' => $mainOrderSn,
                'order_role' => Order::ROLE_MAIN,
                'user_id' => $userId,
                'merchant_id' => 0,
                'original_amount' => (float)($summary['original_amount'] ?? 0),
                'total_amount' => (float)($summary['total_amount'] ?? 0),
                'discount_amount' => (float)($summary['discount_amount'] ?? 0),
                'use_points' => (int)($points['use_points'] ?? 0),
                'points_deduct_amount' => (float)($summary['points_deduct_amount'] ?? 0),
                'member_discount_amount' => (float)($summary['member_discount']['discount_amount'] ?? 0),
                'member_discount_rate' => (float)($summary['member_discount']['discount_rate'] ?? 1.00),
                'postage' => (float)($summary['postage'] ?? 0),
                'pay_amount' => (float)($summary['pay_amount'] ?? 0),
                'order_type' => $orderType,
                'merchant_coupon_id' => 0,
                'merchant_coupon_amount' => (float)($summary['merchant_coupon_amount'] ?? 0),
                'platform_coupon_id' => (int)($platformCoupon['selected_coupon_id'] ?? 0),
                'platform_coupon_amount' => (float)($summary['platform_coupon_amount'] ?? 0),
                'referrer_id' => 0,
                'second_referrer_id' => 0,
                'first_commission_amount' => 0,
                'second_commission_amount' => 0,
                'commission_base_amount' => 0,
                'first_commission_rate' => 0,
                'second_commission_rate' => 0,
                'commission_level' => 0,
                'commission_mode' => '',
                'platform_commission_amount' => 0,
                'merchant_income_amount' => 0,
                'commission_status' => Order::COMMISSION_STATUS_PENDING,
                'address_id' => (int)$addressPayload['address_id'],
                'real_name' => (string)$addressPayload['real_name'],
                'user_phone' => (string)$addressPayload['user_phone'],
                'province' => (string)$addressPayload['province'],
                'city' => (string)$addressPayload['city'],
                'district' => (string)$addressPayload['district'],
                'detail' => (string)$addressPayload['detail'],
                'user_address' => (string)$addressPayload['user_address'],
                'pay_type' => '',
                'status' => Order::STATUS_PENDING,
                'pay_expire_time' => app()->make(OrderPromotionService::class)->paymentDeadline($allItems),
                'refund_status' => 0,
                'refund_amount' => 0,
                'refund_reason' => '',
                'remark' => (string)($confirm['remark'] ?? ''),
                'plugin_payload_json' => is_array($creatingPayload['plugin_payload'] ?? null) ? $creatingPayload['plugin_payload'] : [],
            ]);

            $mainOrderId = (int)$mainOrder->id;
            $children = [];
            $hotelOrderEvidence = [
                'order_id' => 0,
                'order_sn' => '',
                'order_goods' => [],
            ];
            $platformCommissionAmount = 0.0;
            $merchantIncomeAmount = 0.0;
            $merchantCouponUsed = [];
            $merchantSettlementService = app()->make(OrderMerchantSettlementService::class);
            $merchantIds = array_values(array_unique(array_filter(array_map(
                static fn(array $group): int => (int)($group['merchant_id'] ?? 0),
                $groups
            ))));
            $commissionRateMap = app()->make(MerchantService::class)->getCommissionRateMapByIds($merchantIds);

            /*
             * 子单按商户分组创建，状态仍是待支付。这样支付回调只需要更新主单并逐个激活子单，
             * 不需要在回调阶段重新拆单，后续后台拆单发货也可以继续基于子单和 order_goods_id 扩展。
             */
            foreach ($groups as $group) {
                $childItems = array_values($group['items'] ?? []);
                if ($childItems === []) {
                    continue;
                }

                $childSummary = $group['summary'] ?? [];
                $childCommission = $group['commission_snapshot'] ?? [];
                $merchantId = (int)($group['merchant_id'] ?? 0);
                $childOrderType = (int)($group['order_type'] ?? Order::ORDER_TYPE_GOODS);
                $childIsVirtualOrder = !$this->hasPhysicalOrderItems($childItems);
                $childOrderSn = get_sn('PD');
                $childAddressPayload = $this->buildOrderAddressPayload($address, $userData, $childIsVirtualOrder);
                $merchantCouponId = 0;
                if ($merchantId > 0 && !isset($merchantCouponUsed[$merchantId])) {
                    $merchantCouponUsed[$merchantId] = true;
                    $merchantCouponId = (int)($group['coupon']['selected_coupon_id'] ?? 0);
                }
                // 平台券由平台承担，商户结算基准要加回平台券分摊额。
                $merchantSettlement = $merchantSettlementService->calculateSettlement(
                    $merchantId,
                    (float)($childSummary['settlement_pay_amount'] ?? $childSummary['pay_amount'] ?? 0),
                    (float)($commissionRateMap[$merchantId] ?? 0)
                );
                $platformCommissionAmount = (float)bcadd(
                    (string)$platformCommissionAmount,
                    (string)($merchantSettlement['commission_amount'] ?? 0),
                    2
                );
                $merchantIncomeAmount = (float)bcadd(
                    (string)$merchantIncomeAmount,
                    (string)($merchantSettlement['merchant_income_amount'] ?? 0),
                    2
                );

                $childOrder = $this->create([
                    'pid' => $mainOrderId,
                    'order_sn' => $childOrderSn,
                    'main_order_sn' => $mainOrderSn,
                    'order_role' => Order::ROLE_CHILD,
                    'user_id' => $userId,
                    'merchant_id' => $merchantId,
                    'original_amount' => (float)($childSummary['original_amount'] ?? 0),
                    'total_amount' => (float)($childSummary['total_amount'] ?? 0),
                    'discount_amount' => (float)($childSummary['discount_amount'] ?? 0),
                    'use_points' => (int)($group['use_points'] ?? 0),
                    'points_deduct_amount' => (float)($childSummary['points_deduct_amount'] ?? 0),
                    'member_discount_amount' => (float)($childSummary['member_discount_amount'] ?? 0),
                    'member_discount_rate' => (float)($group['member_discount']['discount_rate'] ?? 1.00),
                    'postage' => (float)($childSummary['postage'] ?? 0),
                    'pay_amount' => (float)($childSummary['pay_amount'] ?? 0),
                    'order_type' => $childOrderType,
                    'merchant_coupon_id' => $merchantCouponId,
                    'merchant_coupon_amount' => (float)($childSummary['merchant_coupon_amount'] ?? 0),
                    'platform_coupon_id' => 0,
                    'platform_coupon_amount' => (float)($childSummary['platform_coupon_amount'] ?? 0),
                    'referrer_id' => (int)($childCommission['referrer_id'] ?? 0),
                    'second_referrer_id' => (int)($childCommission['second_referrer_id'] ?? 0),
                    'first_commission_amount' => (float)($childCommission['first_commission_amount'] ?? 0),
                    'second_commission_amount' => (float)($childCommission['second_commission_amount'] ?? 0),
                    'commission_base_amount' => (float)($childCommission['commission_base_amount'] ?? 0),
                    'first_commission_rate' => (float)($childCommission['first_commission_rate'] ?? 0),
                    'second_commission_rate' => (float)($childCommission['second_commission_rate'] ?? 0),
                    'commission_level' => (int)($childCommission['commission_level'] ?? 0),
                    'commission_mode' => (string)($childCommission['commission_mode'] ?? ''),
                    'platform_commission_amount' => (float)($merchantSettlement['commission_amount'] ?? 0),
                    'merchant_income_amount' => (float)($merchantSettlement['merchant_income_amount'] ?? 0),
                    'commission_status' => Order::COMMISSION_STATUS_PENDING,
                    'address_id' => (int)$childAddressPayload['address_id'],
                    'real_name' => (string)$childAddressPayload['real_name'],
                    'user_phone' => (string)$childAddressPayload['user_phone'],
                    'province' => (string)$childAddressPayload['province'],
                    'city' => (string)$childAddressPayload['city'],
                    'district' => (string)$childAddressPayload['district'],
                    'detail' => (string)$childAddressPayload['detail'],
                    'user_address' => (string)$childAddressPayload['user_address'],
                    'pay_type' => '',
                    'status' => Order::STATUS_PENDING,
                    'pay_expire_time' => app()->make(OrderPromotionService::class)->paymentDeadline($childItems),
                    'refund_status' => 0,
                    'refund_amount' => 0,
                    'refund_reason' => '',
                    'remark' => (string)($group['remark'] ?? ''),
                    'plugin_payload_json' => is_array($creatingPayload['plugin_payload'] ?? null) ? $creatingPayload['plugin_payload'] : [],
                ]);

                $childOrderId = (int)$childOrder->id;
                // 商品行保存所有优惠分摊快照，退款时可按 order_goods_id 精确冲正商户入账和平台补贴。
                $this->saveOrderGoods($childOrderId, $childItems, $merchantId);
                $childOrderGoods = app()->make(OrderGoodsService::class)->getByOrderId($childOrderId);

                $hotelItems = array_values(array_filter(
                    $childItems,
                    static fn($item): bool => is_array($item)
                        && strtolower(trim((string)($item['plugin_code'] ?? ''))) === 'hotel'
                ));
                if ($hotelItems !== []) {
                    if ($hotelOrderEvidence['order_id'] > 0 || count($hotelItems) !== 1) {
                        throw new AdminResponseException('一笔酒店订单只能对应一个房型子订单');
                    }

                    $hotelCartId = (int)($hotelItems[0]['cart_id'] ?? $hotelItems[0]['id'] ?? 0);
                    $hotelGoods = array_values(array_filter(
                        $childOrderGoods,
                        static function ($goods) use ($hotelCartId): bool {
                            if (!is_array($goods)) {
                                return false;
                            }
                            $goodsInfo = is_array($goods['goods_info'] ?? null) ? $goods['goods_info'] : [];
                            return $hotelCartId > 0 && (int)($goodsInfo['cart_id'] ?? 0) === $hotelCartId;
                        }
                    ));
                    if (count($hotelGoods) !== 1) {
                        throw new AdminResponseException('酒店订单商品证据缺失');
                    }
                    // 酒店生命周期必须绑定真实业务子单，不能绑定只负责聚合支付的主单。
                    $hotelOrderEvidence = [
                        'order_id' => $childOrderId,
                        'order_sn' => $childOrderSn,
                        'order_goods' => $hotelGoods,
                    ];
                }
                $this->recordOrderGiftPoints([
                    'id' => $childOrderId,
                    'order_sn' => $childOrderSn,
                    'user_id' => $userId,
                    'order_type' => $childOrderType,
                ]);
                $merchantSettlementService->createOrderSettlement([
                    'id' => $childOrderId,
                    'order_sn' => $childOrderSn,
                ], $merchantSettlement);
                $this->useCoupon($merchantCouponId, $childOrderId);
                $this->recordOrderGiftCoupons([
                    'id' => $childOrderId,
                    'order_sn' => $childOrderSn,
                    'user_id' => $userId,
                    'merchant_id' => $merchantId,
                    'order_role' => Order::ROLE_CHILD,
                    'pay_amount' => (float)($childSummary['pay_amount'] ?? 0),
                ]);

                $children[] = [
                    'order_id' => $childOrderId,
                    'order_sn' => $childOrderSn,
                    'merchant_id' => $merchantId,
                    'merchant_name' => (string)($group['merchant_name'] ?? ''),
                    'order_type' => $childOrderType,
                    'order_type_text' => $this->resolveOrderTypeText($childOrderType),
                    'status' => Order::STATUS_PENDING,
                    'status_text' => '待支付',
                    'pay_amount' => (float)($childSummary['pay_amount'] ?? 0),
                    'plugin_payload' => is_array($creatingPayload['plugin_payload'] ?? null) ? $creatingPayload['plugin_payload'] : [],
                    'order_goods' => is_array($childOrderGoods) ? $childOrderGoods : [],
                ];
            }

            /*
             * 主子单都创建成功后，再统一占用库存、优惠券、积分和购物车状态。
             * 这些操作仍在同一个事务内，任一失败都会回滚主单和已创建的子单，避免半拆单。
             */
            $transactionPayload = array_merge($creatingPayload, [
                'order_id' => $mainOrderId,
                'order_sn' => $mainOrderSn,
                'order' => $mainOrder->toArray(),
                'orders' => $children,
            ]);
            if ($hotelOrderEvidence['order_goods'] !== []) {
                // 事务处理器仍接收主单上下文；酒店专用 ID/商品证据指向实际可履约的子单。
                $transactionPayload['hotel_order_id'] = $hotelOrderEvidence['order_id'];
                $transactionPayload['order_goods'] = $hotelOrderEvidence['order_goods'];
            }
            app()->make(PluginBusinessEventService::class)->dispatchTransactional(
                'order.creating',
                $transactionPayload,
                [
                    'surface' => 'mobile',
                    'merchant_id' => 0,
                ]
            );
            $this->update($mainOrderId, [
                'platform_commission_amount' => $platformCommissionAmount,
                'merchant_income_amount' => $merchantIncomeAmount,
            ]);
            $this->decreaseStock($allItems);
            $this->useCoupon((int)($platformCoupon['selected_coupon_id'] ?? 0), $mainOrderId);
            $this->deductPoints($userId, (int)($points['use_points'] ?? 0), $mainOrderSn);
            $this->markCartOrdered($userId, $selectedCartIds);
            $this->recordOrderGiftCoupons([
                'id' => $mainOrderId,
                'order_sn' => $mainOrderSn,
                'user_id' => $userId,
                'merchant_id' => 0,
                'order_role' => Order::ROLE_MAIN,
                'pay_amount' => (float)($summary['pay_amount'] ?? 0),
            ]);

            $createdOrder = [
                'order_id' => $mainOrderId,
                'order_sn' => $mainOrderSn,
                'merchant_id' => 0,
                'merchant_name' => '合并支付订单',
                'status' => Order::STATUS_PENDING,
                'status_text' => '待支付',
                'pay_amount' => (float)($summary['pay_amount'] ?? 0),
                'order_types' => $orderTypes,
                'order_type_text' => count($orderTypes) > 1
                    ? '合单订单'
                    : $this->resolveOrderTypeText($orderType),
                'children' => $children,
                'confirm' => $confirm,
            ];

            $pluginOutboxIds = $this->publishOrderEvent('order.created', $createdOrder, [
                'operation' => 'create_split_order',
                'merchant_id' => 0,
                'merchant_ids' => array_values(array_unique(array_filter(array_map(
                    static fn(array $group): int => (int) ($group['merchant_id'] ?? 0),
                    $groups
                )))),
                'order_types' => $orderTypes,
                'order_type_text' => $createdOrder['order_type_text'],
                'children' => $children,
                'plugin_payload' => is_array($creatingPayload['plugin_payload'] ?? null) ? $creatingPayload['plugin_payload'] : [],
            ]);

            return [
                'order' => $createdOrder,
                'plugin_outbox_ids' => $pluginOutboxIds,
            ];
        });
        $result = is_array($transactionResult['order'] ?? null)
            ? $transactionResult['order']
            : [];

        $this->dispatchDeferredPluginEvents($transactionResult['plugin_outbox_ids'] ?? []);

        app()->make(BackendMessageEventService::class)->orderCreated($result);

        if ((float)($result['pay_amount'] ?? 0) > 0) {
            $this->logCreateOrderDebugPayload($userId, $result);
            return $this->buildCreateOrderResponse($result);
        }

        // 0 元合单也统一走支付成功入口，确保主单聚合流水、子单结算和权益发放流程一致。
        $paidResult = app()->make(OrderService::class)->markOrderPaid(
            (int)$result['order_id'],
            PayTypeEnum::PAY_TYPE_FREE
        );

        $result = array_merge($result, $paidResult);
        $this->logCreateOrderDebugPayload($userId, $result);

        return $this->buildCreateOrderResponse($result);
    }

    /**
     * 将商户确认组按订单类型继续拆分。
     *
     * @param array $groups 当前确认单的商户或业务子单分组。
     * @param array $address 收货地址资料及运费计算所需地区信息。
     * @return array 按商户和订单类型稳定排序的业务子单分组。
     */
    protected function buildOrderSplitGroups(array $groups, array $address = []): array
    {
        $splitGroups = [];
        $assignedPluginFees = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $items = array_values(array_filter($group['items'] ?? [], static fn($item): bool => is_array($item)));
            if ($items === []) {
                continue;
            }

            $merchantId = max(0, (int)($group['merchant_id'] ?? 0));
            $merchantName = (string)($group['merchant_name'] ?? '');
            $merchantType = (string)($group['merchant_type'] ?? ($merchantId > 0 ? 'merchant' : 'platform'));
            $coupon = is_array($group['coupon'] ?? null) ? $group['coupon'] : [];
            $memberDiscount = is_array($group['member_discount'] ?? null) ? $group['member_discount'] : [];
            $remark = (string)($group['remark'] ?? '');
            $buckets = [];

            foreach ($items as $item) {
                $orderType = (int)($item['order_type'] ?? Order::ORDER_TYPE_GOODS);
                $bucketKey = $merchantId . ':' . $orderType;

                if (!isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey] = [
                        'merchant_id' => $merchantId,
                        'merchant_name' => $merchantName,
                        'merchant_type' => $merchantType,
                        'order_type' => $orderType,
                        'items' => [],
                        'cart_ids' => [],
                        'coupon' => $coupon,
                        'remark' => $remark,
                        'member_discount' => $memberDiscount,
                    ];
                }

                $buckets[$bucketKey]['items'][] = $item;
                $cartId = (int)($item['cart_id'] ?? $item['id'] ?? 0);
                if ($cartId > 0) {
                    $buckets[$bucketKey]['cart_ids'][] = $cartId;
                }
            }

            foreach ($buckets as $bucket) {
                $bucket['cart_ids'] = array_values(array_unique(array_filter(array_map('intval', $bucket['cart_ids']))));
                $bucket['items'] = array_values($bucket['items']);
                $bucket['summary'] = $this->buildSplitGroupSummary($bucket['items'], $address, $assignedPluginFees);
                $bucket['member_discount']['discount_amount'] = (float)($bucket['summary']['member_discount_amount'] ?? 0);
                $splitGroups[] = $bucket;
            }
        }

        usort($splitGroups, static function (array $left, array $right): int {
            $merchantCompare = (int)($left['merchant_id'] ?? 0) <=> (int)($right['merchant_id'] ?? 0);
            if ($merchantCompare !== 0) {
                return $merchantCompare;
            }

            return (int)($left['order_type'] ?? 0) <=> (int)($right['order_type'] ?? 0);
        });

        return $splitGroups;
    }

    /**
     * 重新汇总拆分后的子单金额（含运费）。
     *
     * 拆分子单时按组内实物商品重新计算运费（虚拟订单无运费），
     * 避免多商户合单拆分后子单邮费归 0。
     *
     * @param array $items 组内商品行
     * @param array $address 收货地址（省/市/区名称）
     * @param array $assignedPluginFees 已分配的插件费用记录，防止拆单重复计费。
     * @return array 子单汇总
     */
    protected function buildSplitGroupSummary(array $items, array $address = [], array &$assignedPluginFees = []): array
    {
        $originalAmount = 0.0;
        $goodsAmount = 0.0;
        $memberGoodsAmount = 0.0;
        $memberDiscountAmount = 0.0;
        $merchantCouponAmount = 0.0;
        $platformCouponAmount = 0.0;
        $pointsDeductAmount = 0.0;
        $payAmount = 0.0;
        $pluginPostage = 0.0;
        $pluginExtraAmount = 0.0;

        foreach ($items as $item) {
            $cartNum = max(1, (int)($item['cart_num'] ?? 1));
            $lineTotal = $this->normalizeMoney((float)($item['line_total'] ?? 0));
            $originalPrice = $this->normalizeMoney((float)($item['original_price'] ?? ($item['price'] ?? 0)));
            $memberLineTotal = array_key_exists('member_line_total', $item)
                ? $this->normalizeMoney((float)($item['member_line_total'] ?? 0))
                : $this->moneyMax(0, $this->moneySub($lineTotal, (float)($item['member_discount_amount'] ?? 0)));

            $originalAmount = $this->moneyAdd($originalAmount, $this->moneyMul($originalPrice, $cartNum));
            $goodsAmount = $this->moneyAdd($goodsAmount, $lineTotal);
            $memberGoodsAmount = $this->moneyAdd($memberGoodsAmount, $memberLineTotal);
            $memberDiscountAmount = $this->moneyAdd($memberDiscountAmount, (float)($item['member_discount_amount'] ?? 0));
            $merchantCouponAmount = $this->moneyAdd($merchantCouponAmount, (float)($item['merchant_coupon_amount'] ?? 0));
            $platformCouponAmount = $this->moneyAdd($platformCouponAmount, (float)($item['platform_coupon_amount'] ?? 0));
            $pointsDeductAmount = $this->moneyAdd($pointsDeductAmount, (float)($item['points_deduct_amount'] ?? 0));
            $payAmount = $this->moneyAdd($payAmount, (float)($item['pay_amount'] ?? 0));
            $pluginCode = strtolower(trim((string)($item['plugin_code'] ?? '')));
            $pluginSummary = is_array($item['plugin_summary'] ?? null) ? $item['plugin_summary'] : [];
            // plugin_summary 是插件维度快照，同一插件可能出现在多个商户子组，
            // 费用只归集一次；商品行本身的价格/优惠仍按每行累计。
            if ($pluginCode !== '' && !isset($assignedPluginFees[$pluginCode])) {
                $assignedPluginFees[$pluginCode] = true;
                $pluginPostage = $this->moneyAdd($pluginPostage, (float)($pluginSummary['postage'] ?? 0));
                $pluginExtraAmount = $this->moneyAdd(
                    $pluginExtraAmount,
                    (float)($pluginSummary['extra_amount'] ?? $pluginSummary['fee_amount'] ?? 0)
                );
            }
        }

        $hostItems = array_values(array_filter(
            $items,
            static fn(array $item): bool => trim((string)($item['plugin_code'] ?? '')) === ''
                || trim((string) ($item['promotion_code'] ?? '')) !== ''
        ));
        $postage = $this->moneyAdd(
            $this->resolveSplitGroupPostage($hostItems, $address),
            $this->moneyAdd($pluginPostage, $pluginExtraAmount)
        );
        $totalAmount = $this->moneyAdd($goodsAmount, $postage);
        $discountAmount = $this->moneyAdd(
            $memberDiscountAmount,
            $this->moneyAdd($merchantCouponAmount, $this->moneyAdd($platformCouponAmount, $pointsDeductAmount))
        );

        return [
            'original_amount' => $this->normalizeMoney($originalAmount),
            'goods_amount' => $this->normalizeMoney($goodsAmount),
            'member_goods_amount' => $this->normalizeMoney($memberGoodsAmount),
            'total_amount' => $this->normalizeMoney($totalAmount),
            'discount_amount' => $this->normalizeMoney($discountAmount),
            'member_discount_amount' => $this->normalizeMoney($memberDiscountAmount),
            'merchant_coupon_amount' => $this->normalizeMoney($merchantCouponAmount),
            'platform_coupon_amount' => $this->normalizeMoney($platformCouponAmount),
            'points_deduct_amount' => $this->normalizeMoney($pointsDeductAmount),
            'postage' => $this->normalizeMoney($postage),
            'pay_amount' => $this->normalizeMoney($this->moneyAdd($payAmount, $postage)),
            'settlement_pay_amount' => $this->normalizeMoney(
                $this->moneyAdd(
                    $this->moneyAdd($payAmount, $postage),
                    $platformCouponAmount
                )
            ),
        ];
    }

    /**
     * 计算拆分子单运费（虚拟订单为 0；含实物无地址抛"请选择收货地址"）
     *
     * @param array $items 组内商品行
     * @param array $address 收货地址
     * @return float 运费
     */
    protected function resolveSplitGroupPostage(array $items, array $address): float
    {
        $hasPhysical = false;
        foreach ($items as $item) {
            if ((int) ($item['order_type'] ?? Order::ORDER_TYPE_GOODS) === Order::ORDER_TYPE_GOODS
                && !app()->make(PluginCheckoutCapabilityService::class)->isVirtualGoodsItem($item)) {
                $hasPhysical = true;
                break;
            }
        }
        if (!$hasPhysical) {
            return 0.0;
        }

        $result = app()->make(\app\common\service\store\FreightTemplateService::class)
            ->calcGroupPostage($items, $address);

        return (float) ($result['postage'] ?? 0.0);
    }

    /**
     * 按积分抵扣金额分配子单使用积分数量。
     *
     * @param array $groups 当前确认单的商户或业务子单分组。
     * @param array $points 整单实际使用的积分数量或抵扣配置。
     * @return array 已追加各子单实际使用积分数量的分组数据。
     */
    protected function allocateGroupUsePoints(array $groups, array $points): array
    {
        $usePoints = max(0, (int)($points['use_points'] ?? 0));
        if ($groups === []) {
            return [];
        }

        if ($usePoints <= 0) {
            foreach ($groups as $index => $group) {
                $groups[$index]['use_points'] = 0;
            }

            return $groups;
        }

        $bases = [];
        foreach ($groups as $index => $group) {
            $bases[$index] = (float)($group['summary']['points_deduct_amount'] ?? $group['summary']['pay_amount'] ?? 0);
        }

        $shares = $this->allocateIntegerShares($bases, $usePoints);
        foreach ($groups as $index => $group) {
            $groups[$index]['use_points'] = (int)($shares[$index] ?? 0);
        }

        return $groups;
    }

    /**
     * 将金额权重分配为整数份额，最后一份吸收余数。
     *
     * @param array $bases 各分摊对象的金额权重，保留原键顺序。
     * @param int $total 待分配的整数总份额。
     * @return array 保留权重键且合计等于总份额的整数分配结果。
     */
    protected function allocateIntegerShares(array $bases, int $total): array
    {
        $keys = array_keys($bases);
        $count = count($keys);
        if ($count === 0) {
            return [];
        }

        if ($total <= 0) {
            return array_fill_keys($keys, 0);
        }

        $normalizedBases = [];
        $baseTotal = 0.0;
        foreach ($bases as $key => $base) {
            $normalizedBases[$key] = max(0.0, (float)$base);
            $baseTotal += $normalizedBases[$key];
        }

        if ($baseTotal <= 0) {
            foreach ($normalizedBases as $key => $value) {
                $normalizedBases[$key] = 1.0;
            }
            $baseTotal = (float)$count;
        }

        $shares = [];
        $allocated = 0;
        $lastKey = $keys[$count - 1];
        foreach ($keys as $index => $key) {
            if ($key === $lastKey) {
                $shares[$key] = max(0, $total - $allocated);
                continue;
            }

            $share = (int)floor($total * ($normalizedBases[$key] / $baseTotal));
            $shares[$key] = max(0, $share);
            $allocated += $shares[$key];
        }

        return $shares;
    }

    /**
     * 提取商品行中的订单类型列表。
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @return array 当前商品行中去重后的有效订单类型列表。
     */
    protected function extractOrderTypes(array $items): array
    {
        $orderTypes = [];
        foreach ($items as $item) {
            $orderType = (int)($item['order_type'] ?? Order::ORDER_TYPE_GOODS);
            $orderTypes[$orderType] = $orderType;
        }

        return array_values(array_unique(array_map('intval', array_values($orderTypes))));
    }

    /**
     * 判断商品行是否包含实物商品。
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @return bool 商品行是否包含需要真实库存及配送的实物商品。
     */
    protected function hasPhysicalOrderItems(array $items): bool
    {
        foreach ($items as $item) {
            if ((int)($item['order_type'] ?? Order::ORDER_TYPE_GOODS) === Order::ORDER_TYPE_GOODS) {
                return true;
            }
        }

        return false;
    }

    /**
     * 构建移动端下单接口轻量响应。
     *
     * @param array $result 下单结果。
     * @return array 移动端继续支付所需的订单编号、单号及当前状态。
     */
    protected function buildCreateOrderResponse(array $result): array
    {
        $response = [
            'order_id' => (int) ($result['order_id'] ?? 0),
            'order_sn' => (string) ($result['order_sn'] ?? ''),
            'pay_amount' => (float) ($result['pay_amount'] ?? 0),
            'status' => (int) ($result['status'] ?? Order::STATUS_PENDING),
            'status_text' => (string) ($result['status_text'] ?? ''),
        ];

        if (isset($result['order_types']) && is_array($result['order_types'])) {
            $response['order_types'] = array_values(array_unique(array_map('intval', $result['order_types'])));
            $response['order_type_text'] = (string) ($result['order_type_text'] ?? '');
        }

        if (isset($result['children']) && is_array($result['children'])) {
            $response['children'] = $result['children'];
        }

        return $response;
    }

    /**
     * 临时记录下单完整结果，确认联调稳定后可删除。
     *
     * @param int $userId 用户标识。
     * @param array $result 完整下单结果。
     * @return void 无返回值；仅记录用于联调核对的下单结果。
     */
    protected function logCreateOrderDebugPayload(int $userId, array $result): void
    {
        Log::debug('[mobile-order-create] debug payload, remove after checkout integration is stable', [
            'user_id' => $userId,
            'order_id' => (int) ($result['order_id'] ?? 0),
            'order_sn' => (string) ($result['order_sn'] ?? ''),
            'payload' => $result,
        ]);
    }

    /**
     * 组装订单创建同步事件载荷。
     *
     * 只保留插件决策所需的确认单快照、商品行和扩展命名空间，避免把
     * 控制器原始请求体完整透传到事件总线。
     *
     * @param int $userId 用户标识。
     * @param array $cartIds 购物车标识列表。
     * @param bool $isNew 是否立即购买。
     * @param array $options 下单选项。
     * @param array $confirmData 确认单结果。
     * @param array $groups 拆分后的商户分组。
     * @param array $items 合并后的商品明细。
     * @param array $orderTypes 订单类型列表。
     * @return array<string, mixed> 用于同步插件决策的可信确认快照、商品行和命名空间载荷。
     */
    private function buildOrderCreatingPayload(
        int $userId,
        array $cartIds,
        bool $isNew,
        array $options,
        array $confirmData,
        array $groups,
        array $items,
        array $orderTypes
    ): array {
        $confirm = is_array($confirmData['confirm'] ?? null) ? $confirmData['confirm'] : [];
        $summary = is_array($confirm['summary'] ?? null) ? $confirm['summary'] : [];
        $address = is_array($confirm['address'] ?? null) ? $confirm['address'] : [];
        $pluginPayload = is_array($options['plugin_payload'] ?? null) ? $options['plugin_payload'] : [];
        if ($pluginPayload === []) {
            foreach ($items as $item) {
                $itemPayload = is_array($item['plugin_payload'] ?? null) ? $item['plugin_payload'] : [];
                foreach ($itemPayload as $code => $value) {
                    if (is_array($value)) {
                        $pluginPayload[strtolower(trim((string)$code))] = $value;
                    }
                }
            }
        }
        $requestOptions = $options;
        unset($requestOptions['plugin_payload']);

        $merchantIds = [];
        foreach ($groups as $group) {
            $merchantId = (int) ($group['merchant_id'] ?? 0);
            if ($merchantId > 0) {
                $merchantIds[$merchantId] = $merchantId;
            }
        }
        $merchantIds = array_values($merchantIds);
        $merchantId = count($merchantIds) === 1 ? (int) $merchantIds[0] : 0;

        return [
            'scene' => 'create_order',
            'user_id' => $userId,
            'cart_ids' => array_values(array_unique(array_filter(array_map('intval', $cartIds)))),
            'is_new' => $isNew ? 1 : 0,
            'merchant_id' => $merchantId,
            'merchant_ids' => $merchantIds,
            'order_type' => (int) ($orderTypes[0] ?? Order::ORDER_TYPE_GOODS),
            'order_types' => array_values(array_unique(array_filter(array_map('intval', $orderTypes), static fn(int $id): bool => $id > 0))),
            'groups' => $groups,
            'items' => $items,
            'summary' => $summary,
            'address' => $address,
            'options' => $requestOptions,
            'plugin_payload' => $pluginPayload,
        ];
    }

    /**
     * 发布订单插件事件，只携带最小必要信息。
     *
     * @param array|object|int $order 订单模型、数组或订单标识。
     * @param array<string, mixed> $context 事件上下文。
     * @param string $eventName 当前订单计算或通知事件名称。
     * @return list<int|string> 已写入事务 Outbox（事件发件箱）的记录标识。
     */
    private function publishOrderEvent(string $eventName, array|object|int $order, array $context = []): array
    {
        $snapshot = [];
        if (is_int($order)) {
            $row = $this->get($order);
            if (is_object($row) && method_exists($row, 'toArray')) {
                $snapshot = $row->toArray();
            } elseif (is_array($row)) {
                $snapshot = $row;
            }
        } elseif (is_object($order) && method_exists($order, 'toArray')) {
            $snapshot = $order->toArray();
        } elseif (is_array($order)) {
            $snapshot = $order;
        }

        // 下单响应使用 order_id，模型快照使用 id；两种输入都必须能生成 Outbox（事件发件箱）记录。
        $orderId = (int) ($snapshot['id'] ?? $snapshot['order_id'] ?? $context['order_id'] ?? 0);
        if ($orderId <= 0) {
            return [];
        }

        if (($snapshot['update_time'] ?? '') === '' && ($snapshot['create_time'] ?? '') === '') {
            $row = $this->get($orderId);
            if (is_object($row) && method_exists($row, 'toArray')) {
                $snapshot = array_merge($snapshot, $row->toArray());
            } elseif (is_array($row)) {
                $snapshot = array_merge($snapshot, $row);
            }
        }

        $pluginPayload = is_array($context['plugin_payload'] ?? null) ? $context['plugin_payload'] : [];
        $orderTypes = is_array($context['order_types'] ?? null) ? $context['order_types'] : [];
        $children = is_array($context['children'] ?? null) ? $context['children'] : [];
        $payload = [
            'order_id' => $orderId,
            'order_sn' => (string) ($snapshot['order_sn'] ?? $context['order_sn'] ?? ''),
            'main_order_sn' => (string) ($snapshot['main_order_sn'] ?? $context['main_order_sn'] ?? ''),
            'pid' => (int) ($snapshot['pid'] ?? 0),
            'order_role' => (int) ($snapshot['order_role'] ?? 0),
            'user_id' => (int) ($snapshot['user_id'] ?? 0),
            'merchant_id' => (int) ($snapshot['merchant_id'] ?? $context['merchant_id'] ?? 0),
            'merchant_name' => (string) ($snapshot['merchant_name'] ?? $context['merchant_name'] ?? ''),
            'merchant_ids' => is_array($context['merchant_ids'] ?? null)
                ? array_values(array_unique(array_filter(array_map('intval', $context['merchant_ids']), static fn(int $id): bool => $id > 0)))
                : [],
            'order_type' => (int) ($snapshot['order_type'] ?? 0),
            'order_types' => array_values(array_unique(array_filter(array_map('intval', $orderTypes), static fn(int $id): bool => $id > 0))),
            'order_type_text' => (string) ($context['order_type_text'] ?? ''),
            'status' => (int) ($snapshot['status'] ?? $context['status'] ?? 0),
            'status_text' => (string) ($snapshot['status_text'] ?? $context['status_text'] ?? ''),
            'pay_amount' => (float) ($snapshot['pay_amount'] ?? $context['pay_amount'] ?? 0),
            'plugin_payload' => $pluginPayload,
            'children' => $children,
            'meta' => array_diff_key($context, array_flip([
                'order_id',
                'order_sn',
                'main_order_sn',
                'pid',
                'order_role',
                'user_id',
                'merchant_id',
                'merchant_name',
                'merchant_ids',
                'order_type',
                'order_types',
                'order_type_text',
                'status',
                'status_text',
                'pay_amount',
                'plugin_payload',
                'children',
            ])),
        ];

        if (is_array($payload['meta'])) {
            ksort($payload['meta']);
        }
        $metaHash = json_encode($payload['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $published = app()->make(PluginBusinessEventService::class)->publish(
            $eventName,
            $payload,
            [
                'subject_type' => 'order',
                'subject_id' => (string) $orderId,
                'merchant_id' => (int) ($payload['merchant_id'] ?? 0),
                // order.created 是订单事务事实；Outbox（事件发件箱）写入失败必须回滚订单，
                // 不能沿用普通观察者的降级继续策略。
                'strict' => true,
                'strict_persistence' => true,
                'idempotency_key' => 'order:' . substr(hash('sha256', implode('|', [
                    $eventName,
                    (string) $orderId,
                    (string) ($snapshot['update_time'] ?? $snapshot['create_time'] ?? ''),
                    $metaHash === false ? '' : $metaHash,
                ])), 0, 64),
                // Outbox（事件发件箱）必须先随订单事务提交，提交后由 dispatchDeferredPluginEvents() 启动队列。
                'dispatch' => false,
            ]
        );

        $outboxIds = [];
        $outboxId = (int)($published['outbox_id'] ?? $published['id'] ?? 0);
        if ($outboxId > 0) {
            $outboxIds[] = $outboxId;
        } elseif (($eventId = trim((string)($published['event_id'] ?? ''))) !== '') {
            $outboxIds[] = $eventId;
        }
        return $outboxIds;
    }

    /**
     * 在订单事务提交后派发已写入 Outbox（事件发件箱）的观察者任务。
     *
     * 队列基础设施暂不可用时只记录日志，待处理的 Outbox（事件发件箱）记录仍可由扫描器重新认领。
     *
     * @param array $pending 已提交事务的待派发插件事件编号列表。
     * @return void 无返回值；提交后派发观察者任务，队列故障保留发件箱待重试。
     */
    private function dispatchDeferredPluginEvents(array $pending): void
    {
        $pending = array_values(array_unique(array_filter($pending, static fn(mixed $value): bool => is_int($value) || is_string($value))));
        foreach ($pending as $identifier) {
            try {
                PluginEventDispatchJob::dispatch($identifier);
            } catch (\Throwable $exception) {
                Log::warning('[OrderCreateService] order.created observer dispatch deferred', [
                    'identifier' => $identifier,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * 保存订单商品
     *
     * @param int $orderId 订单标识。
     * @param array $items 确认单商品明细，每项包含商品、规格（SKU）、价格、优惠、佣金快照。
     * @param int $merchantId 商户标识，平台自营为 0。
     * @return void 无返回值；持久化不可变商品成交及促销库存身份快照。
     */
    protected function saveOrderGoods(int $orderId, array $items, int $merchantId): void
    {
        $rows = [];
        foreach ($items as $item) {
            $productInfo = is_array($item['product'] ?? null) ? $item['product'] : [];
            $rows[] = [
                'order_id' => $orderId,
                'goods_id' => (int) ($item['goods_id'] ?? 0),
                'merchant_id' => $merchantId,
                'goods_name' => (string) ($item['goods_name'] ?? ''),
                'order_type' => (int) ($item['order_type'] ?? Order::ORDER_TYPE_GOODS),
                'goods_type' => app()->make(PluginCheckoutCapabilityService::class)->isVirtualGoodsItem($item)
                    ? OrderGoods::GOODS_TYPE_VIRTUAL : $this->resolveGoodsType((int)($item['order_type'] ?? Order::ORDER_TYPE_GOODS)),
                'goods_amount' => (float) ($item['line_total'] ?? 0),
                'price' => (float) ($item['price'] ?? 0),
                'num' => (int) ($item['cart_num'] ?? 1),
                'total_price' => (float) ($item['line_total'] ?? 0),
                'first_referrer_id' => (int)($item['first_referrer_id'] ?? 0),
                'second_referrer_id' => (int)($item['second_referrer_id'] ?? 0),
                'commission_base_amount' => (float)($item['commission_base_amount'] ?? 0),
                'first_commission_rate' => (float)($item['first_commission_rate'] ?? 0),
                'second_commission_rate' => (float)($item['second_commission_rate'] ?? 0),
                'commission_level' => (int)($item['commission_level'] ?? 0),
                'commission_mode' => (string)($item['commission_mode'] ?? ''),
                'first_commission' => (float) ($item['first_commission'] ?? 0),
                'second_commission' => (float) ($item['second_commission'] ?? 0),
                'points_deduct_amount' => (float) ($item['points_deduct_amount'] ?? 0),
                'platform_coupon_amount' => (float) ($item['platform_coupon_amount'] ?? 0),
                'merchant_coupon_amount' => (float) ($item['merchant_coupon_amount'] ?? 0),
                'member_discount_amount' => (float)($item['member_discount_amount'] ?? 0),
                'member_discount_rate' => (float)($item['member_discount_rate'] ?? 1.00),
                'goods_info' => [
                    'cart_id' => (int) ($item['cart_id'] ?? $item['id'] ?? 0),
                    // 保存下单时商品与规格（SKU）快照，避免后续商品改价/改名影响历史订单展示。
                    'merchant_id' => $merchantId,
                    'merchant_name' => $item['merchant_name'] ?? '',
                    'goods_id' => (int) ($item['goods_id'] ?? 0),
                    'goods_name' => $item['goods_name'] ?? '',
                    'goods_image' => $item['goods_image'] ?? '',
                    'sku_id' => (int) ($item['sku_id'] ?? 0),
                    'sku_name' => $item['sku_name'] ?? '',
                    'product_attr_unique' => $item['product_attr_unique'] ?? '',
                    'price' => (float) ($item['price'] ?? 0),
                    'cart_num' => (int) ($item['cart_num'] ?? 1),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                    'member_price' => (float)($item['member_price'] ?? $item['price'] ?? 0),
                    'effective_price' => (float)($item['effective_price'] ?? $item['price'] ?? 0),
                    'member_line_total' => (float)($item['member_line_total'] ?? $item['line_total'] ?? 0),
                    'use_member_price' => (bool)($item['use_member_price'] ?? false),
                    'plugin_code' => strtolower(trim((string)($item['plugin_code'] ?? ''))),
                    'promotion_code' => trim((string) ($item['promotion_code'] ?? '')),
                    'plugin_payload' => is_array($item['plugin_payload'] ?? null) ? $item['plugin_payload'] : [],
                    'plugin_config' => is_array(($productInfo['plugin_payload_json'] ?? null)) ? $productInfo['plugin_payload_json'] : [],
                    'plugin_snapshot' => is_array($item['plugin_snapshot'] ?? null) ? $item['plugin_snapshot'] : [],
                    'plugin_business_snapshot' => is_array($item['plugin_business_snapshot'] ?? null) ? $item['plugin_business_snapshot'] : (is_array($item['plugin_snapshot'] ?? null) ? $item['plugin_snapshot'] : []),
                    'plugin_quote_fingerprint' => (string)($item['plugin_quote_fingerprint'] ?? ''),
                    'plugin_quote' => [
                        'price' => (float)($item['price'] ?? 0),
                        'original_price' => (float)($item['original_price'] ?? $item['price'] ?? 0),
                        'line_total' => (float)($item['line_total'] ?? 0),
                        'member_price' => (float)($item['member_price'] ?? $item['price'] ?? 0),
                        'member_line_total' => (float)($item['member_line_total'] ?? $item['line_total'] ?? 0),
                        'pay_amount' => (float)($item['pay_amount'] ?? 0),
                        'use_member_price' => (bool)($item['use_member_price'] ?? false),
                        'points_used' => (int)($item['points_used'] ?? 0),
                        'points_deduct_amount' => (float)($item['points_deduct_amount'] ?? 0),
                        'merchant_coupon_amount' => (float)($item['merchant_coupon_amount'] ?? 0),
                        'platform_coupon_amount' => (float)($item['platform_coupon_amount'] ?? 0),
                        'plugin_discount' => is_array($item['plugin_discount'] ?? null) ? $item['plugin_discount'] : [],
                    ],
                    'plugin_discount' => is_array($item['plugin_discount'] ?? null) ? $item['plugin_discount'] : [],
                    'gift_points' => (int)($item['gift_points'] ?? ($productInfo['gift_points'] ?? 0)),
                    'pay_amount' => (float) ($item['pay_amount'] ?? 0),
                    'first_referrer_id' => (int)($item['first_referrer_id'] ?? 0),
                    'second_referrer_id' => (int)($item['second_referrer_id'] ?? 0),
                    'commission_base_amount' => (float)($item['commission_base_amount'] ?? 0),
                    'first_commission_rate' => (float)($item['first_commission_rate'] ?? 0),
                    'second_commission_rate' => (float)($item['second_commission_rate'] ?? 0),
                    'commission_level' => (int)($item['commission_level'] ?? 0),
                    'commission_mode' => (string)($item['commission_mode'] ?? ''),
                    'first_commission' => (float)($item['first_commission'] ?? 0),
                    'second_commission' => (float)($item['second_commission'] ?? 0),
                    'member_discount_amount' => (float)($item['member_discount_amount'] ?? 0),
                    'member_discount_rate' => (float)($item['member_discount_rate'] ?? 1.00),
                    'freight' => $item['freight'] ?? [], // 运费快照（模板标识/名称/发货时间/计价方式/规则标识/实际运费）
                    'product' => $productInfo,
                    'sku' => $item['sku'] ?? [],
                ],
            ];
        }

        app()->make(OrderGoodsService::class)->saveBatch($rows);
    }

    /**
     * 下单时记录内容/专题订单赠送积分快照。
     *
     * @param array $order 当前业务订单的模型或不可变数据快照。
     * @return void 无返回值；按下单快照记录内容及专题的赠送积分。
     */
    protected function recordOrderGiftPoints(array $order): void
    {
        $orderId = (int)($order['id'] ?? 0);
        $orderType = (int)($order['order_type'] ?? Order::ORDER_TYPE_GOODS);
        if ($orderId <= 0 || !in_array($orderType, [Order::ORDER_TYPE_CONTENT, Order::ORDER_TYPE_SPECIAL], true)) {
            return;
        }

        app()->make(OrderGiftPointsRecordService::class)->recordForOrder(
            $order,
            app()->make(OrderGoodsService::class)->getByOrderId($orderId)
        );
    }

    /**
     * 扁平化分组商品。
     *
     * 主单需要汇总所有商品的订单类型、佣金和库存占用；子单创建时仍保留原来的分组结构。
     *
     * @param array $groups 当前确认单的商户或业务子单分组。
     * @return array 按原分组顺序展开的全部订单商品行。
     */
    protected function flattenGroupItems(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) {
            foreach (($group['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * 汇总分组购物车标识。
     *
     * 多商户确认页会按店铺返回 cart_ids；旧版单商户调用可能只提交 fallbackCartIds。
     *
     * @param array $groups 当前确认单的商户或业务子单分组。
     * @param array $fallbackCartIds 旧版单商户调用提供的备用购物车编号列表。
     * @return array 去重后的分组购物车编号；兼容旧版备用编号。
     */
    protected function collectGroupCartIds(array $groups, array $fallbackCartIds = []): array
    {
        $cartIds = [];
        foreach ($groups as $group) {
            foreach (($group['cart_ids'] ?? []) as $cartId) {
                $cartIds[] = (int)$cartId;
            }
        }

        if ($cartIds === []) {
            $cartIds = array_map('intval', $fallbackCartIds);
        }

        return array_values(array_unique(array_filter($cartIds)));
    }

    /**
     * 构建订单地址载荷。
     *
     * @param array $address 收货地址资料及运费计算所需地区信息。
     * @param array $userData 当前用户资料，用于补齐订单收货人信息。
     * @param bool $isVirtualOrder 是否为无需收货地址的虚拟资源订单。
     * @return array 固化到业务订单的收货资料；虚拟订单使用空地址。
     */
    protected function buildOrderAddressPayload(array $address, array $userData, bool $isVirtualOrder): array
    {
        if ($isVirtualOrder) {
            return [
                'address_id' => 0,
                'real_name' => '',
                'user_phone' => '',
                'province' => '',
                'city' => '',
                'district' => '',
                'detail' => '',
                'user_address' => '',
            ];
        }

        return [
            'address_id' => (int)($address['address_id'] ?? 0),
            'real_name' => (string)($address['real_name'] ?? ''),
            'user_phone' => (string)($address['user_phone'] ?? ($userData['phone'] ?? '')),
            'province' => (string)($address['province'] ?? ''),
            'city' => (string)($address['city'] ?? ''),
            'district' => (string)($address['district'] ?? ''),
            'detail' => (string)($address['detail'] ?? ''),
            'user_address' => (string)($address['user_address'] ?? ''),
        ];
    }

    /**
     * 金额标准化。
     *
     * @param float|string $amount 待计算或标准化的金额，单位为元。
     * @return float 保留两位小数的标准金额，单位为元。
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 高精度金额相加。
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 两侧金额相加后的标准金额，单位为元。
     */
    protected function moneyAdd(float|string $left, float|string $right): float
    {
        return (float)bcadd((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额相减。
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 左侧金额减去右侧金额的标准结果，单位为元。
     */
    protected function moneySub(float|string $left, float|string $right): float
    {
        return (float)bcsub((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额相乘。
     *
     * @param float|string $left 参与高精度乘算的金额、件数或比例。
     * @param float|string $right 参与高精度乘算的另一数值，量纲由调用场景决定。
     * @return float 两侧金额高精度乘算的结果。
     */
    protected function moneyMul(float|string $left, float|string $right): float
    {
        return (float)bcmul((string)$left, (string)$right, 2);
    }

    /**
     * 金额下限为 0。
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 两侧金额中较大的标准金额，单位为元。
     */
    protected function moneyMax(float|string $left, float|string $right): float
    {
        return max($this->normalizeMoney($left), $this->normalizeMoney($right));
    }

    /**
     * 汇总商品佣金。
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @return array 当前商品行的一级和二级佣金金额，单位为元。
     */
    protected function sumItemCommissionAmounts(array $items): array
    {
        $firstCommissionAmount = 0.0;
        $secondCommissionAmount = 0.0;
        foreach ($items as $item) {
            $firstCommissionAmount = (float)bcadd(
                (string)$firstCommissionAmount,
                (string)($item['first_commission'] ?? 0),
                2
            );
            $secondCommissionAmount = (float)bcadd(
                (string)$secondCommissionAmount,
                (string)($item['second_commission'] ?? 0),
                2
            );
        }

        return [$firstCommissionAmount, $secondCommissionAmount];
    }

    /**
     * 根据抵扣金额折算分组积分数量。
     *
     * 子单 use_points 只是按抵扣金额换算出的展示/追溯值；真正扣减和待支付取消退回都由主单执行。
     *
     * @param array $points 整单实际使用的积分数量或抵扣配置。
     * @param float $deductAmount 分组实际抵扣金额，单位为元。
     * @return int 按分组抵扣金额折算的追溯用积分数量。
     */
    protected function resolveGroupUsePoints(array $points, float $deductAmount): int
    {
        $pointsPerYuan = (float)($points['points_per_yuan'] ?? 0);
        if ($pointsPerYuan <= 0 || $deductAmount <= 0) {
            return 0;
        }

        return (int)floor($deductAmount * $pointsPerYuan);
    }

    /**
     * 扣减商品库存（乐观锁，防止并发超卖）。
     *
     * 商品订单按规格（SKU）乐观锁扣减（仅当 stock>=num 时扣减 stock 并增加 sales），
     * 同时同步 store_product.stock 汇总与 store_product.sales 实际销量；
     * 虚拟销量（virtual_sales）不参与扣减。
     * 内容/专题等虚拟订单无库存概念，直接跳过。
     *
     * @param array $items 确认单商品明细（含 sku_id/product_attr_unique/cart_num）。
     * @return void 无返回值；仅对普通实物商品执行条件库存扣减，活动预留由促销服务负责。
     * @throws AdminResponseException 库存不足时抛出。
     */
    protected function decreaseStock(array $items): void
    {
        $skuRows = [];
        $productSales = [];
        foreach ($items as $item) {
            $orderType = (int) ($item['order_type'] ?? Order::ORDER_TYPE_GOODS);
            if ($orderType !== Order::ORDER_TYPE_GOODS) {
                continue;
            }

            // 插件商品的库存/资源由插件事务处理器负责锁定，宿主不再扣减
            // 通用规格（SKU）库存，避免酒店等非标准库存模型被错误地按商品库存处理。
            if (trim((string) ($item['plugin_code'] ?? '')) !== '') {
                continue;
            }

            $skuId = (int) ($item['sku_id'] ?? 0);
            $num = max(1, (int) ($item['cart_num'] ?? 1));
            if ($skuId <= 0) {
                continue;
            }

            $skuRows[] = ['sku_id' => $skuId, 'num' => $num];
            $productId = (int) ($item['goods_id'] ?? 0);
            $productSales[$productId] = ($productSales[$productId] ?? 0) + $num;
        }

        if ($skuRows === []) {
            return;
        }

        $skuService = app()->make(\app\common\service\store\StoreProductSkuService::class);
        $productService = app()->make(\app\common\service\store\StoreProductService::class);

        $productIds = array_keys($productSales);
        sort($productIds, SORT_NUMERIC);
        $productService->getModel()->whereIn('id', $productIds)->order('id asc')->lock(true)->select();
        usort($skuRows, static fn (array $a, array $b): int => $a['sku_id'] <=> $b['sku_id']);

        foreach ($skuRows as $row) {
            // 乐观锁扣减：仅当 stock >= num 时更新，保证并发下不超卖
            $affected = $skuService->getModel()
                ->where('id', $row['sku_id'])
                ->where('stock', '>=', $row['num'])
                ->dec('stock', $row['num'])
                ->inc('sales', $row['num'])
                ->inc('stock_version', 1)
                ->update();

            if (!$affected) {
                throw new AdminResponseException('商品库存不足');
            }
        }

        // 同步店铺商品库存汇总与实际销量，以商品规格为准。
        foreach ($productSales as $productId => $salesNum) {
            $productService->syncProductSnapshot($productId);
            $productService->getModel()
                ->where('id', $productId)
                ->inc('sales', $salesNum)
                ->update();
        }
    }

    /**
     * 解析订单商品类型
     *
     * @param int $orderType 订单类型。
     * @return int 订单商品类型，实物商品或内容商品。
     */
    protected function resolveGoodsType(int $orderType): int
    {
        return $orderType === Order::ORDER_TYPE_GOODS
            ? OrderGoods::GOODS_TYPE_PHYSICAL
            : OrderGoods::GOODS_TYPE_CONTENT;
    }

    /**
     * 获取订单类型文案。
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return string 当前订单资源类型的中文名称。
     */
    protected function resolveOrderTypeText(int $orderType): string
    {
        return match ($orderType) {
            Order::ORDER_TYPE_GOODS => '商品订单',
            Order::ORDER_TYPE_SPECIAL => '专题订单',
            default => '内容订单',
        };
    }

    /**
     * 使用优惠券
     *
     * @param int $couponId 用户优惠券标识，0 表示未使用优惠券。
     * @param int $orderId 订单标识。
     * @return void 无返回值；登记用户优惠券本次订单使用状态。
     */
    protected function useCoupon(int $couponId, int $orderId): void
    {
        if ($couponId <= 0) {
            return;
        }

        app()->make(StoreCouponUserService::class)->useCoupon($couponId, $orderId);
    }

    /**
     * 下单时预约新人/首单/消费满赠优惠券。
     *
     * @param array $order 订单快照。
     * @return void 无返回值；按订单快照预约首单满赠优惠券。
     */
    protected function recordOrderGiftCoupons(array $order): void
    {
        app()->make(StoreCouponIssueService::class)->recordOrderGiftCoupons($order);
    }

    /**
     * 扣减积分
     *
     * @param int $userId 用户标识。
     * @param int $usePoints 本单使用的积分数量。
     * @param string $orderSn 订单号，用于资产流水关联。
     * @return void 无返回值；扣减订单积分并登记业务单号。
     */
    protected function deductPoints(int $userId, int $usePoints, string $orderSn): void
    {
        if ($usePoints <= 0) {
            return;
        }

        app()->make(UserAssetService::class)->changeAsset(
            $userId,
            'points',
            (float) $usePoints,
            'out',
            '订单积分抵扣',
            [
                'source_type' => 'order_points',
                'order_no' => $orderSn,
                'source_desc' => '订单积分抵扣',
            ]
        );
    }

    /**
     * 标记购物车已下单
     *
     * @param int $userId 用户标识。
     * @param array $cartIds 本次下单关联的购物车标识列表。
     * @return void 无返回值；登记所选购物车已被订单使用。
     */
    protected function markCartOrdered(int $userId, array $cartIds): void
    {
        app()->make(CartService::class)->markOrdered($userId, $cartIds);
    }

}
