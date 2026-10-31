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

use app\common\enum\OrderConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\order\Cart;
use app\common\plugin\PluginRegistry;
use app\common\service\BaseService;
use app\common\service\plugin\PluginOrderQuoteService;
use app\common\service\plugin\PluginCheckoutCapabilityService;

/**
 * 购物车服务
 */
class CartService extends BaseService
{
    public const LIVE_GOODS_ATTR_PREFIX = 'live:';
    public const MAX_SETTLEMENT_MERCHANTS = 10;
    public const MERCHANT_LIMIT_MESSAGE = '最多支持10个商户一起结算，平台也计1个';

    /**
     * 设置模型
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return Cart::class;
    }

    /**
     * 获取购物车列表
     *
     * @param int $userId 当前业务用户编号。
     * @param bool $includeNew 是否包含立即购买使用的临时购物车。
     * @return array 当前用户购物车的汇总、商户分组、失效项及结算规则。
     */
    public function getList(int $userId, bool $includeNew = false): array
    {
        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('is_pay', 0)
            ->order('id desc');

        if (!$includeNew) {
            $query->where('is_new', 0);
        }

        $cartList = $query->select()->toArray();

        return $this->buildGroupedResult($cartList);
    }

    /**
     * 获取用户购物车中的专题ID列表。
     *
     * @param int $userId 用户ID。
     * @param int $limit 限制条数。
     * @return array 用户未支付购物车中去重后的专题编号列表。
     */
    public function getUserSpecialCartIds(int $userId, int $limit = 20): array
    {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $specialIds = $this->getModel()
            ->where('user_id', $userId)
            ->where('order_type', Cart::ORDER_TYPE_SPECIAL)
            ->where('is_pay', 0)
            ->where('status', 1)
            ->order('update_time', 'desc')
            ->limit($limit)
            ->column('goods_id');

        return array_values(array_unique(array_filter(array_map('intval', $specialIds))));
    }

    /**
     * 加入购物车
     *
     * @param int $userId 当前业务用户编号。
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @param int $cartNum 当前商品的购买件数。
     * @param string $productAttrUnique 商品规格唯一标识；直播立即购买可使用内部商品标记。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param array $pluginPayload 按插件编码隔离的扩展入参，需经服务端重新校验。
     * @return array 加入购物车后包含当前商品与规格信息的购物车项。
     * @throws AdminResponseException
     */
    public function add(int $userId, int $goodsId, int $cartNum, string $productAttrUnique = '', int $orderType = Cart::ORDER_TYPE_GOODS, array $pluginPayload = []): array
    {
        $cartNum = $this->normalizeCartNum($cartNum, $orderType);
        $resource = $this->getAvailableResource($goodsId, $orderType);
        if ((string)($resource['plugin_code'] ?? '') === 'hotel' && $cartNum !== 1) {
            throw new AdminResponseException('酒店购物车项固定一份，预订间数请选房型');
        }
        $sku = $this->getProductSku($resource, $productAttrUnique, $orderType);
        $productAttrUnique = $this->normalizeProductAttrUnique($productAttrUnique, $sku, $orderType);
        $merchantId = $this->normalizeMerchantId($resource, $orderType);

        if ($pluginPayload !== [] && trim((string) ($resource['plugin_code'] ?? '')) === '') {
            throw new AdminResponseException('活动商品仅支持立即抢购');
        }

        $this->assertNotPurchased($userId, $goodsId, $orderType);
        $this->assertStock($resource, $sku, $cartNum, $orderType);

        $where = [
            'user_id' => $userId,
            'goods_id' => $goodsId,
            'order_type' => $orderType,
            'product_attr_unique' => $productAttrUnique,
            'is_pay' => 0,
            'is_new' => 0,
        ];
        $normalisedPayload = $this->normalisePluginPayload($resource, $pluginPayload);

        // 已有购物车保留原插件载荷；调用方传入新载荷时再按商品归属插件覆盖。
        $cart = $this->get($where, ['id', 'cart_num', 'plugin_payload_json']);
        if ($cart) {
            $nextCartNum = (string)($resource['plugin_code'] ?? '') === 'hotel' || $this->isVirtualOrderType($orderType)
                ? 1
                : ((int) $cart['cart_num'] + $cartNum);
            $this->assertStock($resource, $sku, $nextCartNum, $orderType);
            if ($normalisedPayload === [] && trim((string)($resource['plugin_code'] ?? '')) !== '') {
                $normalisedPayload = $this->decodeJsonPayload($cart['plugin_payload_json'] ?? []);
            }
            $this->update((int) $cart['id'], [
                'cart_num' => $nextCartNum,
                'merchant_id' => $merchantId,
                'product_attr_unique' => $productAttrUnique,
                'plugin_payload_json' => $normalisedPayload,
                'status' => 1,
            ]);
            $cartId = (int) $cart['id'];
        } else {
            $this->assertMerchantLimitOnAdd($userId, $merchantId);
            $created = $this->create([
                'user_id' => $userId,
                'merchant_id' => $merchantId,
                'order_type' => $orderType,
                'goods_id' => $goodsId,
                'product_attr_unique' => $productAttrUnique,
                'cart_num' => $cartNum,
                'plugin_payload_json' => $normalisedPayload,
                'is_pay' => 0,
                'is_new' => 0,
                'status' => 1,
            ]);
            $cartId = (int) $created->id;
            $nextCartNum = $cartNum;
        }

        return $this->buildCartItemResponse([
            'id' => $cartId,
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'order_type' => $orderType,
            'goods_id' => $goodsId,
            'product_attr_unique' => $productAttrUnique,
            'cart_num' => $nextCartNum,
            'is_pay' => 0,
            'is_new' => 0,
            'status' => 1,
            'plugin_payload_json' => $normalisedPayload ?? $this->normalisePluginPayload($resource, $pluginPayload),
        ], $resource, $sku, $orderType);
    }

    /**
     * 立即购买
     *
     * @param int $userId 当前业务用户编号。
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @param int $cartNum 当前商品的购买件数。
     * @param string $productAttrUnique 商品规格唯一标识；直播立即购买可使用内部商品标记。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param array $pluginPayload 按插件编码隔离的扩展入参，需经服务端重新校验。
     * @return array 已创建的可信立即购买临时购物车项。
     * @throws AdminResponseException
     */
    public function buyNow(int $userId, int $goodsId, int $cartNum, string $productAttrUnique = '', int $orderType = Cart::ORDER_TYPE_GOODS, array $pluginPayload = []): array
    {
        $cartNum = $this->normalizeCartNum($cartNum, $orderType);
        $resource = $this->getAvailableResource($goodsId, $orderType);
        if ((string)($resource['plugin_code'] ?? '') === 'hotel' && $cartNum !== 1) {
            throw new AdminResponseException('酒店购物车项固定一份，预订间数请选房型');
        }
        $sku = $this->getProductSku($resource, $productAttrUnique, $orderType);
        $productAttrUnique = $this->normalizeProductAttrUnique($productAttrUnique, $sku, $orderType, true);
        $merchantId = $this->normalizeMerchantId($resource, $orderType);

        $this->assertNotPurchased($userId, $goodsId, $orderType);
        $this->assertStock($resource, $sku, $cartNum, $orderType);
        $promotionCode = '';
        if ($pluginPayload !== [] && trim((string) ($resource['plugin_code'] ?? '')) === '') {
            if (!$this->isGoodsOrderType($orderType) || !$sku) {
                throw new AdminResponseException('活动仅支持普通实物商品');
            }
            $normalisedPayload = app()->make(OrderPromotionService::class)->normaliseCartPayload($userId, $resource, $sku, $cartNum, $pluginPayload);
            $promotionCode = (string) array_key_first($normalisedPayload);
        } else {
            $normalisedPayload = $this->normalisePluginPayload($resource, $pluginPayload);
        }

        return $this->transaction(function () use (
            $userId,
            $goodsId,
            $cartNum,
            $productAttrUnique,
            $orderType,
            $pluginPayload,
            $normalisedPayload,
            $promotionCode,
            $resource,
            $sku,
            $merchantId
        ) {
            $this->getModel()
                ->where('user_id', $userId)
                ->where('is_pay', 0)
                ->where('is_new', 1)
                ->delete();

            $created = $this->create([
                'user_id' => $userId,
                'merchant_id' => $merchantId,
                'order_type' => $orderType,
                'goods_id' => $goodsId,
                'product_attr_unique' => $productAttrUnique,
                'cart_num' => $cartNum,
                'plugin_payload_json' => $normalisedPayload,
                'promotion_code' => $promotionCode,
                'is_pay' => 0,
                'is_new' => 1,
                'status' => 1,
            ]);
            $cartId = (int) $created->id;

            return [
                'cart' => $this->buildCartItemResponse([
                    'id' => $cartId,
                    'user_id' => $userId,
                    'merchant_id' => $merchantId,
                    'order_type' => $orderType,
                    'goods_id' => $goodsId,
                    'product_attr_unique' => $productAttrUnique,
                    'cart_num' => $cartNum,
                    'is_pay' => 0,
                    'is_new' => 1,
                    'status' => 1,
                    'plugin_payload_json' => $normalisedPayload,
                    'promotion_code' => $promotionCode,
                ], $resource, $sku, $orderType),
                'preview' => $this->getSettlementPreview($userId, [$cartId], true),
            ];
        });
    }

    /**
     * 修改购物车数量
     *
     * @param int $userId 当前业务用户编号。
     * @param int $cartId 当前用户所属购物车记录编号。
     * @param int $cartNum 当前商品的购买件数。
     * @return array 购买数量更新后的购物车商品详情。
     * @throws AdminResponseException
     */
    public function changeNum(int $userId, int $cartId, int $cartNum): array
    {
        $cart = $this->getUserCart($userId, $cartId);
        if (trim((string) ($cart['promotion_code'] ?? '')) !== '') {
            throw new AdminResponseException('请在活动详情重新选择抢购数量');
        }
        $orderType = (int) $cart['order_type'];
        $cartNum = $this->normalizeCartNum($cartNum, $orderType, true);
        $resource = $this->getAvailableResource((int) $cart['goods_id'], $orderType);
        $sku = $this->getProductSku($resource, (string) $cart['product_attr_unique'], $orderType);

        $this->assertNotPurchased($userId, (int) $cart['goods_id'], $orderType);
        $this->assertStock($resource, $sku, $cartNum, $orderType);

        $this->update($cartId, [
            'cart_num' => $cartNum,
            'merchant_id' => $this->normalizeMerchantId($resource, $orderType),
            'product_attr_unique' => $this->isGoodsOrderType($orderType) ? (string) ($cart['product_attr_unique'] ?? '') : '',
            'status' => 1,
        ]);

        return $this->getCartDetail($userId, $cartId);
    }

    /**
     * 修改购物车商品规格
     *
     * @param int $userId 当前业务用户编号。
     * @param int $cartId 当前用户所属购物车记录编号。
     * @param string $productAttrUnique 商品规格唯一标识；直播立即购买可使用内部商品标记。
     * @return array 规格更新或合并后的购物车商品详情。
     * @throws AdminResponseException
     */
    public function changeSku(int $userId, int $cartId, string $productAttrUnique): array
    {
        $cart = $this->getUserCart($userId, $cartId);
        if (trim((string) ($cart['promotion_code'] ?? '')) !== '') {
            throw new AdminResponseException('请在活动详情重新选择抢购规格');
        }
        $orderType = (int) $cart['order_type'];
        if (!$this->isGoodsOrderType($orderType)) {
            throw new AdminResponseException('该商品不支持切换规格');
        }

        $resource = $this->getAvailableResource((int) $cart['goods_id'], $orderType);
        $sku = $this->getProductSku($resource, $productAttrUnique, $orderType);
        $productAttrUnique = $this->normalizeProductAttrUnique($productAttrUnique, $sku, $orderType);
        if ($productAttrUnique === '') {
            throw new AdminResponseException('请选择商品规格');
        }

        $cartNum = (int) $cart['cart_num'];
        $this->assertStock($resource, $sku, $cartNum, $orderType);

        if ((string) ($cart['product_attr_unique'] ?? '') === $productAttrUnique) {
            return $this->getCartDetail($userId, $cartId);
        }

        return $this->transaction(function () use ($userId, $cartId, $cart, $orderType, $productAttrUnique, $cartNum) {
            $exists = $this->getModel()
                ->where('user_id', $userId)
                ->where('goods_id', (int) $cart['goods_id'])
                ->where('order_type', $orderType)
                ->where('product_attr_unique', $productAttrUnique)
                ->where('is_pay', 0)
                ->where('is_new', (int) $cart['is_new'])
                ->where('id', '<>', $cartId)
                ->find();

            if ($exists) {
                $nextCartNum = (int) $exists['cart_num'] + $cartNum;
                $resource = $this->getAvailableResource((int) $cart['goods_id'], $orderType);
                $sku = $this->getProductSku($resource, $productAttrUnique, $orderType);
                $this->assertStock($resource, $sku, $nextCartNum, $orderType);
                $this->update((int) $exists['id'], [
                    'cart_num' => $nextCartNum,
                    'merchant_id' => $this->normalizeMerchantId($resource, $orderType),
                    'product_attr_unique' => $productAttrUnique,
                    'status' => 1,
                ]);
                $this->getModel()->where('id', $cartId)->delete();

                return $this->getCartDetail($userId, (int) $exists['id']);
            }

            $resource = $this->getAvailableResource((int) $cart['goods_id'], $orderType);
            $this->update($cartId, [
                'merchant_id' => $this->normalizeMerchantId($resource, $orderType),
                'product_attr_unique' => $productAttrUnique,
                'status' => 1,
            ]);

            return $this->getCartDetail($userId, $cartId);
        });
    }

    /**
     * 删除购物车商品
     *
     * @param int $userId 当前业务用户编号。
     * @param array $cartIds 当前用户选择结算或操作的购物车编号列表。
     * @return int 实际删除的未支付购物车记录数量。
     */
    public function deleteCart(int $userId, array $cartIds): int
    {
        $cartIds = $this->normalizeIdList($cartIds);
        if ($cartIds === []) {
            return 0;
        }

        return (int) $this->getModel()
            ->where('user_id', $userId)
            ->where('is_pay', 0)
            ->whereIn('id', $cartIds)
            ->delete();
    }

    /**
     * 标记购物车已下单
     *
     * @param int $userId 当前业务用户编号。
     * @param array $cartIds 当前用户选择结算或操作的购物车编号列表。
     * @return void 无返回值；仅标记选中购物车已下单。
     */
    public function markOrdered(int $userId, array $cartIds): void
    {
        $cartIds = $this->normalizeIdList($cartIds);
        if ($cartIds === []) {
            return;
        }

        $this->getModel()
            ->where('user_id', $userId)
            ->whereIn('id', $cartIds)
            ->update([
                'is_pay' => 1,
                'status' => 1,
            ]);
    }

    /**
     * 结算预览
     *
     * @param int $userId 当前业务用户编号。
     * @param array $cartIds 当前用户选择结算或操作的购物车编号列表。
     * @param bool $isNew 是否仅结算立即购买的临时购物车。
     * @param bool $withAmountPreview 是否补充服务端金额和优惠预览。
     * @param array $pluginPayload 按插件编码隔离的扩展入参，需经服务端重新校验。
     * @return array 包含结算规则、商户分组、失效项及可选金额预览的数据。
     * @throws AdminResponseException
     */
    public function getSettlementPreview(
        int $userId,
        array $cartIds = [],
        bool $isNew = false,
        bool $withAmountPreview = true,
        array $pluginPayload = []
    ): array
    {
        $query = $this->getModel()
            ->where('user_id', $userId)
            ->where('is_pay', 0)
            ->order('id desc');

        if ($cartIds !== []) {
            $query->whereIn('id', $this->normalizeIdList($cartIds));
        } else {
            $query->where('is_new', $isNew ? 1 : 0);
        }

        $cartList = $query->select()->toArray();
        if ($cartList === []) {
            throw new AdminResponseException('未找到可结算的购物车商品');
        }
        $promotionItems = array_filter($cartList, static fn (array $cart): bool => trim((string) ($cart['promotion_code'] ?? '')) !== '');
        if ($promotionItems !== [] && (count($cartList) !== 1 || (int) ($cartList[0]['is_new'] ?? 0) !== 1)) {
            throw new AdminResponseException('活动商品必须单独立即抢购，不能混合结算');
        }
        if ($isNew) {
            $this->assertImmediateCheckoutNotExpired($cartList);
        }

        $result = $this->buildGroupedResult($cartList);
        if ($result['invalid_list'] !== []) {
            throw new AdminResponseException('购物车中存在失效商品，请先处理后再结算', 0, null, [
                'invalid_list' => $result['invalid_list'],
            ]);
        }

        $pluginCalculation = app()->make(PluginOrderQuoteService::class)->calculate(
            'cart.calculating',
            $userId,
            $result['groups'] ?? [],
            ['surface' => 'mobile', 'plugin_payload' => $pluginPayload]
        );
        $result = app()->make(PluginOrderQuoteService::class)->applyToSettlement($result, $pluginCalculation);

        return $withAmountPreview ? $this->appendSettlementAmountPreview($userId, $result, $pluginPayload) : $result;
    }

    /**
     * 校验立即购买临时购物车是否仍在下单有效期内。
     *
     * @param array<int, array<string, mixed>> $cartList 待校验或组装的购物车记录列表。
     * @return void 无返回值；临时购物车超时或不属于有效下单窗口时抛出异常。
     * @throws AdminResponseException
     */
    protected function assertImmediateCheckoutNotExpired(array $cartList): void
    {
        $minutes = max(1, (int) db_config(OrderConfigKeyEnum::UNPAID_CANCEL_MINUTES, 30));
        $now = time();

        foreach ($cartList as $cart) {
            if ((int)($cart['is_new'] ?? 0) !== 1) {
                continue;
            }

            $createdAt = strtotime((string)($cart['create_time'] ?? ''));
            if ($createdAt > 0 && $createdAt + $minutes * 60 <= $now) {
                throw new AdminResponseException('支付时间已过期，请重新下单或联系客服');
            }
        }
    }

    /**
     * 补充结算金额与优惠券预览。
     *
     * @param int $userId 用户ID
     * @param array $settlement 购物车结算分组数据
     * @param array $pluginPayload 按插件编码隔离的扩展入参，需经服务端重新校验。
     * @return array 追加服务端金额及优惠选择预览后的结算数据。
     * @throws AdminResponseException
     */
    protected function appendSettlementAmountPreview(int $userId, array $settlement, array $pluginPayload = []): array
    {
        $rule = $settlement['settlement_rule'] ?? [];
        $totalAmount = (float) ($settlement['summary']['total_amount'] ?? 0);
        $defaultSummary = [
            'goods_amount' => (float) bcadd((string) $totalAmount, '0', 2),
            'coupon_amount' => 0.0,
            'discount_amount' => 0.0,
            'pay_amount' => (float) bcadd((string) $totalAmount, '0', 2),
        ];
        $defaultCoupon = [
            'available_count' => 0,
            'selected_coupon_id' => 0,
            'coupon_amount' => 0.0,
            'platform_coupon_list' => [],
            'merchant_coupon_list' => [],
            'platform_coupon_count' => 0,
            'merchant_coupon_count' => 0,
        ];

        if (!($rule['can_submit'] ?? false)) {
            $settlement['summary'] = array_merge($settlement['summary'] ?? [], $defaultSummary);
            $settlement['coupon'] = $defaultCoupon;
            return $settlement;
        }

        try {
            $confirm = app()->make(OrderAmountService::class)->buildConfirmAmount($userId, $settlement, [
                'auto_select_coupon' => true,
                'plugin_payload' => $pluginPayload,
            ]);
        } catch (AdminResponseException $e) {
            if ($e->getMessage() !== '请选择收货地址') {
                throw $e;
            }

            $settlement['summary'] = array_merge($settlement['summary'] ?? [], $defaultSummary, [
                'postage' => 0.0,
                'freight_message' => '选择收货地址后计算运费',
            ]);
            $settlement['coupon'] = $defaultCoupon;
            return $settlement;
        }
        $summary = $confirm['summary'] ?? $defaultSummary;
        $coupon = $confirm['coupon'] ?? $defaultCoupon;
        $coupon['available_count'] = (int) ($coupon['platform_coupon_count'] ?? 0) + (int) ($coupon['merchant_coupon_count'] ?? 0);

        $settlement['summary'] = array_merge($settlement['summary'] ?? [], $summary);
        $settlement['coupon'] = array_merge($defaultCoupon, $coupon);
        // 结算接口直接返回已计算会员价的商品分组，购物车可与确认页显示同一单价口径。
        $settlement['groups'] = $confirm['groups'] ?? ($settlement['groups'] ?? []);
        $settlement['confirm'] = $confirm;

        return $settlement;
    }

    /**
     * 获取购物车详情
     *
     * @param int $userId 当前业务用户编号。
     * @param int $cartId 当前用户所属购物车记录编号。
     * @return array 当前购物车项的有效商品资料或失效状态。
     * @throws AdminResponseException
     */
    public function getCartDetail(int $userId, int $cartId): array
    {
        $cart = $this->getUserCart($userId, $cartId)->toArray();
        $result = $this->buildGroupedResult([$cart]);

        if ($result['groups'] !== []) {
            return $result['groups'][0]['items'][0];
        }

        if ($result['invalid_list'] !== []) {
            return $result['invalid_list'][0];
        }

        throw new AdminResponseException('购物车商品不存在');
    }

    /**
     * 使用当前已加载的商品上下文构建购物车响应，避免加购成功后重新查询购物车和商品。
     *
     * @param array $cart 已加载的购物车记录及其结算身份。
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return array 使用已加载上下文组装的购物车商品响应。
     */
    protected function buildCartItemResponse(
        array $cart,
        array $resource,
        ?array $sku,
        int $orderType
    ): array {
        $merchantId = (int)($cart['merchant_id'] ?? 0);
        $merchantNameMap = $merchantId > 0
            ? app()->make(\app\common\service\merchant\MerchantService::class)->getNameMapByIds([$merchantId])
            : [];

        $liveGoods = $this->getActiveLiveGoodsByCart($cart);

        return $this->formatCartItem($cart, $resource, $sku, $merchantNameMap, $orderType, '', $liveGoods);
    }

    /**
     * 获取用户购物车记录
     *
     * @param int $userId 当前业务用户编号。
     * @param int $cartId 当前用户所属购物车记录编号。
     * @return \think\Model 已校验用户归属的购物车模型。
     * @throws AdminResponseException
     */
    protected function getUserCart(int $userId, int $cartId)
    {
        $cart = $this->get([
            'id' => $cartId,
            'user_id' => $userId,
            'is_pay' => 0,
        ]);

        if (!$cart) {
            throw new AdminResponseException('购物车商品不存在');
        }

        return $cart;
    }

    /**
     * 获取有效资源
     *
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return array 与订单类型对应的可购买商品、内容或专题资料。
     * @throws AdminResponseException
     */
    protected function getAvailableResource(int $goodsId, int $orderType): array
    {
        return match ($orderType) {
            Cart::ORDER_TYPE_GOODS => $this->getAvailableGoods($goodsId),
            Cart::ORDER_TYPE_CONTENT => $this->getAvailableContent($goodsId),
            Cart::ORDER_TYPE_SPECIAL => $this->getAvailableSpecial($goodsId),
            default => throw new AdminResponseException('不支持的订单类型'),
        };
    }

    /**
     * 获取有效店铺商品（上架 + 审核通过）
     *
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @return array 审核通过且已上架的普通店铺商品资料。
     * @throws AdminResponseException
     */
    protected function getAvailableGoods(int $goodsId): array
    {
        return app()->make(\app\common\service\store\StoreProductService::class)->findAvailableById($goodsId);
    }

    /**
     * 获取有效内容
     *
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @return array 当前有效且可购买的内容资料。
     * @throws AdminResponseException
     */
    protected function getAvailableContent(int $goodsId): array
    {
        return app()->make(\app\common\service\content\ArticleService::class)->findAvailableById($goodsId);
    }

    /**
     * 获取有效专题
     *
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @return array 当前有效且可购买的专题资料。
     * @throws AdminResponseException
     */
    protected function getAvailableSpecial(int $goodsId): array
    {
        return app()->make(\app\common\service\content\SpecialService::class)->findAvailableById($goodsId);
    }

    /**
     * 获取商品规格
     *
     * 按 product_attr_unique 中的规格唯一标识 uniqid 解析店铺商品规格；为空时返回默认规格。
     * 规格隐藏（status!=1）时返回空值，由调用方按"商品规格已失效"处理。
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param string $productAttrUnique 商品规格唯一标识；直播立即购买可使用内部商品标记。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return ?array 有效商品规格资料；虚拟资源或已隐藏规格返回空值。
     * @throws AdminResponseException
     */
    protected function getProductSku(array $resource, string $productAttrUnique = '', int $orderType = Cart::ORDER_TYPE_GOODS): ?array
    {
        if ((string)($resource['plugin_code'] ?? '') === 'hotel') {
            if ($productAttrUnique !== '') {
                throw new AdminResponseException('酒店预订不使用商品 SKU');
            }
            return null;
        }
        if (!$this->isGoodsOrderType($orderType)) {
            return null;
        }

        $productId = (int) ($resource['id'] ?? 0);
        $skuService = app()->make(\app\common\service\store\StoreProductSkuService::class);

        $sku = $productAttrUnique !== ''
            ? $skuService->resolveSkuByUnique($productId, $productAttrUnique)
            : $skuService->getDefaultSku($productId);

        if ($sku === null) {
            return null;
        }

        return $sku;
    }

    /**
     * 校验库存
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param int $cartNum 当前商品的购买件数。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return void 无返回值；实物规格库存不足时抛出业务异常。
     * @throws AdminResponseException
     */
    protected function assertStock(array $resource, ?array $sku, int $cartNum, int $orderType): void
    {
        if (!$this->isGoodsOrderType($orderType)) {
            return;
        }

        $stock = $this->resolveStock($resource, $sku, $orderType);
        if ($stock !== null && $stock < $cartNum) {
            throw new AdminResponseException('商品库存不足');
        }
    }

    /**
     * 校验是否已购买
     *
     * @param int $userId 当前业务用户编号。
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return void 无返回值；用户已拥有虚拟资源权益时抛出异常。
     * @throws AdminResponseException
     */
    protected function assertNotPurchased(int $userId, int $goodsId, int $orderType): void
    {
        if (!$this->isVirtualOrderType($orderType)) {
            return;
        }

        $resourceIdsByType = [
            Cart::ORDER_TYPE_GOODS => [],
            Cart::ORDER_TYPE_CONTENT => [],
            Cart::ORDER_TYPE_SPECIAL => [],
        ];
        $resourceIdsByType[$orderType] = [$goodsId];

        $purchasedMap = $this->loadPurchasedMap($userId, $resourceIdsByType);
        if (isset($purchasedMap[$this->buildPurchaseKey($orderType, $goodsId)])) {
            throw new AdminResponseException($this->getRepeatPurchaseMessage($orderType));
        }
    }

    /**
     * 构建购物车分组结果
     *
     * @param array $cartList 待校验或组装的购物车记录列表。
     * @return array 购物车汇总、商户分组、失效项及结算冲突规则。
     */
    protected function buildGroupedResult(array $cartList): array
    {
        if ($cartList === []) {
            return [
                'summary' => [
                    'total_count' => 0,
                    'checked_count' => 0,
                    'invalid_count' => 0,
                    'total_amount' => 0.0,
                ],
                'groups' => [],
                'invalid_list' => [],
                'settlement_rule' => [
                    'merchant_limit' => self::MAX_SETTLEMENT_MERCHANTS,
                    'merchant_ids' => [],
                    'merchant_count' => 0,
                    'order_types' => [],
                    'order_type_count' => 0,
                    'can_submit' => false,
                    'conflict_type' => '',
                    'message' => '购物车为空',
                ],
            ];
        }

        $context = $this->loadCartContext($cartList);
        $resourceMap = $context['resource_map'];
        $skuMap = $context['sku_map'];
        $defaultSkuMap = $context['default_sku_map'];
        $merchantNameMap = $context['merchant_name_map'];
        $purchasedMap = $context['purchased_map'];
        $liveGoodsMap = $context['live_goods_map'];

        $groups = [];
        $invalidList = [];
        $checkedCount = 0;
        $totalAmount = 0.0;
        $validMerchantIds = [];
        $validOrderTypes = [];
        $statusUpdates = [];

        foreach ($cartList as $cart) {
            $orderType = (int) ($cart['order_type'] ?? Cart::ORDER_TYPE_GOODS);
            $goodsId = (int) ($cart['goods_id'] ?? 0);
            $resource = $resourceMap[$orderType][$goodsId] ?? null;
            $sku = null;
            $invalidReason = '';
            $liveGoods = $liveGoodsMap[(int) ($cart['id'] ?? 0)] ?? null;

            if ($resource === null) {
                $invalidReason = $this->getResourceMissingMessage($orderType);
            } else {
                $invalidReason = $this->resolveResourceInvalidReason($cart, $resource, $orderType, $purchasedMap, $liveGoods);

                if ($invalidReason === '' && $this->isGoodsOrderType($orderType)) {
                    if ((string) ($cart['product_attr_unique'] ?? '') !== '') {
                        $sku = $skuMap[$goodsId][(string) $cart['product_attr_unique']] ?? null;
                        if ($sku === null) {
                            $invalidReason = '商品规格不存在';
                        }
                    } else {
                        $sku = $defaultSkuMap[$goodsId] ?? null;
                    }

                    if ($invalidReason === '' && $sku !== null && (int) ($sku['status'] ?? 0) !== 1) {
                        $invalidReason = '商品规格已失效';
                    }

                    if ($invalidReason === '') {
                        $stock = $this->resolveStock($resource, $sku, $orderType);
                        if ($stock !== null && $stock < (int) $cart['cart_num']) {
                            $invalidReason = '库存不足';
                        }
                    }
                }
            }

            $item = $this->formatCartItem($cart, $resource, $sku, $merchantNameMap, $orderType, $invalidReason, $liveGoods);
            $nextStatus = $invalidReason === '' ? 1 : 0;
            if ((int) $cart['status'] !== $nextStatus) {
                $statusUpdates[(int) $cart['id']] = $nextStatus;
            }

            if ($invalidReason !== '') {
                $invalidList[] = $item;
                continue;
            }

            $merchantId = (int) $item['merchant_id'];
            $validMerchantIds[$merchantId] = $merchantId;
            $validOrderTypes[$orderType] = $orderType;

            if (!isset($groups[$merchantId])) {
                $groups[$merchantId] = [
                    'merchant_id' => $merchantId,
                    'merchant_name' => $item['merchant_name'],
                    'merchant_type' => $item['merchant_type'],
                    'checked_count' => 0,
                    'total_amount' => 0.0,
                    'cart_ids' => [],
                    'items' => [],
                ];
            }

            $groups[$merchantId]['checked_count']++;
            $groups[$merchantId]['total_amount'] = (float) bcadd((string) $groups[$merchantId]['total_amount'], (string) $item['line_total'], 2);
            $groups[$merchantId]['cart_ids'][] = (int) $item['id'];
            $groups[$merchantId]['items'][] = $item;
            $checkedCount++;
            $totalAmount = (float) bcadd((string) $totalAmount, (string) $item['line_total'], 2);
        }

        foreach ($statusUpdates as $cartId => $status) {
            $this->update($cartId, ['status' => $status]);
        }

        $groups = array_values($groups);
        usort($groups, function (array $left, array $right) {
            if ($left['merchant_id'] === 0) {
                return -1;
            }
            if ($right['merchant_id'] === 0) {
                return 1;
            }
            return $left['merchant_id'] <=> $right['merchant_id'];
        });

        return [
            'summary' => [
                'total_count' => count($cartList),
                'checked_count' => $checkedCount,
                'invalid_count' => count($invalidList),
                'total_amount' => (float) bcadd((string) $totalAmount, '0', 2),
            ],
            'groups' => $groups,
            'invalid_list' => $invalidList,
            'settlement_rule' => $this->buildSettlementRule(array_values($validMerchantIds), array_values($validOrderTypes)),
        ];
    }

    /**
     * 加载购物车上下文
     *
     * @param array $cartList 待校验或组装的购物车记录列表。
     * @return array 原资源、规格、商户、已购权益和直播商品的批量映射。
     */
    protected function loadCartContext(array $cartList): array
    {
        $resourceIdsByType = [
            Cart::ORDER_TYPE_GOODS => [],
            Cart::ORDER_TYPE_CONTENT => [],
            Cart::ORDER_TYPE_SPECIAL => [],
        ];
        foreach ($cartList as $item) {
            $orderType = (int) ($item['order_type'] ?? Cart::ORDER_TYPE_GOODS);
            $goodsId = (int) ($item['goods_id'] ?? 0);
            if ($goodsId > 0 && isset($resourceIdsByType[$orderType])) {
                $resourceIdsByType[$orderType][] = $goodsId;
            }
        }

        foreach ($resourceIdsByType as $orderType => $ids) {
            $resourceIdsByType[$orderType] = array_values(array_unique(array_filter(array_map('intval', $ids))));
        }

        $resourceMap = [
            Cart::ORDER_TYPE_GOODS => [],
            Cart::ORDER_TYPE_CONTENT => [],
            Cart::ORDER_TYPE_SPECIAL => [],
        ];
        $skuMap = [];
        $defaultSkuMap = [];
        $merchantNameMap = [];
        $merchantIds = [];

        // 商品订单：加载店铺商品及对应规格映射（skuMap 按商品标识和 uniqid 建键）。
        if ($resourceIdsByType[Cart::ORDER_TYPE_GOODS] !== []) {
            $storeProductService = app()->make(\app\common\service\store\StoreProductService::class);
            $resourceMap[Cart::ORDER_TYPE_GOODS] = $storeProductService->getMapByIds($resourceIdsByType[Cart::ORDER_TYPE_GOODS]);

            $skuService = app()->make(\app\common\service\store\StoreProductSkuService::class);
            foreach ($resourceMap[Cart::ORDER_TYPE_GOODS] as $product) {
                $productId = (int) ($product['id'] ?? 0);
                $skus = $skuService->getSkuList($productId);
                $defaultSku = null;
                foreach ($skus as $sku) {
                    $skuMap[$productId][(string) ($sku['uniqid'] ?? '')] = $sku;
                    if ($defaultSku === null && (int) ($sku['is_default'] ?? 0) === 1) {
                        $defaultSku = $sku;
                    }
                }
                if ($defaultSku === null && $skus !== []) {
                    $defaultSku = $skus[0];
                }
                if ($defaultSku !== null) {
                    $defaultSkuMap[$productId] = $defaultSku;
                }

                $merchantId = $this->normalizeMerchantId($product, Cart::ORDER_TYPE_GOODS);
                if ($merchantId > 0) {
                    $merchantIds[] = $merchantId;
                }
            }
        }

        if ($resourceIdsByType[Cart::ORDER_TYPE_CONTENT] !== []) {
            $resourceMap[Cart::ORDER_TYPE_CONTENT] = app()->make(\app\common\service\content\ArticleService::class)->getMapByIds($resourceIdsByType[Cart::ORDER_TYPE_CONTENT]);
            foreach ($resourceMap[Cart::ORDER_TYPE_CONTENT] as $article) {
                $merchantId = $this->normalizeMerchantId($article, Cart::ORDER_TYPE_CONTENT);
                if ($merchantId > 0) {
                    $merchantIds[] = $merchantId;
                }
            }
        }

        if ($resourceIdsByType[Cart::ORDER_TYPE_SPECIAL] !== []) {
            $resourceMap[Cart::ORDER_TYPE_SPECIAL] = app()->make(\app\common\service\content\SpecialService::class)->getMapByIds($resourceIdsByType[Cart::ORDER_TYPE_SPECIAL]);
            foreach ($resourceMap[Cart::ORDER_TYPE_SPECIAL] as $special) {
                $merchantId = $this->normalizeMerchantId($special, Cart::ORDER_TYPE_SPECIAL);
                if ($merchantId > 0) {
                    $merchantIds[] = $merchantId;
                }
            }
        }

        if ($merchantIds !== []) {
            $merchantNameMap = app()->make(\app\common\service\merchant\MerchantService::class)->getNameMapByIds(array_values(array_unique($merchantIds)));
        }

        $userId = (int) ($cartList[0]['user_id'] ?? 0);

        return [
            'resource_map' => $resourceMap,
            'sku_map' => $skuMap,
            'default_sku_map' => $defaultSkuMap,
            'merchant_name_map' => $merchantNameMap,
            'purchased_map' => $userId > 0 ? $this->loadPurchasedMap($userId, $resourceIdsByType) : [],
            'live_goods_map' => $this->loadActiveLiveGoodsMapByCartList($cartList),
        ];
    }

    /**
     * 加载直播间立即购买商品快照，按购物车 ID 建立映射。
     *
     * @param array $cartList 待校验或组装的购物车记录列表。
     * @return array 以购物车编号为键的当前有效直播商品快照。
     */
    protected function loadActiveLiveGoodsMapByCartList(array $cartList): array
    {
        $liveGoodsIds = [];
        $cartLiveGoodsIds = [];
        foreach ($cartList as $cart) {
            $cartId = (int) ($cart['id'] ?? 0);
            $liveGoodsId = $this->extractLiveGoodsIdFromAttrUnique((string) ($cart['product_attr_unique'] ?? ''));
            if ($cartId <= 0 || $liveGoodsId <= 0) {
                continue;
            }

            $cartLiveGoodsIds[$cartId] = $liveGoodsId;
            $liveGoodsIds[] = $liveGoodsId;
        }

        if ($liveGoodsIds === []) {
            return [];
        }

        $liveGoodsMap = app()
            ->make(\app\common\service\live\LiveGoodsService::class)
            ->getActiveMapByIds($liveGoodsIds);
        $result = [];
        foreach ($cartLiveGoodsIds as $cartId => $liveGoodsId) {
            if (isset($liveGoodsMap[$liveGoodsId])) {
                $result[$cartId] = $liveGoodsMap[$liveGoodsId];
            }
        }

        return $result;
    }

    /**
     * 解析立即购买使用的商品属性唯一值。
     *
     * 普通商品维持规格唯一标识 uniqid；直播商品在立即购买阶段保留 `live:{id}` 内部标记，
     * 便于结算时还原直播商品快照和直播价。
     *
     * @param string $productAttrUnique 商品规格唯一标识；直播立即购买可使用内部商品标记。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param bool $allowLiveGoodsMarker 是否保留直播立即购买的内部商品标记。
     * @return string 规范化后的规格唯一标识或允许保留的直播内部标记。
     */
    protected function normalizeProductAttrUnique(
        string $productAttrUnique,
        ?array $sku,
        int $orderType,
        bool $allowLiveGoodsMarker = false
    ): string {
        if ($this->isGoodsOrderType($orderType)) {
            return (string) ($sku['uniqid'] ?? $productAttrUnique);
        }

        if ($allowLiveGoodsMarker && $this->isLiveGoodsAttrUnique($productAttrUnique)) {
            return $productAttrUnique;
        }

        return '';
    }

    /**
     * 判断是否为直播商品内部标记。
     *
     * @param string $productAttrUnique 商品规格唯一标识；直播立即购买可使用内部商品标记。
     * @return bool 当前规格值是否为有效的直播商品内部标记。
     */
    protected function isLiveGoodsAttrUnique(string $productAttrUnique): bool
    {
        return preg_match('/^' . preg_quote(self::LIVE_GOODS_ATTR_PREFIX, '/') . '\d+$/', $productAttrUnique) === 1;
    }

    /**
     * 提取直播商品 ID。
     *
     * @param string $productAttrUnique 商品规格唯一标识；直播立即购买可使用内部商品标记。
     * @return int 直播商品编号；格式不符合内部标记时为零。
     */
    protected function extractLiveGoodsIdFromAttrUnique(string $productAttrUnique): int
    {
        if (!preg_match('/^' . preg_quote(self::LIVE_GOODS_ATTR_PREFIX, '/') . '(\d+)$/', $productAttrUnique, $matches)) {
            return 0;
        }

        return max(0, (int) ($matches[1] ?? 0));
    }

    /**
     * 将直播商品类型映射为统一订单类型。
     *
     * @param string $goodsType 直播商品的资源类型编码。
     * @return int 直播资源对应的订单类型；不支持的类型为零。
     */
    protected function resolveLiveGoodsOrderType(string $goodsType): int
    {
        return match ($goodsType) {
            'article' => Cart::ORDER_TYPE_CONTENT,
            'special' => Cart::ORDER_TYPE_SPECIAL,
            default => 0,
        };
    }

    /**
     * 解析直播商品价格。
     *
     * @param ?array $liveGoods 当前有效的直播商品快照；无直播关联时为空。
     * @return ?float 当前直播销售价，单位为元；无有效快照时为空。
     */
    protected function resolveLiveGoodsPrice(?array $liveGoods): ?float
    {
        if (!is_array($liveGoods) || (int) ($liveGoods['id'] ?? 0) <= 0) {
            return null;
        }

        return (float) bcadd((string) ($liveGoods['goods_price'] ?? 0), '0', 2);
    }

    /**
     * 获取单条购物车绑定的直播商品快照。
     *
     * @param array $cart 已加载的购物车记录及其结算身份。
     * @return ?array 当前购物车绑定的有效直播商品快照；无关联时为空。
     */
    protected function getActiveLiveGoodsByCart(array $cart): ?array
    {
        $cartId = (int) ($cart['id'] ?? 0);
        if ($cartId <= 0) {
            return null;
        }

        $liveGoodsMap = $this->loadActiveLiveGoodsMapByCartList([$cart]);
        return $liveGoodsMap[$cartId] ?? null;
    }

    /**
     * 加载已购资源映射
     *
     * @param int $userId 当前业务用户编号。
     * @param array $resourceIdsByType 按订单资源类型分组的资源编号列表。
     * @return array 按资源类型及编号建立的当前用户已购权益映射。
     */
    protected function loadPurchasedMap(int $userId, array $resourceIdsByType): array
    {
        $contentIds = $resourceIdsByType[Cart::ORDER_TYPE_CONTENT] ?? [];
        $specialIds = $resourceIdsByType[Cart::ORDER_TYPE_SPECIAL] ?? [];
        if ($contentIds === [] && $specialIds === []) {
            return [];
        }

        return app()->make(\app\common\service\order\UserPurchaseRecordService::class)->getPurchasedMap($userId, [
            Cart::ORDER_TYPE_CONTENT => $contentIds,
            Cart::ORDER_TYPE_SPECIAL => $specialIds,
        ]);
    }

    /**
     * 格式化购物车项
     *
     * @param array $cart 已加载的购物车记录及其结算身份。
     * @param ?array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param array $merchantNameMap 以商户编号为键的店铺名称映射。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param string $invalidReason 当前购物车商品不可结算的原因。
     * @param ?array $liveGoods 当前有效的直播商品快照；无直播关联时为空。
     * @return array 包含原商品归属、规格、价格、库存及活动身份的购物车展示项。
     */
    protected function formatCartItem(
        array $cart,
        ?array $resource,
        ?array $sku,
        array $merchantNameMap,
        int $orderType,
        string $invalidReason = '',
        ?array $liveGoods = null
    ): array
    {
        $merchantId = $resource
            ? $this->normalizeMerchantId($resource, $orderType)
            : (int) ($cart['merchant_id'] ?? 0);
        $price = $invalidReason === '' ? $this->resolveSalePrice($resource ?? [], $sku, $liveGoods) : 0.0;
        $lineTotal = $invalidReason === '' ? (float) bcmul((string) $price, (string) $cart['cart_num'], 2) : 0.0;

        return [
            'id' => (int) $cart['id'],
            'cart_id' => (int) $cart['id'],
            'user_id' => (int) $cart['user_id'],
            'merchant_id' => $merchantId,
            'merchant_name' => $merchantId > 0 ? ($merchantNameMap[$merchantId] ?? '商户店铺') : '平台自营',
            'merchant_type' => $merchantId > 0 ? 'merchant' : 'platform',
            'order_type' => $orderType,
            'resource_type' => $this->getResourceTypeName($orderType),
            'resource_type_text' => $this->getResourceTypeText($orderType),
            'goods_id' => (int) $cart['goods_id'],
            'goods_name' => $this->resolveResourceName($resource ?? [], $liveGoods),
            'goods_image' => $this->resolveImageUrl($this->resolveImage($resource ?? [], $sku, $liveGoods)),
            'cart_num' => (int) $cart['cart_num'],
            'product_attr_unique' => $this->isGoodsOrderType($orderType) ? (string) ($cart['product_attr_unique'] ?? '') : '',
            'sku_id' => (int) ($sku['id'] ?? 0),
            'sku_name' => $this->resolveSkuName($sku),
            'price' => (float) bcadd((string) $price, '0', 2),
            'original_price' => (float) bcadd((string) $this->resolveOriginalPrice($resource ?? [], $sku, $liveGoods), '0', 2),
            'line_total' => (float) bcadd((string) $lineTotal, '0', 2),
            'stock' => $this->resolveStock($resource ?? [], $sku, $orderType),
            'goods_type' => $this->isGoodsOrderType($orderType)
                ? (int) ($resource['goods_type'] ?? 1)
                : 0,
            'is_free_shipping' => $this->isGoodsOrderType($orderType)
                ? (int) ($resource['is_free_shipping'] ?? 0)
                : 0,
            'freight_template_id' => $this->isGoodsOrderType($orderType)
                ? (int) ($resource['freight_template_id'] ?? 0)
                : 0,
            'weight' => (float) ($sku['weight'] ?? 0),
            'plugin_code' => app()->make(OrderPromotionService::class)->effectiveCode($cart, $resource ?? []),
            'promotion_code' => trim((string) ($cart['promotion_code'] ?? '')),
            'plugin_payload' => is_array($cart['plugin_payload_json'] ?? null)
                ? $cart['plugin_payload_json']
                : $this->decodeJsonPayload($cart['plugin_payload_json'] ?? []),
            'is_new' => (int) $cart['is_new'],
            'is_pay' => (int) $cart['is_pay'],
            'status' => $invalidReason === '' ? 1 : 0,
            'checked' => $invalidReason === '',
            'invalid_reason' => $invalidReason,
            'product' => $resource ?? [],
            'sku' => $sku ?? [],
        ];
    }

    /**
     * 规范化加入购物车时的插件载荷，只保留商品归属插件的命名空间。
     *
     * @param array<string, mixed> $resource 购物车关联的原商品、内容或专题资料。
     * @param array<string, mixed> $payload 待处理的插件载荷。
     * @return array<string, mixed> 仅保留原商品所属插件命名空间的规范化载荷。
     */
    protected function normalisePluginPayload(array $resource, array $payload): array
    {
        $pluginCode = strtolower(trim((string)($resource['plugin_code'] ?? '')));
        if ($pluginCode === '') {
            return [];
        }
        if (!app()->make(PluginRegistry::class)->isPluginEnabled($pluginCode)) {
            throw new AdminResponseException('插件未启用，无法加入插件商品');
        }
        foreach ($payload as $namespace => $value) {
            if (strtolower(trim((string)$namespace)) !== $pluginCode) {
                throw new AdminResponseException('插件载荷命名空间无效：' . (string)$namespace);
            }
            if (!is_array($value)) {
                throw new AdminResponseException('插件载荷格式错误：' . $pluginCode);
            }
            return [$pluginCode => $value];
        }
        return [];
    }

    /**
     * 解析已保存的插件载荷，非数组结构统一视为空载荷。
     *
     * @param mixed $value 待解析的原始字段值或筛选值。
     * @return array<string, mixed> 已解码的数组载荷；非法或非数组结构返回空数组。
     */
    protected function decodeJsonPayload(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return is_array($value) ? $value : [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 构建结算规则
     *
     * @param array $merchantIds 参与结算的商户编号列表。
     * @param array $orderTypes 参与结算或展示的订单类型列表。
     * @return array 结算商户数、订单类型和是否可提交的冲突规则。
     */
    protected function buildSettlementRule(array $merchantIds, array $orderTypes): array
    {
        $merchantIds = array_values(array_unique(array_map('intval', $merchantIds)));
        $orderTypes = array_values(array_unique(array_map('intval', $orderTypes)));

        $conflictType = '';
        $message = '可提交';
        $canSubmit = $merchantIds !== [];

        if (count($merchantIds) > self::MAX_SETTLEMENT_MERCHANTS) {
            $conflictType = 'merchant_limit';
            $message = self::MERCHANT_LIMIT_MESSAGE;
            $canSubmit = false;
        } elseif ($merchantIds === []) {
            $message = '暂无可结算商品';
            $canSubmit = false;
        }

        return [
            'merchant_limit' => self::MAX_SETTLEMENT_MERCHANTS,
            'merchant_ids' => $merchantIds,
            'merchant_count' => count($merchantIds),
            'order_types' => $orderTypes,
            'order_type_count' => count($orderTypes),
            'can_submit' => $canSubmit,
            'conflict_type' => $conflictType,
            'message' => $message,
        ];
    }

    /**
     * 加购新商户前校验普通购物车的商户数量。
     *
     * 立即购买使用独立的 is_new 临时购物车，不占用普通购物车的商户额度。
     *
     * @param int $userId 当前业务用户编号。
     * @param int $merchantId 操作或结算所属商户编号，零表示平台。
     * @return void 无返回值；新增普通购物车商户超过限制时抛出异常。
     * @throws AdminResponseException
     */
    protected function assertMerchantLimitOnAdd(int $userId, int $merchantId): void
    {
        $merchantIds = $this->getModel()
            ->where('user_id', $userId)
            ->where('is_pay', 0)
            ->where('is_new', 0)
            ->where('status', 1)
            ->column('merchant_id');

        $merchantIds = array_values(array_unique(array_map('intval', $merchantIds)));
        if (in_array($merchantId, $merchantIds, true)) {
            return;
        }

        if (count($merchantIds) >= self::MAX_SETTLEMENT_MERCHANTS) {
            throw new AdminResponseException(self::MERCHANT_LIMIT_MESSAGE);
        }
    }

    /**
     * 解析资源失效原因
     *
     * @param array $cart 已加载的购物车记录及其结算身份。
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param array $purchasedMap 以资源类型和编号建立的已购权益映射。
     * @param ?array $liveGoods 当前有效的直播商品快照；无直播关联时为空。
     * @return string 当前资源无法购买的具体原因；可购买时返回空字符串。
     */
    protected function resolveResourceInvalidReason(
        array $cart,
        array $resource,
        int $orderType,
        array $purchasedMap,
        ?array $liveGoods = null
    ): string
    {
        if ($this->isVirtualOrderType($orderType) && (int) ($cart['cart_num'] ?? 0) !== 1) {
            return '购买数量固定为1';
        }

        $isLiveGoodsOrder = $this->isLiveGoodsAttrUnique((string) ($cart['product_attr_unique'] ?? ''));
        if ($isLiveGoodsOrder) {
            if ($liveGoods === null) {
                return '直播间商品已下架';
            }

            $liveGoodsOrderType = $this->resolveLiveGoodsOrderType((string) ($liveGoods['goods_type'] ?? ''));
            if ($liveGoodsOrderType !== $orderType || (int) ($liveGoods['goods_id'] ?? 0) !== (int) ($cart['goods_id'] ?? 0)) {
                return '直播间商品信息不正确';
            }
        }

        if ($this->isGoodsOrderType($orderType)) {
            if ((int) ($resource['status'] ?? 0) !== 1) {
                return '商品已下架';
            }

            return '';
        }

        if (isset($purchasedMap[$this->buildPurchaseKey($orderType, (int) ($cart['goods_id'] ?? 0))])) {
            return $this->getRepeatPurchaseMessage($orderType);
        }

        if ($orderType === Cart::ORDER_TYPE_CONTENT) {
            if ((int) ($resource['status'] ?? 0) !== 1) {
                return '内容已下架';
            }
            if ((int) ($resource['audit_status'] ?? 0) !== 1) {
                return '内容暂不可购买';
            }
            if (!$isLiveGoodsOrder && (float) ($resource['price'] ?? 0) <= 0) {
                return '免费内容无需购买';
            }

            return '';
        }

        if ((int) ($resource['status'] ?? 0) !== 1 || (int) ($resource['audit_status'] ?? 0) !== 1) {
            return '专题已下架';
        }
        if (!$isLiveGoodsOrder && ((int) ($resource['special_type'] ?? 0) !== 1 || (float) ($resource['price'] ?? 0) <= 0)) {
            return '免费专题无需购买';
        }

        return '';
    }

    /**
     * 规格或商品库存
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return ?int 实物规格或商品的可用库存；虚拟资源无库存时为空。
     */
    protected function resolveStock(array $resource, ?array $sku, int $orderType): ?int
    {
        if ((string)($resource['plugin_code'] ?? '') === 'hotel') {
            return null;
        }
        if (!$this->isGoodsOrderType($orderType)) {
            return null;
        }

        $value = $sku['stock'] ?? $resource['stock'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * 获取销售价
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param ?array $liveGoods 当前有效的直播商品快照；无直播关联时为空。
     * @return float 当前实际销售单价，单位为元。
     */
    protected function resolveSalePrice(array $resource, ?array $sku, ?array $liveGoods = null): float
    {
        $livePrice = $this->resolveLiveGoodsPrice($liveGoods);
        if ($livePrice !== null) {
            return $livePrice;
        }

        $price = $sku['price']
            ?? $sku['store_price']
            ?? $resource['price']
            ?? $resource['store_price']
            ?? 0;

        return (float) bcadd((string) $price, '0', 2);
    }

    /**
     * 获取原价
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param ?array $liveGoods 当前有效的直播商品快照；无直播关联时为空。
     * @return float 当前商品或规格的展示原价，单位为元。
     */
    protected function resolveOriginalPrice(array $resource, ?array $sku, ?array $liveGoods = null): float
    {
        $price = $sku['ot_price']
            ?? $resource['original_price']
            ?? $resource['ot_price']
            ?? $this->resolveSalePrice($resource, $sku);
        $livePrice = $this->resolveLiveGoodsPrice($liveGoods);
        if ($livePrice !== null && $livePrice > (float) bcadd((string) $price, '0', 2)) {
            $price = $livePrice;
        }

        return (float) bcadd((string) $price, '0', 2);
    }

    /**
     * 获取资源名称
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $liveGoods 当前有效的直播商品快照；无直播关联时为空。
     * @return string 直播快照或原商品、内容、专题的展示名称。
     */
    protected function resolveResourceName(array $resource, ?array $liveGoods = null): string
    {
        $liveTitle = trim((string) ($liveGoods['goods_title'] ?? ''));
        if ($liveTitle !== '') {
            return $liveTitle;
        }

        $name = $resource['store_name']
            ?? $resource['name']
            ?? $resource['title']
            ?? '';

        return (string) $name;
    }

    /**
     * 获取规格名称
     *
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @return string 当前规格的展示名称；未选择规格时为空字符串。
     */
    protected function resolveSkuName(?array $sku): string
    {
        if ($sku === null) {
            return '';
        }

        return (string) ($sku['sku'] ?? $sku['attr_value'] ?? $sku['title'] ?? '');
    }

    /**
     * 获取图片字段
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param ?array $sku 已加载的商品规格；虚拟资源或无效规格可为空。
     * @param ?array $liveGoods 当前有效的直播商品快照；无直播关联时为空。
     * @return string 当前商品或直播快照可展示的首张图片地址。
     */
    protected function resolveImage(array $resource, ?array $sku, ?array $liveGoods = null): string
    {
        $liveImage = trim((string) ($liveGoods['goods_cover'] ?? ''));
        if ($liveImage !== '') {
            return $liveImage;
        }

        $image = $sku['pics']
            ?? $resource['cover']
            ?? $resource['image']
            ?? $resource['pic']
            ?? $resource['pics']
            ?? '';

        if (is_array($image)) {
            return (string) ($image[0] ?? '');
        }

        $image = trim((string) $image);
        if ($image === '') {
            return '';
        }

        if (str_starts_with($image, '[') || str_starts_with($image, '{')) {
            $decoded = json_decode($image, true);
            if (is_array($decoded)) {
                return (string) ($decoded[0] ?? '');
            }
        }

        if (str_contains($image, ',')) {
            $images = array_values(array_filter(array_map('trim', explode(',', $image))));
            return (string) ($images[0] ?? '');
        }

        return $image;
    }

    /**
     * 转换图片地址
     *
     * @param string $image 待转换的商品图片地址。
     * @return string 补全或保留后的图片访问地址。
     */
    protected function resolveImageUrl(string $image): string
    {
        if ($image === '') {
            return '';
        }

        if (function_exists('get_image_url')) {
            return (string) get_image_url($image);
        }

        return $image;
    }

    /**
     * 标准化商户归属
     *
     * @param array $resource 购物车关联的原商品、内容或专题资料。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return int 规范化后的资源归属商户编号，零表示平台。
     */
    protected function normalizeMerchantId(array $resource, int $orderType): int
    {
        $merchantId = match ($orderType) {
            Cart::ORDER_TYPE_CONTENT => (int) ($resource['mer_id'] ?? 0),
            default => (int) ($resource['merchant_id'] ?? 0),
        };

        return max(0, $merchantId);
    }


    /**
     * 标准化数量
     *
     * @param int $cartNum 当前商品的购买件数。
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param bool $strictVirtual 是否拒绝虚拟资源大于一件的购买数量。
     * @return int 符合实物件数或虚拟资源固定一份规则的购买数量。
     * @throws AdminResponseException
     */
    protected function normalizeCartNum(int $cartNum, int $orderType = Cart::ORDER_TYPE_GOODS, bool $strictVirtual = true): int
    {
        if ($cartNum <= 0) {
            throw new AdminResponseException('购买数量必须大于 0');
        }

        if ($this->isVirtualOrderType($orderType)) {
            if ($strictVirtual && $cartNum !== 1) {
                throw new AdminResponseException('内容和专题购买数量固定为1');
            }

            return 1;
        }

        return $cartNum;
    }

    /**
     * 标准化 ID 列表
     *
     * @param array $ids 待清理去重的资源编号列表。
     * @return array 转换为正整数且去重后的资源编号列表。
     */
    protected function normalizeIdList(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    /**
     * 是否商品订单
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return bool 当前订单类型是否为普通店铺商品。
     */
    protected function isGoodsOrderType(int $orderType): bool
    {
        return $orderType === Cart::ORDER_TYPE_GOODS;
    }

    /**
     * 是否虚拟资源订单
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return bool 当前订单类型是否为内容或专题等虚拟资源。
     */
    protected function isVirtualOrderType(int $orderType): bool
    {
        return in_array($orderType, [Cart::ORDER_TYPE_CONTENT, Cart::ORDER_TYPE_SPECIAL], true);
    }

    /**
     * 获取资源类型名称
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return string 与订单类型对应的前端资源类型编码。
     */
    protected function getResourceTypeName(int $orderType): string
    {
        return match ($orderType) {
            Cart::ORDER_TYPE_CONTENT => 'content',
            Cart::ORDER_TYPE_SPECIAL => 'special',
            default => 'goods',
        };
    }

    /**
     * 获取资源类型中文名称
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return string 与订单类型对应的中文资源类型名称。
     */
    protected function getResourceTypeText(int $orderType): string
    {
        return match ($orderType) {
            Cart::ORDER_TYPE_CONTENT => '内容',
            Cart::ORDER_TYPE_SPECIAL => '专题',
            default => '商品',
        };
    }

    /**
     * 获取资源不存在提示
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return string 与订单类型对应的资源不存在提示。
     */
    protected function getResourceMissingMessage(int $orderType): string
    {
        return match ($orderType) {
            Cart::ORDER_TYPE_CONTENT => '内容不存在',
            Cart::ORDER_TYPE_SPECIAL => '专题不存在',
            default => '商品不存在',
        };
    }

    /**
     * 获取重复购买提示
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @return string 与订单类型对应的重复购买提示。
     */
    protected function getRepeatPurchaseMessage(int $orderType): string
    {
        return match ($orderType) {
            Cart::ORDER_TYPE_CONTENT => '内容已购买，无需重复购买',
            Cart::ORDER_TYPE_SPECIAL => '专题已购买，无需重复购买',
            default => '商品已购买，无需重复购买',
        };
    }

    /**
     * 构建已购映射键
     *
     * @param int $orderType 订单资源类型，区分商品、内容与专题。
     * @param int $goodsId 原商品、内容或专题的资源编号。
     * @return string 由订单类型和资源编号组成的已购权益映射键。
     */
    protected function buildPurchaseKey(int $orderType, int $goodsId): string
    {
        return $orderType . '_' . $goodsId;
    }

}
