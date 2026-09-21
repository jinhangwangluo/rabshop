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

namespace app\controller\mobile\order;

use app\BaseController;
use app\common\enum\PayTypeEnum;
use app\common\enum\PaymentTerminalEnum;
use app\common\service\order\OrderConfirmService;
use app\common\service\order\OrderCreateService;
use app\common\service\order\OrderPayService;
use app\common\service\order\OrderReviewService;
use app\common\service\order\OrderService;
use app\common\service\setting\PaymentConfigService;
use app\common\service\user\UserAssetService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端订单控制器.
 */
class Order extends BaseController
{

    /**
     * 订单业务服务.
     *
     * @var OrderService
     */
    #[Inject]
    protected OrderService $orderService;
    /**
     * 订单确认服务.
     *
     * @var OrderConfirmService
     */
    #[Inject]
    protected OrderConfirmService $orderConfirmService;

    /**
     * 订单创建服务.
     *
     * @var OrderCreateService
     */
    #[Inject]
    protected OrderCreateService $orderCreateService;

    /**
     * 订单支付服务.
     *
     * @var OrderPayService
     */
    #[Inject]
    protected OrderPayService $orderPayService;

    /**
     * 订单评价服务.
     *
     * @var OrderReviewService
     */
    #[Inject]
    protected OrderReviewService $orderReviewService;

    /**
     * 获取当前用户订单列表.
     *
     * GET 参数：
     * - status string 订单状态筛选。
     * - order_sn string 订单号精确/模糊查询。
     * - keyword string 搜索关键词，未传 order_sn 时作为订单号查询。
     * - merchant_id int 商户 ID，大于 0 时仅返回该商户订单。
     *
     * @return Response 返回订单分页列表。
     */
    public function index(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $status = trim((string)$this->request->get('status', ''));
        $orderSn = trim((string)$this->request->get('order_sn', ''));
        $keyword = trim((string)$this->request->get('keyword', ''));
        $merchantId = max(0, (int)$this->request->get('merchant_id/d', 0));

        $data = $this->orderService->getUserOrderList($userId, [
            'status' => $status,
            'order_sn' => $orderSn ?: $keyword,
            'merchant_id' => $merchantId,
        ]);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取当前用户订单详情.
     *
     * GET 参数：
     * - order_sn string 订单号。
     * - order_id int 订单 ID；未传 order_sn 时使用。
     *
     * @return Response 返回订单详情。
     */
    public function detail(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->get('order_sn', ''));
        $orderId = (int)$this->request->get('order_id/d', 0);

        if ($orderSn !== '') {
            $data = $this->orderService->getUserOrderDetail($userId, $orderSn);
        } elseif ($orderId > 0) {
            $data = $this->orderService->getUserOrderDetailById($userId, $orderId);
        } else {
            return $this->fail('请传入订单号或订单ID');
        }

        return $this->success('获取成功', $data);
    }

    /**
     * 获取当前用户订单物流详情.
     *
     * GET 参数：
     * - order_id int 订单 ID。
     * - order_sn string 订单号；未传订单 ID 时使用。
     *
     * @return Response 返回物流公司、收货信息和轨迹列表。
     */
    public function express(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderId = (int)$this->request->get('order_id/d', 0);
        $orderSn = trim((string)$this->request->get('order_sn', ''));
        if ($orderId <= 0 && $orderSn === '') {
            return $this->fail('请传入订单ID或订单号');
        }

        $data = $this->orderService->getUserExpressTrace($userId, $orderId, $orderSn);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取当前用户订单支付结果.
     *
     * GET 参数：
     * - order_sn string 订单号。
     * - order_id int 订单 ID；未传 order_sn 时使用。
     *
     * @return Response 返回支付状态、订单信息和本次到账赠券。
     */
    public function payResult(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->get('order_sn', ''));
        $orderId = (int)$this->request->get('order_id/d', 0);

        $data = $this->orderService->getUserOrderPaymentResult($userId, $orderSn, $orderId);

        return $this->success('获取成功', $data);
    }

    /**
     * 确认订单并返回结算数据.
     *
     * POST 参数：
     * - cart_ids array|string 购物车 ID 列表，非立即购买时必传。
     * - is_new int 是否立即购买，1是，0否。
     * - platform_coupon_id int 平台优惠券 ID。
     * - merchant_coupon_id int 商户优惠券 ID。
     * - merchant_coupon_ids array 多商户优惠券列表，每项包含 merchant_id、coupon_id。
     * - merchant_remarks array 多商户备注列表，每项包含 merchant_id、remark。
     * - use_points int 是否使用积分。
     * - address_id int 收货地址 ID。
     * - real_name/user_phone/province/city/district/detail/user_address string 临时收货地址字段。
     * - plugin_payload array 插件扩展载荷，按插件编码分组。
     * - remark string 订单备注。
     *
     * @return Response 返回确认单商品、金额、优惠和地址信息。
     */
    public function confirm(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $isNew = (bool)$this->request->post('is_new/d', 0);
        $rawCartIds = $this->request->post('cart_ids', '');
        $cartIds = parse_ids(
            $this->request->post('cart_ids/a', []),
            is_array($rawCartIds) ? '' : (string)$rawCartIds
        );

        if (!$isNew && $cartIds === []) {
            return $this->fail('请选择要确认的购物车商品');
        }

        $options = [
            'platform_coupon_id' => (int)$this->request->post('platform_coupon_id/d', 0),
            'merchant_coupon_id' => (int)$this->request->post('merchant_coupon_id/d', 0),
            'merchant_coupon_ids' => $this->request->post('merchant_coupon_ids/a', []),
            'merchant_remarks' => $this->request->post('merchant_remarks/a', []),
            'use_points' => (int)$this->request->post('use_points/d', 0),
            'address_id' => (int)$this->request->post('address_id/d', 0),
            'real_name' => trim((string)$this->request->post('real_name', '')),
            'user_phone' => trim((string)$this->request->post('user_phone', '')),
            'province' => trim((string)$this->request->post('province', '')),
            'city' => trim((string)$this->request->post('city', '')),
            'district' => trim((string)$this->request->post('district', '')),
            'detail' => trim((string)$this->request->post('detail', '')),
            'user_address' => trim((string)$this->request->post('user_address', '')),
            'remark' => trim((string)$this->request->post('remark', '')),
            'allow_missing_address' => true,
            'plugin_payload' => $this->request->post('plugin_payload/a', []),
        ];

        $data = $this->orderConfirmService->confirm($userId, $cartIds, $isNew, $options);

        return $this->success('获取成功', $data);
    }

    /**
     * 创建订单.
     *
     * POST 参数：
     * - cart_ids array|string 购物车 ID 列表，非立即购买时必传。
     * - is_new int 是否立即购买，1是，0否。
     * - platform_coupon_id int 平台优惠券 ID。
     * - merchant_coupon_id int 商户优惠券 ID。
     * - merchant_coupon_ids array 多商户优惠券列表，每项包含 merchant_id、coupon_id。
     * - merchant_remarks array 多商户备注列表，每项包含 merchant_id、remark。
     * - use_points int 是否使用积分。
     * - address_id int 收货地址 ID。
     * - real_name/user_phone/province/city/district/detail/user_address string 临时收货地址字段。
     * - plugin_payload array 插件扩展载荷，按插件编码分组。
     * - remark string 订单备注。
     *
     * @return Response 返回创建后的订单信息。
     */
    public function create(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $isNew = (bool)$this->request->post('is_new/d', 0);
        $rawCartIds = $this->request->post('cart_ids', '');
        $cartIds = parse_ids(
            $this->request->post('cart_ids/a', []),
            is_array($rawCartIds) ? '' : (string)$rawCartIds
        );

        if (!$isNew && $cartIds === []) {
            return $this->fail('请选择要下单的购物车商品');
        }

        $options = [
            'platform_coupon_id' => (int)$this->request->post('platform_coupon_id/d', 0),
            'merchant_coupon_id' => (int)$this->request->post('merchant_coupon_id/d', 0),
            'merchant_coupon_ids' => $this->request->post('merchant_coupon_ids/a', []),
            'merchant_remarks' => $this->request->post('merchant_remarks/a', []),
            'use_points' => (int)$this->request->post('use_points/d', 0),
            'address_id' => (int)$this->request->post('address_id/d', 0),
            'real_name' => trim((string)$this->request->post('real_name', '')),
            'user_phone' => trim((string)$this->request->post('user_phone', '')),
            'province' => trim((string)$this->request->post('province', '')),
            'city' => trim((string)$this->request->post('city', '')),
            'district' => trim((string)$this->request->post('district', '')),
            'detail' => trim((string)$this->request->post('detail', '')),
            'user_address' => trim((string)$this->request->post('user_address', '')),
            'plugin_payload' => $this->request->post('plugin_payload/a', []),
            'remark' => trim((string)$this->request->post('remark', '')),
        ];

        $data = $this->orderCreateService->createOrder($userId, $cartIds, $isNew, $options);

        return $this->success('下单成功', $data);
    }

    /**
     * 取消当前用户订单.
     *
     * POST 参数：
     * - order_sn string 订单号。
     *
     * @return Response 返回取消后的订单状态。
     */
    public function cancel(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        if ($orderSn === '') {
            return $this->fail('订单号不能为空');
        }

        $data = $this->orderService->cancelUserOrder($userId, $orderSn);

        return $this->success('取消订单成功', $data);
    }

    /**
     * 发起订单支付.
     *
     * POST 参数：
     * - order_sn string 订单号。
     * - pay_type string 支付方式，支持 wechat/alipay/yue；兼容 payType。
     * - trade_type string 微信交易类型；兼容 from。
     * - return_url string H5 支付回跳地址；兼容 returnUrl。
     *
     * @return Response 返回支付状态或第三方支付参数。
     */
    public function pay(): Response
    {
        return $this->handlePayRequest('支付发起成功');
    }

    /**
     * 发起订单重付.
     *
     * POST 参数同 pay()。
     *
     * @return Response 返回支付状态或第三方支付参数。
     */
    public function repay(): Response
    {
        return $this->handlePayRequest('订单重付发起成功');
    }

    /**
     * 申请订单退款，进入审核流程.
     *
     * POST 参数：
     * - order_sn string 订单号。
     * - reason string 退款原因。
     * - items array 退款商品明细；不传时按整单退款。
     *
     * @return Response 返回退款申请结果。
     */
    public function refund(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        $reason = trim((string)$this->request->post('reason', ''));
        $items = $this->request->post('items/a', []);
        if (!is_array($items)) {
            $items = [];
        }

        if ($orderSn === '') {
            return $this->fail('订单号不能为空');
        }

        $data = $this->orderService->userApplyRefund($userId, $orderSn, $reason, $items);

        return $this->success('退款申请成功', $data);
    }

    /**
     * 取消当前用户退款申请.
     *
     * POST 参数：
     * - order_sn string 订单号。
     *
     * @return Response 返回取消后的订单详情。
     */
    public function cancelRefund(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        if ($orderSn === '') {
            return $this->fail('订单号不能为空');
        }

        $data = $this->orderService->cancelUserRefund($userId, $orderSn);

        return $this->success('取消退款申请成功', $data);
    }

    /**
     * 确认订单收货.
     *
     * POST 参数：
     * - order_sn string 订单号。
     *
     * @return Response 返回确认收货后的订单状态。
     */
    public function receipt(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        if ($orderSn === '') {
            return $this->fail('订单号不能为空');
        }

        $data = $this->orderService->confirmReceipt($userId, $orderSn);

        return $this->success('确认收货成功', $data);
    }

    /**
     * 获取订单状态角标统计.
     *
     * @return Response 返回待支付、待发货等状态数量。
     */
    public function badge(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);

        $data = $this->orderService->getUserOrderBadge($userId);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取当前终端可用的支付方式列表。
     *
     * GET 参数：
     * - terminal string 当前终端，例如 h5、wechat_h5、mp_weixin、pc。
     *
     * @return Response 返回支付方式列表和当前用户余额。
     */
    public function paymentMethods(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $terminal = $this->resolveTerminal($this->request->get('terminal', ''));

        $methods = app()->make(PaymentConfigService::class)->getEnabledMethodsByTerminal($terminal);

        $balance = 0;
        if ($userId > 0) {
            $asset = app()->make(UserAssetService::class)->getInfo($userId);
            $balance = (float)($asset ? ($asset['balance'] ?? 0) : 0);
        }

        return $this->success('获取成功', [
            'methods' => $methods,
            'balance' => $balance,
        ]);
    }

    /**
     * 将前端终端标识映射为后端终端枚举值。
     *
     * @param string $terminal 前端终端标识。
     * @return string 后端支付终端枚举值。
     */
    protected function resolveTerminal(string $terminal): string
    {
        if (in_array($terminal, ['miniapp', 'mp_weixin'], true)) {
            return PaymentTerminalEnum::TERMINAL_MP_WEIXIN;
        }
        if (in_array($terminal, ['wechat-h5', 'official_account', 'wechat_h5'], true)) {
            return PaymentTerminalEnum::TERMINAL_WECHAT_H5;
        }
        if ($terminal === 'h5') {
            return PaymentTerminalEnum::TERMINAL_H5;
        }
        if ($terminal === 'pc') {
            return PaymentTerminalEnum::TERMINAL_PC;
        }

        return PaymentTerminalEnum::TERMINAL_H5;
    }

    /**
     * 删除当前用户订单.
     *
     * POST 参数：
     * - order_sn string 订单号，仅已完成或已取消订单可删除。
     *
     * @return Response 返回删除结果。
     */
    public function delete(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        if ($orderSn === '') {
            return $this->fail('订单号不能为空');
        }

        $this->orderService->deleteUserOrder($userId, $orderSn);

        return $this->success('删除成功');
    }

    /**
     * 提交订单评价.
     *
     * POST 参数：
     * - order_id int 订单 ID。
     * - order_goods_id int 订单商品 ID；不传时默认取订单第一个商品。
     * - star int 评分，默认 5。
     * - content string 评价内容。
     * - images array 评价图片列表。
     * - description_star int 描述相符评分。
     * - logistics_star int 物流服务评分。
     * - service_star int 服务态度评分。
     * - is_anonymous int 是否匿名评价。
     *
     * @return Response 返回评价提交结果。
     */
    public function review(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderId = (int)$this->request->post('order_id/d', 0);
        $orderGoodsId = (int)$this->request->post('order_goods_id/d', 0);
        $star = (int)$this->request->post('star/d', 5);
        $content = trim((string)$this->request->post('content', ''));
        $images = $this->request->post('images/a', []);
        $descriptionStar = (int)$this->request->post('description_star/d', 5);
        $logisticsStar = (int)$this->request->post('logistics_star/d', 5);
        $serviceStar = (int)$this->request->post('service_star/d', 5);
        $isAnonymous = (int)$this->request->post('is_anonymous/d', 1);

        if ($orderId <= 0) {
            return $this->fail('请选择要评价的订单');
        }

        if (!is_array($images)) {
            $images = [];
        }

        $this->orderReviewService->submitReview(
            $userId,
            $orderId,
            $orderGoodsId,
            $star,
            $content,
            $images,
            $descriptionStar,
            $logisticsStar,
            $serviceStar,
            $isAnonymous
        );

        return $this->success('评价成功');
    }

    /**
     * 获取订单评价页面数据.
     *
     * GET 参数：
     * - order_id int 订单 ID。
     * - order_goods_id int 订单商品 ID；不传时默认取订单第一个商品。
     *
     * @return Response 返回订单、评价、可评价状态。
     */
    public function reviewPage(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderId = (int)$this->request->get('order_id/d', 0);
        $orderGoodsId = (int)$this->request->get('order_goods_id/d', 0);

        if ($orderId <= 0) {
            return $this->fail('请传入订单ID');
        }

        $data = $this->orderReviewService->getReviewPage($userId, $orderId, $orderGoodsId);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取订单评价详情.
     *
     * GET 参数：
     * - order_id int 订单 ID。
     * - order_goods_id int 订单商品 ID；不传时返回该订单第一条评价。
     *
     * @return Response 返回当前用户对该订单的评价详情。
     */
    public function getReview(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderId = (int)$this->request->get('order_id/d', 0);
        $orderGoodsId = (int)$this->request->get('order_goods_id/d', 0);

        if ($orderId <= 0) {
            return $this->fail('请传入订单ID');
        }

        $data = $this->orderReviewService->getUserReview($userId, $orderId, $orderGoodsId);

        return $this->success('获取成功', $data ?: []);
    }

    /**
     * 统一处理订单支付请求.
     *
     * @param string $successMessage 支付发起成功提示文案。
     * @return Response 返回支付状态或第三方支付参数。
     */
    protected function handlePayRequest(string $successMessage): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        $payType = trim((string)$this->request->post('pay_type', (string)$this->request->post('payType', PayTypeEnum::PAY_TYPE_YUE)));
        $tradeType = trim((string)$this->request->post('trade_type', (string)$this->request->post('from', '')));
        $returnUrl = trim((string)$this->request->post('return_url', (string)$this->request->post('returnUrl', '')));

        if ($orderSn === '') {
            return $this->fail('订单号不能为空');
        }

        $data = $this->orderPayService->pay($userId, $orderSn, $payType, [
            'trade_type' => $tradeType,
            'return_url' => $returnUrl,
        ]);

        return $this->success($successMessage, $data);
    }
}
