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

namespace app\controller\mobile\user;

use app\BaseController;
use app\common\enum\PayTypeEnum;
use app\common\service\user\UserRechargeService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户余额充值控制器.
 *
 * 提供充值页初始化、充值单创建和充值单状态查询。
 * 需要登录的页面接口通过路由中间件注入 user_id；支付异步回调由 mobile.payment.Notify 统一承接。
 */
class UserRecharge extends BaseController
{
    /**
     * 用户充值业务服务.
     *
     * @var UserRechargeService
     */
    #[Inject]
    protected UserRechargeService $service;

    /**
     * 获取充值页初始化数据.
     *
     * 请求方式：GET user/recharge/options
     *
     * 请求头：
     * - X-Terminal-Type header 当前终端，例如 h5、official_account、miniapp；用于筛选可用支付方式。
     *
     * 返回数据：
     * - amounts array 充值预设组合数据列表，来自 db_combination_data(CombinationDataTypeEnum::RECHARGE)。
     * - balance float 当前用户余额。
     * - coins int 当前用户金币资产。
     * - coin_balance int 当前直播金币余额。
     * - coin_exchange_rate int 直播金币兑换比例，1 元可兑换金币数。
     * - methods array 当前终端可用支付方式；充值场景仅返回微信/支付宝。
     *
     * @return Response
     */
    public function options(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $terminal = $this->getRequestTerminal();

        return $this->success('获取成功', $this->service->getRechargeOptions($userId, $terminal));
    }

    /**
     * 创建充值单并发起支付.
     *
     * 请求参数：
     * - amount float 自定义充值金额；选择预设充值项时仅作前端展示兜底，最终金额以后端配置为准。
     * - coin_amount int 直播金币充值数量，order_type=live_coin 时后端按兑换比例重新计算支付金额。
     * - recharge_id int 预设充值组合数据 ID；0 或不传表示自定义充值，自定义充值不享受优惠。
     * - order_type string 订单类型，默认 recharge；直播金币充值传 live_coin。
     * - pay_type string 支付方式，仅支持 wechat/alipay；兼容 payType。
     * - trade_type string 微信交易类型，支持 h5/official_account/miniapp；兼容 from。
     * - return_url string H5 支付同步回跳地址；兼容 returnUrl。
     * - X-Terminal-Type header 当前终端，用于记录当前支付终端。
     *
     * 返回数据：
     * - order_sn string 充值单号。
     * - amount float 充值到账金额。
     * - pay_amount float 实际支付金额。
     * - order_type string 订单类型。
     * - coin_amount int 直播金币到账数量，非金币订单为 0。
     * - exchange_rate int 直播金币兑换比例，非金币订单为 0。
     * - pay_type string 支付方式。
     * - status int 充值单状态。
     * - config array 第三方支付拉起参数。
     *
     * @return Response
     */
    public function create(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $amount = (float)$this->request->post('amount/f', 0);
        $coinAmount = (int)$this->request->post('coin_amount/d', (int)$this->request->post('coinAmount/d', 0));
        $rechargeId = (int)$this->request->post('recharge_id/d', (int)$this->request->post('rechargeId/d', 0));
        $payType = trim((string)$this->request->post('pay_type', (string)$this->request->post('payType', PayTypeEnum::PAY_TYPE_WECHAT)));
        $tradeType = trim((string)$this->request->post('trade_type', (string)$this->request->post('from', '')));
        $returnUrl = trim((string)$this->request->post('return_url', (string)$this->request->post('returnUrl', '')));
        $terminal = $this->getRequestTerminal('post');
        $orderType = trim((string)$this->request->post('order_type', (string)$this->request->post('orderType', '')));

        $data = $this->service->createAndPay($userId, $amount, $payType, [
            'recharge_id' => $rechargeId,
            'order_type' => $orderType,
            'coin_amount' => $coinAmount,
            'trade_type' => $tradeType,
            'return_url' => $returnUrl,
            'terminal' => $terminal,
        ]);

        return $this->success('充值支付发起成功', $data);
    }

    /**
     * 获取当前请求终端标识，优先使用统一请求头，兼容旧参数。
     *
     * @param string $fallbackSource 兼容参数来源，支持 get/post。
     * @return string 终端标识。
     */
    protected function getRequestTerminal(string $fallbackSource = 'get'): string
    {
        $headerTerminal = trim((string)$this->request->header('X-Terminal-Type', ''));
        if ($headerTerminal !== '') {
            return $headerTerminal;
        }

        if ($fallbackSource === 'post') {
            return trim((string)$this->request->post('terminal', ''));
        }

        return trim((string)$this->request->get('terminal', ''));
    }

    /**
     * 查询充值单详情.
     *
     * 请求方式：GET user/recharge/detail
     *
     * 查询参数：
     * - order_sn string 充值单号，用于支付后轮询充值状态。
     *
     * 返回数据：
     * - order_sn string 充值单号。
     * - amount float 充值到账金额。
     * - pay_amount float 实际支付金额。
     * - order_type string 订单类型。
     * - coin_amount int 直播金币到账数量，非金币订单为 0。
     * - exchange_rate int 直播金币兑换比例，非金币订单为 0。
     * - pay_type string 支付方式。
     * - status int 状态：0待支付，1已支付，2失败。
     * - status_text string 状态文案。
     * - pay_time string|null 支付时间。
     *
     * @return Response
     */
    public function detail(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->get('order_sn', ''));
        if ($orderSn === '') {
            return $this->fail('充值单号不能为空');
        }

        return $this->success('获取成功', $this->service->detail($userId, $orderSn));
    }

}
