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

namespace app\controller\mobile\payment;

use app\BaseController;
use app\common\service\order\OrderPayService;
use app\common\service\user\UserRechargeService;
use think\Response;
use think\facade\Log;
use Throwable;

/**
 * 移动端支付异步回调控制器.
 *
 * 统一承接订单支付、订单退款、用户充值等第三方支付平台回调。
 * 回调接口不依赖登录态，路由应放在移动端免登录分组中。
 */
class Notify extends BaseController
{
    /**
     * 订单微信支付异步回调.
     *
     * 请求方式：POST order/wechatNotify
     * 请求体：微信支付平台推送的原始 JSON 报文。
     * 请求头：微信支付签名、序列号、时间戳、随机串等验签字段。
     *
     * @return Response 返回微信要求的 SUCCESS/FAIL JSON。
     */
    public function orderWechat(): Response
    {
        $rawBody = (string)$this->request->getInput();
        $headers = $this->request->header();
        $logContext = [
            'route' => '/api/mobile/order/wechatNotify',
            'client_ip' => (string)$this->request->ip(),
            'raw_body' => $rawBody,
            'raw_body_length' => strlen($rawBody),
            'raw_body_sha256' => hash('sha256', $rawBody),
            'headers' => $this->sanitizeWechatHeaders(is_array($headers) ? $headers : []),
        ];

        Log::info($this->formatNotifyLogMessage('[mobile-payment] order wechat notify received', $logContext));

        try {
            $result = app()->make(OrderPayService::class)->handleWechatPayNotify(
                $rawBody,
                is_array($headers) ? $headers : []
            );

            Log::info($this->formatNotifyLogMessage('[mobile-payment] order wechat notify handled', [
                'order_sn' => (string)($result['order_sn'] ?? ''),
                'trade_type' => (string)($result['trade_type'] ?? ''),
                'trade_state' => (string)($result['trade_state'] ?? ''),
            ]));

            return json(['code' => 'SUCCESS', 'message' => '成功']);
        } catch (Throwable $throwable) {
            Log::error($this->formatNotifyLogMessage('[mobile-payment] order wechat notify failed', [
                ...$logContext,
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]));

            return json(['code' => 'FAIL', 'message' => $throwable->getMessage() ?: '支付回调处理失败']);
        }
    }

    /**
     * 脱敏微信回调请求头。
     *
     * 签名可以用于重放请求，日志只保留长度与摘要；其它验签定位字段保留原值。
     *
     * @param array<string, mixed> $headers 微信回调请求头。
     * @return array<string, mixed> 脱敏后的请求头。
     */
    protected function sanitizeWechatHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $headerName = (string)$name;
            $headerValue = is_scalar($value) ? (string)$value : $value;
            if (stripos($headerName, 'signature') !== false && is_string($headerValue)) {
                $result[$headerName] = [
                    'length' => strlen($headerValue),
                    'sha256' => hash('sha256', $headerValue),
                ];
                continue;
            }

            $result[$headerName] = $headerValue;
        }

        return $result;
    }

    /**
     * 格式化回调日志消息。
     *
     * 当前文件日志驱动不会自动输出 context 参数，需要把上下文写入 message。
     *
     * @param string $message 日志消息。
     * @param array<string, mixed> $context 日志上下文。
     * @return string 可直接写入文件日志的消息。
     */
    protected function formatNotifyLogMessage(string $message, array $context = []): string
    {
        if ($context === []) {
            return $message;
        }

        $payload = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $message . ': ' . ($payload !== false ? $payload : var_export($context, true));
    }

    /**
     * 订单支付宝支付异步回调.
     *
     * 请求方式：POST order/alipayNotify
     * POST 参数：支付宝平台推送的表单参数，包含 out_trade_no、trade_status、trade_no、sign 等。
     *
     * @return Response 返回 success/fail 文本。
     */
    public function orderAlipay(): Response
    {
        try {
            $payload = $this->request->post();
            app()->make(OrderPayService::class)->handleAlipayNotify(is_array($payload) ? $payload : []);

            return response('success');
        } catch (Throwable $throwable) {
            return response('fail');
        }
    }

    /**
     * 订单微信退款异步回调.
     *
     * 请求方式：POST order/wechatRefundNotify
     * 请求体：微信支付平台推送的退款原始 JSON 报文。
     * 请求头：微信支付签名、序列号、时间戳、随机串等验签字段。
     *
     * @return Response 返回微信要求的 SUCCESS/FAIL JSON。
     */
    public function orderWechatRefund(): Response
    {
        try {
            $rawBody = (string)$this->request->getInput();
            $headers = $this->request->header();
            app()->make(OrderPayService::class)->handleWechatRefundNotify($rawBody, is_array($headers) ? $headers : []);

            return json(['code' => 'SUCCESS', 'message' => '成功']);
        } catch (Throwable $throwable) {
            return json(['code' => 'FAIL', 'message' => $throwable->getMessage() ?: '退款回调处理失败']);
        }
    }

    /**
     * 用户充值微信支付异步回调.
     *
     * 请求方式：POST user/recharge/wechatNotify
     * 请求体：微信支付平台推送的原始 JSON 报文。
     * 请求头：微信支付签名、序列号、时间戳、随机串等验签字段。
     *
     * @return Response 返回微信要求的 SUCCESS/FAIL JSON。
     */
    public function rechargeWechat(): Response
    {
        try {
            $rawBody = (string)$this->request->getInput();
            $headers = $this->request->header();
            app()->make(UserRechargeService::class)->handleWechatNotify($rawBody, is_array($headers) ? $headers : []);

            return json(['code' => 'SUCCESS', 'message' => '成功']);
        } catch (Throwable $throwable) {
            return json(['code' => 'FAIL', 'message' => $throwable->getMessage() ?: '支付回调处理失败']);
        }
    }

    /**
     * 用户充值支付宝支付异步回调.
     *
     * 请求方式：POST user/recharge/alipayNotify
     * POST 参数：支付宝平台推送的表单参数，包含 out_trade_no、trade_status、trade_no、sign 等。
     *
     * @return Response 返回 success/fail 文本。
     */
    public function rechargeAlipay(): Response
    {
        try {
            $payload = $this->request->post();
            app()->make(UserRechargeService::class)->handleAlipayNotify(is_array($payload) ? $payload : []);

            return response('success');
        } catch (Throwable $throwable) {
            return response('fail');
        }
    }
}
