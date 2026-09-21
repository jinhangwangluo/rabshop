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

use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\service\BaseService;
use app\common\service\setting\ConfigService;
use app\common\service\store\StoreCouponUserService;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserMemberService;
use app\common\service\plugin\PluginOrderQuoteService;
use app\common\service\plugin\PluginCheckoutCapabilityService;

/**
 * 订单金额计算服务
 */
class OrderAmountService extends BaseService
{
    private const MISSING_ADDRESS_MESSAGE = '请选择收货地址';
    private const MISSING_ADDRESS_FREIGHT_MESSAGE = '选择收货地址后计算运费';

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
     * 构建确认单金额
     *
     * @param int $userId 当前业务用户编号。
     * @param array $settlement 包含商户分组与商品行的购物车结算数据。
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @return array 服务端计算的商品行、分组金额、优惠、积分及整单汇总。
     * @throws AdminResponseException
     */
    public function buildConfirmAmount(int $userId, array $settlement, array $options = []): array
    {
        $rule = $settlement['settlement_rule'] ?? [];
        if (!($rule['can_submit'] ?? false)) {
            throw new AdminResponseException((string) ($rule['message'] ?? '当前商品不能一起提交'));
        }

        $settlementGroups = array_values(array_filter($settlement['groups'] ?? [], static function (array $group): bool {
            return !empty($group['items']);
        }));
        if ($settlementGroups === []) {
            throw new AdminResponseException('未找到可提交的商品');
        }
        app()->make(PluginCheckoutCapabilityService::class)->assertCheckoutItems(
            array_merge(...array_map(static fn(array $group): array => $group['items'] ?? [], $settlementGroups))
        );

        $pluginCalculation = [];
        if (!(bool)($options['_plugin_calculated'] ?? false)) {
            $quoteService = app()->make(PluginOrderQuoteService::class);
            if ($quoteService->hasPluginItems($settlementGroups)) {
                $pluginCalculation = $quoteService->calculate(
                    'order.confirm.calculating',
                    $userId,
                    $settlementGroups,
                    array_merge($options, ['surface' => 'mobile'])
                );
                $settlement = $quoteService->applyToSettlement($settlement, $pluginCalculation);
                $settlementGroups = array_values(array_filter($settlement['groups'] ?? [], static function (array $group): bool {
                    return !empty($group['items']);
                }));
            }
        }

        $merchantCouponMap = $this->normalizeMerchantCouponMap($options, $settlementGroups);
        $remarkMap = $this->normalizeMerchantRemarkMap($options);
        $preparedGroups = [];
        $allItems = [];
        $groupBaseAfterMerchantDiscount = [];
        $orderOriginalAmount = 0.0;
        $orderGoodsAmount = 0.0;
        $orderPostage = 0.0;
        $orderMemberDiscountAmount = 0.0;
        $orderMerchantCouponAmount = 0.0;
        $orderMemberEnabled = false;
        $orderIsMember = false;
        $orderMemberDiscountRate = 1.00;
        $orderMemberEndTime = null;
        $groupGoodsAmounts = [];
        $merchantGoodsAmounts = [];

        foreach ($settlementGroups as $groupIndex => $group) {
            $merchantId = max(0, (int)($group['merchant_id'] ?? 0));
            $goodsAmount = $this->sumItemAmount(
                array_values(array_filter(
                    $group['items'] ?? [],
                    static fn(array $item): bool => trim((string)($item['plugin_code'] ?? '')) === ''
                )),
                'line_total'
            );
            $groupGoodsAmounts[$groupIndex] = $goodsAmount;
            $merchantGoodsAmounts[$merchantId] = $this->moneyAdd(
                (float)($merchantGoodsAmounts[$merchantId] ?? 0),
                $goodsAmount
            );
        }

        // 插件商品的会员价由插件决定，宿主会员服务只接收普通商品金额。
        $memberDiscountMap = app()->make(UserMemberService::class)
            ->getMemberDiscountInfoMap($userId, $merchantGoodsAmounts);

        /*
         * 合单确认金额采用“三段式”计算：
         * 1. 先在商户/平台分组内计算商品金额、会员折扣、商户券；
         * 2. 再用各分组扣完商户侧优惠后的余额，统一分摊整单平台券；
         * 3. 最后分摊积分抵扣，避免积分提前占用优惠券可用金额。
         * 每一段都会落到商品行，后续创建子单、退款和结算都依赖这些行级快照。
         */
        foreach ($settlementGroups as $groupIndex => $group) {
            $allGroupItems = array_values($group['items'] ?? []);
            $pluginItems = array_values(array_filter(
                $allGroupItems,
                static fn(array $item): bool => trim((string)($item['plugin_code'] ?? '')) !== ''
            ));
            // 宿主金额服务只计算未被插件接管的商品行。
            $items = array_values(array_filter(
                $allGroupItems,
                static fn(array $item): bool => trim((string)($item['plugin_code'] ?? '')) === ''
            ));
            $merchantId = (int) ($group['merchant_id'] ?? 0);
            $merchantType = (string) ($group['merchant_type'] ?? ($merchantId > 0 ? 'merchant' : 'platform'));
            $pluginSummary = $this->buildPluginGroupSummary($pluginItems, $pluginCalculation);
            $hostGoodsAmount = (float)($groupGoodsAmounts[$groupIndex] ?? 0);
            $goodsAmount = $this->moneyAdd($hostGoodsAmount, (float)($pluginSummary['goods_amount'] ?? 0));
            $originalAmount = $this->moneyAdd(
                $this->sumItemAmount($items, 'original_price', 'cart_num'),
                (float)($pluginSummary['original_amount'] ?? 0)
            );
            // 运费：确认预览允许缺地址，真实下单仍由运费服务抛业务异常。
            // 促销商品沿用原实物商品配送规则，但不叠加宿主优惠。
            $freightItems = array_values(array_filter($allGroupItems, static fn (array $item): bool =>
                trim((string) ($item['plugin_code'] ?? '')) === '' || trim((string) ($item['promotion_code'] ?? '')) !== ''
            ));
            $freightResult = $this->resolveConfirmFreight($freightItems, $options);
            $hostPostage = (float) ($freightResult['postage'] ?? 0.0);
            $freightSnapshots = $freightResult['freight'] ?? [];
            $freightMessage = trim((string) ($freightResult['message'] ?? ''));
            $postage = $this->moneyAdd($hostPostage, (float)($pluginSummary['postage'] ?? 0));
            $totalAmount = $this->moneyAdd($goodsAmount, $postage);
            $memberDiscount = $memberDiscountMap[$merchantId] ?? [
                'enabled' => false,
                'is_member' => false,
                'merchant_joined' => false,
                'discount_rate' => 1.00,
                'discount_amount' => 0.0,
                'member_end_time' => null,
            ];
            /*
             * 会员价必须先落实到商品行：订单使用“会员单价乘以数量”作为会员优惠后的金额，
             * 而不是先汇总整个店铺金额再按比例分摊。这样多件商品、多个商户拆单时，
             * 确认页展示价、子单金额和订单商品快照始终使用同一套两位小数口径。
             */
            $memberPricing = $this->buildMemberPriceItems($items, $memberDiscount);
            $items = $memberPricing['items'];
            $hostMemberDiscountAmount = (float)$memberPricing['discount_amount'];
            $pluginMemberDiscountAmount = (float)($pluginSummary['member_discount_amount'] ?? 0);
            $memberDiscountAmount = $this->moneyAdd($hostMemberDiscountAmount, $pluginMemberDiscountAmount);
            $memberDiscount['discount_amount'] = $hostMemberDiscountAmount;
            // 优惠券只以普通商品为基数，插件商品的优惠由插件报价自行承担。
            $amountAfterMemberDiscount = $this->moneyMax(
                0,
                $this->moneySub(
                    $this->moneyAdd($hostGoodsAmount, $items === [] ? 0 : $hostPostage),
                    $hostMemberDiscountAmount
                )
            );
            // 商户券只在所属商户组内计算，平台自营组不吃商户券，保证每个子单的商户优惠边界清晰。
            $merchantCouponPayload = $pluginItems !== [] && $items === []
                ? ($pluginCalculation['quotes']['hotel']['merchant_coupon'] ?? $this->emptyCouponPayload($merchantType))
                : ($merchantId > 0
                ? $this->buildCouponPayload(
                    $userId,
                    $merchantId,
                    $merchantType,
                    $amountAfterMemberDiscount,
                    $items,
                    (int)($merchantCouponMap[$merchantId] ?? 0),
                    $options
                )
                : $this->emptyCouponPayload($merchantType));
            $hostMerchantCouponAmount = $pluginItems !== [] && $items === []
                ? 0.0 : (float)($merchantCouponPayload['coupon_amount'] ?? 0);
            $pluginMerchantCouponAmount = (float)($pluginSummary['merchant_coupon_amount'] ?? 0);
            $merchantCouponAmount = $this->moneyAdd($hostMerchantCouponAmount, $pluginMerchantCouponAmount);
            $merchantCouponBases = array_map(
                static fn(array $item): float => (float)($item['member_line_total'] ?? $item['line_total'] ?? 0),
                $items
            );
            $merchantCouponShares = $this->allocateAmountByBases($merchantCouponBases, $hostMerchantCouponAmount);
            $enrichedItems = [];
            $groupBaseAmount = 0.0;

            // 商品行已经计算出精确会员价金额，再扣商户券形成平台券/积分前的统一基数。
            foreach ($items as $itemIndex => $item) {
                $baseAfterMerchantDiscount = $this->moneyMax(
                    0,
                    $this->moneySub(
                        (string)($item['member_line_total'] ?? $item['line_total'] ?? 0),
                        (string)($merchantCouponShares[$itemIndex] ?? 0)
                    )
                );
                $enrichedItem = array_merge($item, [
                    'merchant_coupon_amount' => $merchantCouponShares[$itemIndex] ?? 0.0,
                    'platform_coupon_amount' => 0.0,
                    'points_deduct_amount' => 0.0,
                    'base_after_merchant_discount' => $baseAfterMerchantDiscount,
                    'pay_amount' => $baseAfterMerchantDiscount,
                    'first_commission' => 0.0,
                    'second_commission' => 0.0,
                ]);
                $groupBaseAmount = $this->moneyAdd($groupBaseAmount, $baseAfterMerchantDiscount);
                $enrichedItems[] = $enrichedItem;
                $allItems[] = $enrichedItem;
            }

            // 插件行保留插件返回的最终报价，不参与宿主券、会员价和积分分摊。
            foreach ($pluginItems as $pluginItem) {
                $pluginItem['pay_amount'] = $this->normalizeMoney((float)($pluginItem['pay_amount'] ?? $pluginItem['line_total'] ?? 0));
                $pluginItem['line_total'] = $this->normalizeMoney((float)($pluginItem['line_total'] ?? 0));
                $pluginItem['member_line_total'] = $this->normalizeMoney((float)($pluginItem['member_line_total'] ?? $pluginItem['line_total'] ?? 0));
                $pluginItem['use_member_price'] = (bool)($pluginItem['use_member_price'] ?? false);
                $pluginItem['plugin_summary'] = is_array($pluginItem['plugin_summary'] ?? null)
                    ? $pluginItem['plugin_summary']
                    : [];
                $enrichedItems[] = $pluginItem;
                $allItems[] = $pluginItem;
            }

            // 维持原购物车行顺序，便于确认页和订单商品一一对应。
            $itemByCartId = [];
            foreach ($enrichedItems as $enrichedItem) {
                $itemByCartId[(int)($enrichedItem['cart_id'] ?? $enrichedItem['id'] ?? 0)] = $enrichedItem;
            }
            $enrichedItems = [];
            foreach ($allGroupItems as $originalItem) {
                $cartId = (int)($originalItem['cart_id'] ?? $originalItem['id'] ?? 0);
                if (isset($itemByCartId[$cartId])) {
                    $enrichedItems[] = $itemByCartId[$cartId];
                }
            }

            // 运费快照挂到每个商品行，随 goods_info 订单快照保存（模板后续修改不影响历史订单）
            if ($freightSnapshots !== []) {
                foreach ($enrichedItems as &$freightItem) {
                    $freightItem['freight'] = $freightSnapshots;
                }
                unset($freightItem);
            }

            $preparedGroups[$groupIndex] = [
                'merchant_id' => $merchantId,
                'merchant_name' => (string) ($group['merchant_name'] ?? ''),
                'merchant_type' => $merchantType,
                'cart_ids' => array_values(array_unique(array_filter(array_map('intval', $group['cart_ids'] ?? [])))),
                'items' => $enrichedItems,
                'coupon' => $merchantCouponPayload,
                'remark' => $remarkMap[$merchantId] ?? trim((string)($options['remark'] ?? '')),
                'member_discount' => $memberDiscount,
                'freight' => $freightSnapshots,
                    'summary' => [
                    'original_amount' => $this->normalizeMoney($originalAmount),
                    'goods_amount' => $this->normalizeMoney($goodsAmount),
                    'member_goods_amount' => $this->normalizeMoney(
                        $this->moneyAdd(
                            (float)$memberPricing['member_goods_amount'],
                            (float)($pluginSummary['member_goods_amount'] ?? 0)
                        )
                    ),
                    'total_amount' => $this->normalizeMoney($totalAmount),
                    'member_discount_amount' => $this->normalizeMoney($memberDiscountAmount),
                    'merchant_coupon_amount' => $this->normalizeMoney($merchantCouponAmount),
                    'platform_coupon_amount' => $this->normalizeMoney((float)($pluginSummary['platform_coupon_amount'] ?? 0)),
                    'points_deduct_amount' => $this->normalizeMoney((float)($pluginSummary['points_deduct_amount'] ?? 0)),
                    'postage' => $this->normalizeMoney($postage),
                    'freight_message' => $freightMessage,
                    'discount_amount' => $this->normalizeMoney($this->moneyAdd(
                        (float)($pluginSummary['discount_amount'] ?? 0),
                        $this->moneyAdd($hostMemberDiscountAmount, $hostMerchantCouponAmount)
                    )),
                    'pay_amount' => $this->normalizeMoney(
                        $this->moneyAdd(
                            $this->moneyAdd($groupBaseAmount, (float)($pluginSummary['pay_amount'] ?? 0)),
                            $hostPostage
                        )
                    ),
                    'settlement_pay_amount' => $this->normalizeMoney(
                        $this->moneyAdd(
                            $this->moneyAdd($groupBaseAmount, (float)($pluginSummary['settlement_pay_amount'] ?? $pluginSummary['pay_amount'] ?? 0)),
                            $hostPostage
                        )
                    ),
                    'point_base_amount' => $this->normalizeMoney($groupBaseAmount),
                    'host_point_base_amount' => $this->normalizeMoney($groupBaseAmount),
                    'plugin_points_deduct_amount' => $this->normalizeMoney((float)($pluginSummary['points_deduct_amount'] ?? 0)),
                    'plugin_platform_coupon_amount' => $this->normalizeMoney((float)($pluginSummary['platform_coupon_amount'] ?? 0)),
                    'plugin_discount_amount' => $this->normalizeMoney((float)($pluginSummary['discount_amount'] ?? 0)),
                    'host_member_discount_amount' => $this->normalizeMoney($hostMemberDiscountAmount),
                    'host_merchant_coupon_amount' => $this->normalizeMoney($hostMerchantCouponAmount),
                ],
            ];
            $groupBaseAfterMerchantDiscount[$groupIndex] = $this->normalizeMoney($groupBaseAmount);
            $orderOriginalAmount = $this->moneyAdd($orderOriginalAmount, $originalAmount);
            $orderGoodsAmount = $this->moneyAdd($orderGoodsAmount, $goodsAmount);
            $orderPostage = $this->moneyAdd($orderPostage, $postage);
            $orderMemberDiscountAmount = $this->moneyAdd($orderMemberDiscountAmount, $memberDiscountAmount);
            $orderMerchantCouponAmount = $this->moneyAdd($orderMerchantCouponAmount, $merchantCouponAmount);
            $orderMemberEnabled = $orderMemberEnabled || (bool)($memberDiscount['enabled'] ?? false);
            $orderIsMember = $orderIsMember || (bool)($memberDiscount['is_member'] ?? false);
            if ((bool)($memberDiscount['is_member'] ?? false)) {
                $groupMemberDiscountRate = (float)($memberDiscount['discount_rate'] ?? 1.00);
                if ($groupMemberDiscountRate > 0 && $groupMemberDiscountRate < 1) {
                    $orderMemberDiscountRate = $groupMemberDiscountRate;
                }
                $orderMemberEndTime = $memberDiscount['member_end_time'] ?? $orderMemberEndTime;
            }
        }

        $orderTotalAmount = $this->moneyAdd($orderGoodsAmount, $orderPostage);
        $amountAfterMerchantDiscount = $this->sumMoneyValues($groupBaseAfterMerchantDiscount);
        /*
         * 平台券是整单维度的优惠，先按“各分组扣完商户侧优惠后的剩余金额”分摊到子单，
         * 再按子单内商品行继续分摊。平台券默认平台承担：用户实付减少，但商户结算基准
         * 仍需要把平台券分摊额加回去，后续用 settlement_pay_amount 做结算。
         */
        $platformCouponPayload = $pluginCalculation['quotes']['hotel']['platform_coupon'] ?? $this->buildCouponPayload(
            $userId,
            0,
            'platform',
            $amountAfterMerchantDiscount,
            array_values(array_filter(
                $allItems,
                static fn(array $item): bool => trim((string)($item['plugin_code'] ?? '')) === ''
            )),
            (int)($options['platform_coupon_id'] ?? 0),
            $options
        );
        $platformCouponAmount = $this->normalizeMoney((float)($platformCouponPayload['coupon_amount'] ?? 0));
        $groupPlatformShares = $this->allocateAmountByBases($groupBaseAfterMerchantDiscount, $platformCouponAmount);
        $amountAfterPlatformCoupon = 0.0;

        foreach ($preparedGroups as $groupIndex => $group) {
            $platformShare = $groupPlatformShares[$groupIndex] ?? 0.0;
            $hostItemIndexes = [];
            $itemBases = [];
            foreach ($group['items'] as $itemIndex => $item) {
                if (trim((string)($item['plugin_code'] ?? '')) === '') {
                    $hostItemIndexes[] = $itemIndex;
                    $itemBases[] = (float)($item['base_after_merchant_discount'] ?? 0);
                }
            }
            $itemPlatformShares = $this->allocateAmountByBases($itemBases, $platformShare);
            $groupPayBeforePoints = 0.0;
            $hostPayBeforePoints = 0.0;

            foreach ($group['items'] as $itemIndex => $item) {
                if (trim((string)($item['plugin_code'] ?? '')) !== '') {
                    $groupPayBeforePoints = $this->moneyAdd($groupPayBeforePoints, (float)($item['pay_amount'] ?? 0));
                    continue;
                }
                $shareIndex = array_search($itemIndex, $hostItemIndexes, true);
                $itemPlatformShare = $shareIndex === false ? 0.0 : ($itemPlatformShares[$shareIndex] ?? 0.0);
                $baseAfterPlatform = $this->moneyMax(0, $this->moneySub((float)($item['base_after_merchant_discount'] ?? 0), $itemPlatformShare));
                $preparedGroups[$groupIndex]['items'][$itemIndex]['platform_coupon_amount'] = $itemPlatformShare;
                $preparedGroups[$groupIndex]['items'][$itemIndex]['base_after_platform_coupon'] = $baseAfterPlatform;
                $preparedGroups[$groupIndex]['items'][$itemIndex]['pay_amount'] = $baseAfterPlatform;
                $groupPayBeforePoints = $this->moneyAdd($groupPayBeforePoints, $baseAfterPlatform);
                $hostPayBeforePoints = $this->moneyAdd($hostPayBeforePoints, $baseAfterPlatform);
            }

            $pluginPlatformShare = (float)($group['summary']['plugin_platform_coupon_amount'] ?? 0);
            $preparedGroups[$groupIndex]['summary']['platform_coupon_amount'] = $this->normalizeMoney(
                $this->moneyAdd($platformShare, $pluginPlatformShare)
            );
            $groupPostage = (float)($group['summary']['postage'] ?? 0);
            $preparedGroups[$groupIndex]['summary']['pay_amount'] = $this->normalizeMoney(
                $this->moneyAdd($groupPayBeforePoints, $groupPostage)
            );
            $preparedGroups[$groupIndex]['summary']['point_base_amount'] = $this->normalizeMoney($groupPayBeforePoints);
            // 子单用户实付 + 平台券分摊额 = 商户结算基准，避免平台券压低商户应结金额。
            $preparedGroups[$groupIndex]['summary']['settlement_pay_amount'] = $this->normalizeMoney(
                    $this->moneyAdd(
                        $this->moneyAdd($groupPayBeforePoints, $groupPostage),
                        $this->moneyAdd($platformShare, $pluginPlatformShare)
                    )
            );
            // 宿主积分只允许抵扣普通商品，插件商品的积分策略由插件报价决定。
            $amountAfterPlatformCoupon = $this->moneyAdd($amountAfterPlatformCoupon, $hostPayBeforePoints);
        }

        /*
         * 积分最后扣减，并继续分摊到分组和商品行。子单保存 use_points 是为了展示和退款追溯，
         * 但取消待支付合单时实际积分只从主单退回，避免多张子单重复退积分。
         */
        $hostPointsPayload = $this->buildPointsPayload($userId, $amountAfterPlatformCoupon,
            isset($pluginCalculation['quotes']['hotel']) ? 0 : (int)($options['use_points'] ?? 0));
        $pointsPayload = $pluginCalculation['quotes']['hotel']['points'] ?? $hostPointsPayload;
        $pointsAmount = $this->normalizeMoney((float)($hostPointsPayload['deduct_amount'] ?? 0));
        // 宿主积分要等可支付金额计算完成后才知道实际 use_points（请求值可能被截断），
        // 此时再与插件报价中的积分合计校验用户余额，避免确认阶段误拒合法请求。
        if ($pluginCalculation !== []) {
            $pluginPointsUsed = app()->make(PluginOrderQuoteService::class)->pointsUsed($pluginCalculation);
            $hostPointsUsed = max(0, (int)($hostPointsPayload['use_points'] ?? 0));
            $availablePoints = max(0, (int)($pointsPayload['available_points'] ?? 0));
            if ($pluginPointsUsed + $hostPointsUsed > $availablePoints) {
                throw new AdminResponseException('插件与宿主合计使用积分超过用户余额');
            }
        }
        $groupPointBases = [];
        foreach ($preparedGroups as $groupIndex => $group) {
            $groupPointBases[$groupIndex] = (float)($group['summary']['host_point_base_amount'] ?? 0);
        }
        $groupPointShares = $this->allocateAmountByBases($groupPointBases, $pointsAmount);
        $orderPayAmount = 0.0;
        $orderPointsAmount = 0.0;

        foreach ($preparedGroups as $groupIndex => $group) {
            $pointsShare = $groupPointShares[$groupIndex] ?? 0.0;
            $hostItemIndexes = [];
            $itemBases = [];
            foreach ($group['items'] as $itemIndex => $item) {
                if (trim((string)($item['plugin_code'] ?? '')) === '') {
                    $hostItemIndexes[] = $itemIndex;
                    $itemBases[] = (float)($item['base_after_platform_coupon'] ?? 0);
                }
            }
            $itemPointShares = $this->allocateAmountByBases($itemBases, $pointsShare);
            $groupPayAmount = 0.0;

            foreach ($group['items'] as $itemIndex => $item) {
                if (trim((string)($item['plugin_code'] ?? '')) !== '') {
                    $groupPayAmount = $this->moneyAdd($groupPayAmount, (float)($item['pay_amount'] ?? 0));
                    continue;
                }
                $shareIndex = array_search($itemIndex, $hostItemIndexes, true);
                $itemPointShare = $shareIndex === false ? 0.0 : ($itemPointShares[$shareIndex] ?? 0.0);
                $itemPayAmount = $this->moneyMax(0, $this->moneySub((float)($item['base_after_platform_coupon'] ?? 0), $itemPointShare));
                $preparedGroups[$groupIndex]['items'][$itemIndex]['points_deduct_amount'] = $itemPointShare;
                $preparedGroups[$groupIndex]['items'][$itemIndex]['pay_amount'] = $itemPayAmount;
                // 中间基数字段只服务本次计算，不落库，避免订单商品快照出现可被误用的临时口径。
                unset(
                    $preparedGroups[$groupIndex]['items'][$itemIndex]['base_after_merchant_discount'],
                    $preparedGroups[$groupIndex]['items'][$itemIndex]['base_after_platform_coupon']
                );
                $groupPayAmount = $this->moneyAdd($groupPayAmount, $itemPayAmount);
            }

            $pluginPointsAmount = (float)($preparedGroups[$groupIndex]['summary']['plugin_points_deduct_amount'] ?? 0);
            $preparedGroups[$groupIndex]['summary']['points_deduct_amount'] = $this->normalizeMoney($this->moneyAdd($pointsShare, $pluginPointsAmount));
            $preparedGroups[$groupIndex]['summary']['discount_amount'] = $this->normalizeMoney(
                $this->moneyAdd(
                    (float)($group['summary']['plugin_discount_amount'] ?? 0),
                    $this->moneyAdd(
                        (float)($group['summary']['host_member_discount_amount'] ?? 0),
                        $this->moneyAdd(
                            (float)($group['summary']['host_merchant_coupon_amount'] ?? 0),
                            $this->moneyAdd((float)($group['summary']['platform_coupon_amount'] ?? 0), $pointsShare)
                        )
                    )
                )
            );
            $groupPostage = (float)($group['summary']['postage'] ?? 0);
            $groupPayAmountWithPostage = $this->moneyAdd($groupPayAmount, $groupPostage);
            $preparedGroups[$groupIndex]['summary']['pay_amount'] = $this->normalizeMoney($groupPayAmountWithPostage);
            $preparedGroups[$groupIndex]['summary']['settlement_pay_amount'] = $this->normalizeMoney(
                $this->moneyAdd($groupPayAmountWithPostage, (float)($group['summary']['platform_coupon_amount'] ?? 0))
            );
            unset($preparedGroups[$groupIndex]['summary']['point_base_amount']);
            $orderPayAmount = $this->moneyAdd($orderPayAmount, $groupPayAmountWithPostage);
            $orderPointsAmount = $this->moneyAdd($orderPointsAmount, $this->moneyAdd($pointsShare, $pluginPointsAmount));
        }

        $isVirtualOrder = $this->isVirtualOrderItems($allItems);
        $orderDiscountAmount = $this->moneyAdd(
            $orderMemberDiscountAmount,
            $this->moneyAdd($orderMerchantCouponAmount, $this->moneyAdd($platformCouponAmount, $orderPointsAmount))
        );
        // 兼容旧版单商户确认页字段；新版多商户确认页优先使用 groups/platform_coupon/summary。
        $legacyGroup = $preparedGroups[0] ?? [];
        $legacyCoupon = count($preparedGroups) === 1 && (int)($legacyGroup['merchant_id'] ?? 0) > 0
            ? ($legacyGroup['coupon'] ?? $this->emptyCouponPayload('merchant'))
            : $platformCouponPayload;

        $result = [
            'merchant' => [
                'merchant_id' => (int)($legacyGroup['merchant_id'] ?? 0),
                'merchant_name' => (string)($legacyGroup['merchant_name'] ?? ''),
                'merchant_type' => (string)($legacyGroup['merchant_type'] ?? 'platform'),
            ],
            'items' => $legacyGroup['items'] ?? [],
            'groups' => array_values($preparedGroups),
            'coupon' => array_merge($legacyCoupon, [
                'available_count' => (int)($platformCouponPayload['platform_coupon_count'] ?? 0)
                    + $this->sumGroupCouponCounts($preparedGroups),
                'platform_coupon_list' => $platformCouponPayload['platform_coupon_list'] ?? [],
                'platform_coupon_count' => (int)($platformCouponPayload['platform_coupon_count'] ?? 0),
                'merchant_coupon_count' => $this->sumGroupCouponCounts($preparedGroups),
            ]),
            'platform_coupon' => $platformCouponPayload,
            'points' => $pointsPayload,
            'address' => $this->buildAddressPayload($options, $isVirtualOrder),
            'remark' => trim((string) ($options['remark'] ?? '')),
            'plugin_payload' => is_array($options['plugin_payload'] ?? null) ? $options['plugin_payload'] : [],
            'summary' => [
                'original_amount' => $this->normalizeMoney($orderOriginalAmount),
                'goods_amount' => $this->normalizeMoney($orderGoodsAmount),
                'member_goods_amount' => $this->normalizeMoney(
                    $this->moneySub($orderGoodsAmount, $orderMemberDiscountAmount)
                ),
                'total_amount' => $this->normalizeMoney($orderTotalAmount),
                'discount_amount' => $this->normalizeMoney($orderDiscountAmount),
                'member_discount' => [
                    'enabled' => $orderMemberEnabled,
                    'is_member' => $orderIsMember,
                    'merchant_joined' => true,
                    'discount_rate' => $orderIsMember ? $orderMemberDiscountRate : 1.00,
                    'discount_amount' => $this->normalizeMoney($orderMemberDiscountAmount),
                    'member_end_time' => $orderMemberEndTime,
                ],
                'coupon_amount' => $this->normalizeMoney($this->moneyAdd($orderMerchantCouponAmount, $platformCouponAmount)),
                'platform_coupon_amount' => $this->normalizeMoney($platformCouponAmount),
                'merchant_coupon_amount' => $this->normalizeMoney($orderMerchantCouponAmount),
                'points_deduct_amount' => $this->normalizeMoney($orderPointsAmount),
                'postage' => $this->normalizeMoney($orderPostage),
                'freight_message' => $this->resolveOrderFreightMessage($preparedGroups),
                'pay_amount' => $this->normalizeMoney($orderPayAmount),
            ],
        ];

        if ($pluginCalculation !== []) {
            $result = app()->make(PluginOrderQuoteService::class)->applyToConfirm($result, $pluginCalculation);
        }

        return $result;
    }

    /**
     * 酒店插件复用宿主券范围与积分开关/比例，返回可直接落库占用的 ID 和积分数。
     *
     * @param int $userId 登录用户。
     * @param int $merchantId 酒店商品归属商户，平台自营为零。
     * @param float $amount 会员价后的房晚总额。
     * @param array $item 宿主可信购物车行，包含商品与分类信息。
     * @param array $options 用户选择的券及积分，价格不从客户端接收。
     * @return array 券 ID、抵扣额、积分数与最终实付金额。
     */
    public function quoteHotelOffers(int $userId, int $merchantId, float $amount, array $item, array $options): array
    {
        $merchantCouponMap = $this->normalizeMerchantCouponMap($options, [['merchant_id' => $merchantId]]);
        $merchant = $merchantId > 0 ? $this->buildCouponPayload($userId, $merchantId, 'merchant',
            $amount, [$item], (int)($merchantCouponMap[$merchantId] ?? 0), $options)
            : $this->emptyCouponPayload('platform');
        $afterMerchant = $this->moneyMax(0, $this->moneySub($amount, (float)$merchant['coupon_amount']));
        $platform = $this->buildCouponPayload($userId, 0, 'platform', $afterMerchant,
            [$item], (int)($options['platform_coupon_id'] ?? 0), $options);
        $afterCoupons = $this->moneyMax(0, $this->moneySub($afterMerchant, (float)$platform['coupon_amount']));
        $points = $this->buildPointsPayload($userId, $afterCoupons, (int)($options['use_points'] ?? 0));
        return [
            'merchant_coupon' => $merchant, 'platform_coupon' => $platform, 'points' => $points,
            'pay_amount' => $this->moneyMax(0, $this->moneySub($afterCoupons, (float)$points['deduct_amount'])),
        ];
    }

    /**
     * 解析确认单运费。
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @return array 当前实物分组运费与计算明细；允许缺地址预览时附带提示。
     */
    protected function resolveConfirmFreight(array $items, array $options): array
    {
        try {
            return app()->make(\app\common\service\store\FreightTemplateService::class)
                ->calcGroupPostage($items, $this->buildFreightAddress($options));
        } catch (AdminResponseException $e) {
            if (
                !(bool) ($options['allow_missing_address'] ?? false)
                || $e->getMessage() !== self::MISSING_ADDRESS_MESSAGE
            ) {
                throw $e;
            }

            return [
                'postage' => 0.0,
                'freight' => [],
                'message' => self::MISSING_ADDRESS_FREIGHT_MESSAGE,
            ];
        }
    }

    /**
     * 汇总整单运费提示。
     *
     * @param array $groups 当前确认单的商户或业务子单分组。
     * @return string 当前整单需要展示的运费提示；无提示时为空字符串。
     */
    protected function resolveOrderFreightMessage(array $groups): string
    {
        foreach ($groups as $group) {
            $message = trim((string) (($group['summary'] ?? [])['freight_message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        return '';
    }

    /**
     * 按商品单价计算有效会员实际使用的会员价。
     *
     * @param array $items 当前商户分组的商品行。
     * @param array $memberDiscount 当前用户与商户的会员折扣信息。
     * @return array{items: array, discount_amount: float, member_goods_amount: float} 应用会员价后的商品行、会员优惠额与会员价商品金额。
     */
    protected function buildMemberPriceItems(array $items, array $memberDiscount): array
    {
        $discountRate = (float)($memberDiscount['discount_rate'] ?? 1.00);
        $useMemberPrice = (bool)($memberDiscount['enabled'] ?? false)
            && (bool)($memberDiscount['is_member'] ?? false)
            && (bool)($memberDiscount['merchant_joined'] ?? false)
            && $discountRate > 0
            && $discountRate < 1;
        $discountAmount = 0.0;
        $memberGoodsAmount = 0.0;
        $result = [];

        foreach ($items as $item) {
            $price = $this->normalizeMoney((float)($item['price'] ?? 0));
            $quantity = max(1, (int)($item['cart_num'] ?? 1));
            $lineTotal = $this->normalizeMoney((float)($item['line_total'] ?? 0));
            // 与内容详情的会员价口径保持一致：先计算两位小数优惠额，再从普通售价扣减。
            $unitMemberDiscount = $useMemberPrice
                ? $this->moneyMul($price, (string)(1 - $discountRate))
                : 0.0;
            $memberPrice = $useMemberPrice ? $this->moneySub($price, $unitMemberDiscount) : $price;
            $memberLineTotal = $this->moneyMin($lineTotal, $this->moneyMul($memberPrice, $quantity));
            $lineDiscountAmount = $this->moneyMax(0, $this->moneySub($lineTotal, $memberLineTotal));

            $result[] = array_merge($item, [
                'member_price' => $memberPrice,
                'effective_price' => $lineDiscountAmount > 0 ? $memberPrice : $price,
                'member_line_total' => $memberLineTotal,
                'use_member_price' => $lineDiscountAmount > 0,
                'member_discount_amount' => $lineDiscountAmount,
                'member_discount_rate' => $lineDiscountAmount > 0 ? $discountRate : 1.00,
            ]);
            $discountAmount = $this->moneyAdd($discountAmount, $lineDiscountAmount);
            $memberGoodsAmount = $this->moneyAdd($memberGoodsAmount, $memberLineTotal);
        }

        return [
            'items' => $result,
            'discount_amount' => $this->normalizeMoney($discountAmount),
            'member_goods_amount' => $this->normalizeMoney($memberGoodsAmount),
        ];
    }

    /**
     * 汇总插件商品报价，供宿主只做金额校验和通用落库。
     *
     * 插件报价中的 postage/extra_amount 是插件自己的费用；宿主不会再对这些行
     * 调用运费、会员价、优惠券或积分计算。
     *
     * @param array<int, array<string, mixed>> $items 插件商品行。
     * @param array<string, mixed> $calculation 插件计算结果。
     * @return array<string, mixed> 已通过宿主校验并标准化的插件商品金额汇总。
     */
    protected function buildPluginGroupSummary(array $items, array $calculation): array
    {
        $summary = [
            'original_amount' => 0.0,
            'goods_amount' => 0.0,
            'member_goods_amount' => 0.0,
            'member_discount_amount' => 0.0,
            'merchant_coupon_amount' => 0.0,
            'platform_coupon_amount' => 0.0,
            'points_deduct_amount' => 0.0,
            'discount_amount' => 0.0,
            'postage' => 0.0,
            'pay_amount' => 0.0,
            'settlement_pay_amount' => 0.0,
        ];
        $quoteMap = is_array($calculation['quotes'] ?? null) ? $calculation['quotes'] : [];
        $groupLineTotals = [];

        foreach ($items as $item) {
            $pluginCode = strtolower(trim((string)($item['plugin_code'] ?? '')));
            if ($pluginCode === '') {
                continue;
            }
            $quantity = max(1, (int)($item['cart_num'] ?? 1));
            $lineTotal = (float)($item['line_total'] ?? 0);
            $groupLineTotals[$pluginCode] = $this->moneyAdd(
                (float)($groupLineTotals[$pluginCode] ?? 0),
                $lineTotal
            );
            $summary['goods_amount'] = $this->moneyAdd($summary['goods_amount'], $lineTotal);
            $summary['original_amount'] = $this->moneyAdd(
                $summary['original_amount'],
                $this->moneyMul((string)(float)($item['original_price'] ?? $item['price'] ?? 0), (string)$quantity)
            );
            $summary['member_goods_amount'] = $this->moneyAdd(
                $summary['member_goods_amount'],
                (float)($item['member_line_total'] ?? $lineTotal)
            );
            foreach ([
                'member_discount_amount',
                'merchant_coupon_amount',
                'platform_coupon_amount',
                'points_deduct_amount',
            ] as $field) {
                $summary[$field] = $this->moneyAdd($summary[$field], (float)($item[$field] ?? 0));
            }
            $summary['pay_amount'] = $this->moneyAdd($summary['pay_amount'], (float)($item['pay_amount'] ?? $lineTotal));
        }

        /*
         * 计价器返回的 summary 是插件维度总额，而当前方法处理的是商户/订单类型子组。
         * 费用按本组商品金额占比计入，避免同一插件跨商户时重复累加；行级优惠和应付金额
         * 已在商品报价行中完成汇总，因此不再用全局 summary 覆盖子组。
         */
        foreach ($groupLineTotals as $pluginCode => $groupLineTotal) {
            $quote = is_array($quoteMap[$pluginCode] ?? null) ? $quoteMap[$pluginCode] : [];
            $quoteSummary = is_array($quote['summary'] ?? null) ? $quote['summary'] : [];
            $fee = $this->moneyAdd(
                (float)($quoteSummary['postage'] ?? 0),
                (float)($quoteSummary['extra_amount'] ?? $quoteSummary['fee_amount'] ?? 0)
            );
            if ($fee <= 0) {
                continue;
            }
            $quoteLineTotal = 0.0;
            foreach ($quote['items'] ?? [] as $quoteItem) {
                if (is_array($quoteItem)) {
                    $quoteLineTotal = $this->moneyAdd($quoteLineTotal, (float)($quoteItem['line_total'] ?? 0));
                }
            }
            $ratio = $quoteLineTotal > 0
                ? min(1.0, max(0.0, $groupLineTotal / $quoteLineTotal))
                : 1.0;
            $summary['postage'] = $this->moneyAdd($summary['postage'], round($fee * $ratio, 2));
        }

        $summary['pay_amount'] = $this->moneyAdd($summary['pay_amount'], $summary['postage']);
        $summary['settlement_pay_amount'] = $this->moneyAdd(
            $summary['pay_amount'],
            $summary['platform_coupon_amount']
        );
        $summary['discount_amount'] = $this->moneyAdd(
            $summary['member_discount_amount'],
            $this->moneyAdd(
                $summary['merchant_coupon_amount'],
                $this->moneyAdd($summary['platform_coupon_amount'], $summary['points_deduct_amount'])
            )
        );
        $summary['total_amount'] = $this->moneyAdd($summary['goods_amount'], $summary['postage']);
        return array_map(fn($value): float => $this->normalizeMoney((float)$value), $summary);
    }

    /**
     * 解析商户优惠券选择映射。
     *
     * 新版入参 merchant_coupon_ids 支持多个商户分别选券；旧版 merchant_coupon_id 仅在单商户时兼容。
     *
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @param array $groups 当前确认单的商户或业务子单分组。
     * @return array 以商户编号为键的用户优惠券选择映射。
     */
    protected function normalizeMerchantCouponMap(array $options, array $groups): array
    {
        $map = [];
        $rows = $options['merchant_coupon_ids'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $key => $row) {
                if (is_array($row)) {
                    $merchantId = (int)($row['merchant_id'] ?? 0);
                    $couponId = (int)($row['coupon_id'] ?? 0);
                } else {
                    $merchantId = is_numeric($key) ? (int)$key : 0;
                    $couponId = (int)$row;
                }

                if ($merchantId > 0) {
                    $map[$merchantId] = max(0, $couponId);
                }
            }
        }

        $legacyCouponId = (int)($options['merchant_coupon_id'] ?? 0);
        if ($legacyCouponId > 0 && count($groups) === 1) {
            $merchantId = (int)($groups[0]['merchant_id'] ?? 0);
            if ($merchantId > 0 && !isset($map[$merchantId])) {
                $map[$merchantId] = $legacyCouponId;
            }
        }

        return $map;
    }

    /**
     * 解析商户备注映射。
     *
     * 备注跟随商户分组进入子单，主单只保留旧版 remark，避免多个商户备注互相覆盖。
     *
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @return array 以商户编号为键的子订单备注映射。
     */
    protected function normalizeMerchantRemarkMap(array $options): array
    {
        $map = [];
        $rows = $options['merchant_remarks'] ?? [];
        if (!is_array($rows)) {
            return $map;
        }

        foreach ($rows as $key => $row) {
            if (is_array($row)) {
                $merchantId = (int)($row['merchant_id'] ?? 0);
                $remark = trim((string)($row['remark'] ?? ''));
            } else {
                $merchantId = is_numeric($key) ? (int)$key : 0;
                $remark = trim((string)$row);
            }

            if ($merchantId >= 0) {
                $map[$merchantId] = mb_substr($remark, 0, 255);
            }
        }

        return $map;
    }

    /**
     * 空优惠券载荷。
     *
     * @param string $merchantType 店铺主体类型，区分平台自营和商户。
     * @return array 不使用优惠券时的空列表、零抵扣和主体类型。
     */
    protected function emptyCouponPayload(string $merchantType = 'merchant'): array
    {
        return [
            'selected_coupon_id' => 0,
            'coupon_amount' => 0.0,
            'platform_coupon_list' => [],
            'merchant_coupon_list' => [],
            'platform_coupon_count' => 0,
            'merchant_coupon_count' => 0,
            'selected_coupon' => null,
            'merchant_type' => $merchantType,
        ];
    }

    /**
     * 汇总分组商户券数量。
     *
     * @param array $groups 当前确认单的商户或业务子单分组。
     * @return int 当前商户分组可用商户优惠券的总数量。
     */
    protected function sumGroupCouponCounts(array $groups): int
    {
        $count = 0;
        foreach ($groups as $group) {
            $count += (int)($group['coupon']['merchant_coupon_count'] ?? 0);
        }

        return $count;
    }

    /**
     * 构建优惠券数据
     *
     * @param int $userId 当前业务用户编号。
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @param string $merchantType 店铺主体类型，区分平台自营和商户。
     * @param float $goodsAmount 参与优惠计算的商品金额，单位为元。
     * @param array $items 当前结算或订单中的商品明细列表。
     * @param int $selectedCouponId 当前选中的用户优惠券记录编号。
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @return array 当前分组的可用优惠券、选中券及实际抵扣金额。
     * @throws AdminResponseException
     */
    protected function buildCouponPayload(int $userId, int $merchantId, string $merchantType, float $goodsAmount, array $items, int $selectedCouponId, array $options = []): array
    {
        $couponService = app()->make(StoreCouponUserService::class);
        $availableCoupons = $couponService->getAvailableOrderCoupons($userId, $goodsAmount, $merchantId, $items);
        $selectedCoupon = null;
        $autoSelectCoupon = (bool) ($options['auto_select_coupon'] ?? false);

        if ($selectedCouponId <= 0 && $autoSelectCoupon && $availableCoupons !== []) {
            $selectedCoupon = $availableCoupons[0];
        }

        if ($selectedCouponId > 0) {
            foreach ($availableCoupons as $coupon) {
                if ((int) $coupon['id'] === $selectedCouponId) {
                    $selectedCoupon = $coupon;
                    break;
                }
            }

            if ($selectedCoupon === null) {
                throw new AdminResponseException('所选优惠券不可用');
            }
        }

        $couponAmount = 0.0;
        if ($selectedCoupon !== null) {
            $couponAmount = $this->moneyMin($goodsAmount, (float) ($selectedCoupon['discount_amount'] ?? 0));
        }

        $couponList = array_map(function (array $coupon): array {
            return [
                'id' => (int) $coupon['id'],
                'coupon_id' => (int) $coupon['coupon_id'],
                'merchant_id' => (int) $coupon['merchant_id'],
                'name' => (string) ($coupon['name'] ?? ''),
                'discount_amount' => $this->normalizeMoney((float) ($coupon['discount_amount'] ?? 0)),
                'min_order_amount' => $this->normalizeMoney((float) ($coupon['min_order_amount'] ?? 0)),
                'valid_start_time' => $coupon['valid_start_time'] ?? null,
                'valid_end_time' => $coupon['valid_end_time'] ?? null,
                'coupon' => $coupon['coupon'] ?? [],
            ];
        }, $availableCoupons);

        return [
            'selected_coupon_id' => $selectedCoupon ? (int) $selectedCoupon['id'] : 0,
            'coupon_amount' => $this->normalizeMoney($couponAmount),
            'platform_coupon_list' => $merchantId === 0 ? $couponList : [],
            'merchant_coupon_list' => $merchantId > 0 ? $couponList : [],
            'platform_coupon_count' => $merchantId === 0 ? count($couponList) : 0,
            'merchant_coupon_count' => $merchantId > 0 ? count($couponList) : 0,
            'selected_coupon' => $selectedCoupon,
            'merchant_type' => $merchantType,
        ];
    }

    /**
     * 构建积分数据
     *
     * @param int $userId 当前业务用户编号。
     * @param float $payableAmount 积分抵扣前可支付金额，单位为元。
     * @param int $requestedPoints 用户希望抵扣的积分数量。
     * @return array 积分开关、可用及实际使用积分、抵扣金额和规则提示。
     */
    protected function buildPointsPayload(int $userId, float $payableAmount, int $requestedPoints): array
    {
        $availablePoints = (int)app()->make(UserAssetService::class)
            ->getAssetValue($userId, UserAssetService::TYPE_POINTS);
        $rule = $this->resolvePointsRule();

        if (!$rule['enabled']) {
            return [
                'enabled' => false,
                'config_key' => $rule['config_key'],
                'points_per_yuan' => 0.0,
                'deduct_limit_points' => 0,
                'available_points' => $availablePoints,
                'max_use_points' => 0,
                'use_points' => 0,
                'deduct_amount' => 0.0,
                'message' => $rule['message'],
            ];
        }

        if ($availablePoints <= 0 || $payableAmount <= 0) {
            return [
                'enabled' => true,
                'config_key' => $rule['config_key'],
                'points_per_yuan' => $rule['points_per_yuan'],
                'deduct_limit_points' => $rule['deduct_limit_points'],
                'available_points' => $availablePoints,
                'max_use_points' => 0,
                'use_points' => 0,
                'deduct_amount' => 0.0,
                'message' => '当前没有可抵扣积分',
            ];
        }

        $maxUsePoints = min($availablePoints, (int) floor((float) bcmul((string) $payableAmount, (string) $rule['points_per_yuan'], 4)));
        if ((int)$rule['deduct_limit_points'] > 0) {
            $maxUsePoints = min($maxUsePoints, (int)$rule['deduct_limit_points']);
        }
        $usePoints = min(max($requestedPoints, 0), $maxUsePoints);
        $deductAmount = $usePoints > 0 ? $this->moneyDiv((string) $usePoints, (string) $rule['points_per_yuan']) : 0.0;

        return [
            'enabled' => true,
            'config_key' => $rule['config_key'],
            'points_per_yuan' => $rule['points_per_yuan'],
            'deduct_limit_points' => $rule['deduct_limit_points'],
            'available_points' => $availablePoints,
            'max_use_points' => $maxUsePoints,
            'use_points' => $usePoints,
            'deduct_amount' => $this->moneyMin($payableAmount, $deductAmount),
            'message' => $maxUsePoints > 0 ? '可使用积分抵扣' : '当前没有可抵扣积分',
        ];
    }

    /**
     * 解析积分规则
     *
     * @return array 积分是否启用、兑换比例、使用上限及规则提示。
     */
    protected function resolvePointsRule(): array
    {
        if (!$this->isPointsEnabled()) {
            return [
                'enabled' => false,
                'config_key' => ConfigKeyEnum::POINTS_ENABLED,
                'points_per_yuan' => 0.0,
                'deduct_limit_points' => 0,
                'message' => '积分功能已关闭',
            ];
        }

        $configValues = app()->make(ConfigService::class)->getMultiConfigVlaue([
            ConfigKeyEnum::ORDER_POINTS_RATIO,
            ConfigKeyEnum::ORDER_POINTS_DEDUCT_LIMIT,
        ], false);

        $limitPoints = $this->normalizeIntegerConfigValue($configValues[ConfigKeyEnum::ORDER_POINTS_DEDUCT_LIMIT] ?? 0);
        $pointsPerYuan = $this->normalizeIntegerConfigValue($configValues[ConfigKeyEnum::ORDER_POINTS_RATIO] ?? 0);
        if ($pointsPerYuan > 0) {
            return [
                'enabled' => true,
                'config_key' => ConfigKeyEnum::ORDER_POINTS_RATIO,
                'points_per_yuan' => $pointsPerYuan,
                'deduct_limit_points' => $limitPoints,
                'message' => 'ok',
            ];
        }

        return [
            'enabled' => false,
            'config_key' => '',
            'points_per_yuan' => 0.0,
            'deduct_limit_points' => $limitPoints,
            'message' => '未配置积分抵扣规则',
        ];
    }

    /**
     * 判断积分总开关是否开启。
     *
     * @return bool 平台积分总开关是否开启。
     */
    protected function isPointsEnabled(): bool
    {
        return (int)db_config(ConfigKeyEnum::POINTS_ENABLED, '1') === 1;
    }

    /**
     * 配置中的积分数按整数口径解析。
     *
     * @param mixed $value 待解析的原始字段值或筛选值。
     * @return int 按非负整数口径解析的配置值；非法值返回零。
     */
    protected function normalizeIntegerConfigValue(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        return max(0, (int)floor((float)$value));
    }

    /**
     * 构建运费计算所需收货地址（省/市/区名称）
     *
     * @param array $options 确认单选项（含 province/city/district 等）
     * @return array 收货地址
     */
    protected function buildFreightAddress(array $options): array
    {
        return [
            'province' => trim((string) ($options['province'] ?? '')),
            'city' => trim((string) ($options['city'] ?? '')),
            'district' => trim((string) ($options['district'] ?? '')),
            'province_id' => (int) ($options['province_id'] ?? 0),
            'city_id' => (int) ($options['city_id'] ?? 0),
            'district_id' => (int) ($options['district_id'] ?? 0),
            'area_id' => (int) ($options['area_id'] ?? 0),
            'detail' => trim((string) ($options['user_address'] ?? ($options['detail'] ?? ''))),
        ];
    }

    /**
     * 构建地址信息
     *
     * @param array $options 已校验的结算选项，包含地址、优惠选择与备注。
     * @param bool $isVirtualOrder 是否为无需收货地址的虚拟资源订单。
     * @return array 实物订单收货地址；虚拟订单返回空收货资料。
     */
    protected function buildAddressPayload(array $options, bool $isVirtualOrder): array
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
            'address_id' => (int) ($options['address_id'] ?? 0),
            'real_name' => trim((string) ($options['real_name'] ?? '')),
            'user_phone' => trim((string) ($options['user_phone'] ?? '')),
            'province' => trim((string) ($options['province'] ?? '')),
            'city' => trim((string) ($options['city'] ?? '')),
            'district' => trim((string) ($options['district'] ?? '')),
            'province_id' => (int) ($options['province_id'] ?? 0),
            'city_id' => (int) ($options['city_id'] ?? 0),
            'district_id' => (int) ($options['district_id'] ?? 0),
            'area_id' => (int) ($options['area_id'] ?? 0),
            'detail' => trim((string) ($options['detail'] ?? '')),
            'user_address' => trim((string) ($options['user_address'] ?? '')),
        ];
    }

    /**
     * 是否虚拟订单项
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @return bool 当前全部商品行是否均属于虚拟资源。
     */
    protected function isVirtualOrderItems(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if ((int) ($item['order_type'] ?? Order::ORDER_TYPE_GOODS) === Order::ORDER_TYPE_GOODS
                && !app()->make(PluginCheckoutCapabilityService::class)->isVirtualGoodsItem($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 按商品分摊优惠与积分
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @param float $couponAmount 待分摊的优惠券抵扣金额，单位为元。
     * @param float $pointsAmount 待分摊的积分抵扣金额，单位为元。
     * @param float $memberDiscountAmount 待分摊的会员优惠金额，单位为元。
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @return array 已按商品金额分配优惠券、积分和会员优惠的商品明细。
     */
    protected function allocateItemDiscounts(array $items, float $couponAmount, float $pointsAmount, float $memberDiscountAmount, int $merchantId): array
    {
        $memberShares = $this->allocateAmountByItems($items, $memberDiscountAmount);
        $couponShares = $this->allocateAmountByItems($items, $couponAmount);
        $pointsShares = $this->allocateAmountByItems($items, $pointsAmount);
        $result = [];

        foreach (array_keys($items) as $index) {
            $result[$index] = [
                'member_discount_amount' => $memberShares[$index] ?? 0.0,
                'platform_coupon_amount' => $merchantId === 0 ? ($couponShares[$index] ?? 0.0) : 0.0,
                'merchant_coupon_amount' => $merchantId > 0 ? ($couponShares[$index] ?? 0.0) : 0.0,
                'points_deduct_amount' => $pointsShares[$index] ?? 0.0,
            ];
        }

        return $result;
    }

    /**
     * 按商品金额比例分摊金额
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @param float $amount 待计算或标准化的金额，单位为元。
     * @return array 与商品行顺序一致且合计等于待分摊金额的金额份额。
     */
    protected function allocateAmountByItems(array $items, float $amount): array
    {
        $amount = $this->normalizeMoney($amount);
        if ($amount <= 0 || $items === []) {
            return array_fill(0, count($items), 0.0);
        }

        $total = $this->sumItemAmount($items, 'line_total');
        if ($total <= 0) {
            return array_fill(0, count($items), 0.0);
        }

        $shares = [];
        $remain = $amount;
        $lastIndex = array_key_last($items);

        foreach ($items as $index => $item) {
            if ($index === $lastIndex) {
                $share = $remain;
            } else {
                $lineTotal = (string) ($item['line_total'] ?? '0');
                $ratio = $this->moneyDiv($lineTotal, (string) $total, 4);
                $share = $this->moneyMul((string) $amount, (string) $ratio);
                if ($this->moneyCompare($share, $remain) > 0) {
                    $share = $remain;
                }
                $remain = $this->moneySub($remain, $share);
            }

            $shares[$index] = $this->normalizeMoney($share);
        }

        return $shares;
    }

    /**
     * 按自定义金额基数分摊金额。
     *
     * 平台券、积分这类整单优惠都走这里分摊；最后一个分摊对象承接四舍五入后的尾差，
     * 保证“子单合计 = 主单优惠金额”，不会出现一分钱分差。
     *
     * @param array $bases 各分摊对象的金额权重，保留原键顺序。
     * @param float $amount 待计算或标准化的金额，单位为元。
     * @return array 保留原权重键且尾差已校正的金额分摊结果。
     */
    protected function allocateAmountByBases(array $bases, float $amount): array
    {
        $amount = $this->normalizeMoney($amount);
        $bases = array_map(fn($base): float => $this->normalizeMoney((float)$base), array_values($bases));
        if ($amount <= 0 || $bases === []) {
            return array_fill(0, count($bases), 0.0);
        }

        $total = $this->sumMoneyValues($bases);
        if ($total <= 0) {
            return array_fill(0, count($bases), 0.0);
        }

        $shares = [];
        $remain = $amount;
        $lastIndex = array_key_last($bases);

        foreach ($bases as $index => $base) {
            if ($index === $lastIndex) {
                $share = $remain;
            } else {
                $ratio = $this->moneyDiv((string)$base, (string)$total, 4);
                $share = $this->moneyMul((string)$amount, (string)$ratio);
                if ($this->moneyCompare($share, $remain) > 0) {
                    $share = $remain;
                }
                $remain = $this->moneySub($remain, $share);
            }

            $shares[$index] = $this->normalizeMoney($share);
        }

        return $shares;
    }

    /**
     * 汇总金额数组。
     *
     * @param array $values 待累计的金额列表。
     * @return float 标准化后的金额合计，单位为元。
     */
    protected function sumMoneyValues(array $values): float
    {
        $amount = '0';
        foreach ($values as $value) {
            $amount = bcadd($amount, (string)$value, 2);
        }

        return $this->normalizeMoney($amount);
    }

    /**
     * 汇总商品金额
     *
     * @param array $items 当前结算或订单中的商品明细列表。
     * @param string $field 用于累计金额的商品字段名。
     * @param ?string $multiplyField 需要乘算的数量字段名；为空时直接累计金额。
     * @return float 按指定字段及可选数量字段累计的商品金额，单位为元。
     */
    protected function sumItemAmount(array $items, string $field, ?string $multiplyField = null): float
    {
        $amount = '0';
        foreach ($items as $item) {
            $value = (string) ($item[$field] ?? '0');
            if ($multiplyField !== null) {
                $value = (string) $this->moneyMul($value, (string) ($item[$multiplyField] ?? '1'));
            }
            $amount = $this->moneyAdd($amount, $value);
        }

        return $this->normalizeMoney($amount);
    }

    /**
     * 金额标准化（高精度）
     *
     * @param float|string $amount 待计算或标准化的金额，单位为元。
     * @return float 保留两位小数的标准金额，单位为元。
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float) bcadd((string) $amount, '0', 2);
    }

    /**
     * 金额相加（高精度）
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 两侧金额相加后的标准金额，单位为元。
     */
    protected function moneyAdd(float|string $left, float|string $right): float
    {
        return (float) bcadd((string) $left, (string) $right, 2);
    }

    /**
     * 金额相减（高精度）
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 左侧金额减去右侧金额的标准结果，单位为元。
     */
    protected function moneySub(float|string $left, float|string $right): float
    {
        return (float) bcsub((string) $left, (string) $right, 2);
    }

    /**
     * 金额相乘（高精度）
     *
     * @param float|string $left 参与高精度乘算的金额、件数或比例。
     * @param float|string $right 参与高精度乘算的另一数值，量纲由调用场景决定。
     * @return float 两侧金额高精度乘算的结果。
     */
    protected function moneyMul(float|string $left, float|string $right): float
    {
        return (float) bcmul((string) $left, (string) $right, 2);
    }

    /**
     * 金额相除（高精度）
     *
     * @param float|string $dividend 高精度除法的被除数。
     * @param float|string $divisor 高精度除法的除数。
     * @param int $scale 除法结果保留的小数位数。
     * @return float 按指定精度相除的结果；除数为零时返回零。
     */
    protected function moneyDiv(float|string $dividend, float|string $divisor, int $scale = 2): float
    {
        if (bccomp((string) $divisor, '0', 10) === 0) {
            return 0.0;
        }
        return (float) bcdiv((string) $dividend, (string) $divisor, $scale);
    }

    /**
     * 金额比较（高精度）
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return int 左侧小于、等于或大于右侧时分别返回负一、零或一。
     */
    protected function moneyCompare(float|string $left, float|string $right): int
    {
        return bccomp((string) $left, (string) $right, 2);
    }

    /**
     * 金额较小值（高精度）
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 两侧金额中较小的标准金额，单位为元。
     */
    protected function moneyMin(float|string $left, float|string $right): float
    {
        return $this->moneyCompare($left, $right) <= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }

    /**
     * 金额较大值（高精度）
     *
     * @param float|string $left 参与高精度运算的左侧金额，单位为元。
     * @param float|string $right 参与高精度运算的右侧金额，单位为元。
     * @return float 两侧金额中较大的标准金额，单位为元。
     */
    protected function moneyMax(float|string $left, float|string $right): float
    {
        return $this->moneyCompare($left, $right) >= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }
}
