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

namespace Rabbit\service\payment\driver;

use Rabbit\service\alipay\AlipayPay;
use Rabbit\service\payment\BaseDriver;
use Rabbit\service\payment\PaymentException;
use Rabbit\service\payment\PaymentService;

/**
 * 支付宝统一驱动。
 */
class Alipay extends BaseDriver
{
    /**
     * 当前驱动使用的支付宝底层支付封装。
     *
     * @var AlipayPay 按驱动配置初始化的支付宝请求与验签实例。
     */
    protected AlipayPay $pay;

    /**
     * 设置支付宝统一驱动配置。
     *
     * @param array<string, mixed> $config 支付宝配置。
     * @return static 已更新配置并初始化支付宝底层封装的当前驱动实例。
     */
    public function setConfig(array $config): static
    {
        parent::setConfig($config);
        $this->pay = new AlipayPay($config);

        return $this;
    }

    /**
     * 获取支付宝统一支付渠道标识。
     *
     * @return string 统一支付服务中支付宝渠道的固定标识。
     */
    public function getChannel(): string
    {
        return PaymentService::CHANNEL_ALIPAY;
    }

    /**
     * 发起支付宝支付。
     *
     * @param array<string, mixed> $payload 统一支付参数。
     * @return array<string, mixed> 所选支付宝场景的订单参数、跳转表单或扫码预下单响应。
     */
    public function pay(array $payload): array
    {
        $tradeType = strtolower($this->stringValue($payload, 'trade_type', 'wap'));
        $outTradeNo = $this->requireString($payload, 'out_trade_no', '商户订单号不能为空');
        $amount = $this->floatValue($payload, 'amount');
        $subject = $this->stringValue($payload, 'subject', '订单支付');
        $notifyUrl = $this->stringValue($payload, 'notify_url');
        $returnUrl = $this->stringValue($payload, 'return_url');
        $body = $this->stringValue($payload, 'body');
        $extra = $this->extra($payload);

        return match ($tradeType) {
            'app' => $this->appPay($outTradeNo, $amount, $subject, $notifyUrl, $returnUrl, $body, $extra),
            'scan', 'native', 'precreate' => $this->scanPay($outTradeNo, $amount, $subject, $notifyUrl, $body, $extra),
            'wap', 'h5' => $this->wapPay($outTradeNo, $amount, $subject, $notifyUrl, $returnUrl, $body, $extra),
            default => throw new PaymentException('支付宝支付场景不存在'),
        };
    }

    /**
     * 查询支付宝订单。
     *
     * @param array<string, mixed> $payload 原商户订单号、可选支付宝交易号及额外查单参数。
     * @return array<string, mixed> 支付宝订单查询响应及其业务成功、验签状态和原始报文。
     */
    public function query(array $payload): array
    {
        return $this->queryOrder(
            $this->stringValue($payload, 'out_trade_no'),
            $this->stringValue($payload, 'transaction_id'),
            $this->extra($payload)
        );
    }

    /**
     * 使用原商户订单号发起支付宝关单。
     *
     * @param array $payload 包含原商户订单号的关单参数。
     * @return array 原订单的支付宝关单业务响应及其业务成功、验签状态和原始报文。
     */
    public function close(array $payload): array
    {
        return $this->pay->closeOrder($this->requireString($payload, 'out_trade_no', '商户订单号不能为空'));
    }

    /**
     * 查询固定退款单号的支付宝退款业务状态。
     *
     * @param array $payload 原商户订单号及必须复用的原商户退款单号。
     * @return array 固定退款单号的支付宝查单响应，须依据业务状态确认结果，不能从退款金额推断完成。
     */
    public function queryRefund(array $payload): array
    {
        return $this->pay->queryRefund(
            $this->requireString($payload, 'out_trade_no', '商户订单号不能为空'),
            $this->requireString($payload, 'out_refund_no', '退款单号不能为空')
        );
    }

    /**
     * 发起支付宝退款。
     *
     * @param array<string, mixed> $payload 原交易标识、退款金额、固定退款单号、原因及额外业务参数。
     * @return array<string, mixed> 支付宝全额或部分退款申请响应，具体完成结果须另行确认。
     */
    public function refund(array $payload): array
    {
        return $this->pay->refund(
            $this->stringValue($payload, 'out_trade_no'),
            $this->floatValue($payload, 'refund_amount'),
            $this->stringValue($payload, 'reason'),
            $this->stringValue($payload, 'out_refund_no'),
            $this->stringValue($payload, 'transaction_id'),
            $this->extra($payload)
        );
    }

    /**
     * 处理支付宝支付回调。
     *
     * @param array<string, mixed>|string $payload 支付宝表单回调字段；字符串输入在当前驱动中会被拒绝。
     * @param array<string, mixed> $headers 统一通知接口预留的请求头参数，支付宝表单验签不使用此字段。
     * @return array<string, mixed> 包含支付宝渠道、交易标识、支付状态、支付时间和原始参数的统一通知数据。
     */
    public function notify(array|string $payload, array $headers = []): array
    {
        if (!is_array($payload)) {
            throw new PaymentException('支付宝回调参数格式错误');
        }

        $notify = $this->parseNotify($payload);

        return $this->normalizeNotify([
            'out_trade_no' => (string)($notify['out_trade_no'] ?? ''),
            'transaction_id' => (string)($notify['trade_no'] ?? ''),
            'trade_status' => (string)($notify['trade_status'] ?? ''),
            'is_success' => (bool)($notify['is_paid'] ?? false),
            'pay_time' => (string)($notify['gmt_payment'] ?? ''),
            'raw' => $payload,
        ]);
    }

    /**
     * 手机网站支付。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param float $totalAmount 订单支付金额，单位为元。
     * @param string $subject 订单标题，为空时使用默认支付标题。
     * @param string $notifyUrl 支付结果异步通知地址。
     * @param string $returnUrl 支付完成后同步返回的页面地址，允许为空。
     * @param string $body 订单商品描述。
     * @param array<string, mixed> $extra 额外业务参数。
     * @return array<string, mixed> 手机网站支付跳转地址、自动提交表单及已签名参数。
     */
    public function wapPay(
        string $outTradeNo,
        float $totalAmount,
        string $subject,
        string $notifyUrl,
        string $returnUrl = '',
        string $body = '',
        array $extra = []
    ): array {
        return $this->pay->wapPay($outTradeNo, $totalAmount, $subject, $notifyUrl, $returnUrl, $body, $extra);
    }

    /**
     * APP 支付。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param float $totalAmount 订单支付金额，单位为元。
     * @param string $subject 订单标题，为空时使用默认支付标题。
     * @param string $notifyUrl 支付结果异步通知地址。
     * @param string $returnUrl 支付完成后同步返回的页面地址，允许为空。
     * @param string $body 订单商品描述。
     * @param array<string, mixed> $extra 额外业务参数。
     * @return array<string, mixed> 客户端调起支付宝所需的订单参数字符串及已签名参数。
     */
    public function appPay(
        string $outTradeNo,
        float $totalAmount,
        string $subject,
        string $notifyUrl,
        string $returnUrl = '',
        string $body = '',
        array $extra = []
    ): array {
        return $this->pay->appPay($outTradeNo, $totalAmount, $subject, $notifyUrl, $returnUrl, $body, $extra);
    }

    /**
     * 扫码支付预下单。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param float $totalAmount 订单支付金额，单位为元。
     * @param string $subject 订单标题，为空时使用默认支付标题。
     * @param string $notifyUrl 支付结果异步通知地址。
     * @param string $body 订单商品描述。
     * @param array<string, mixed> $extra 额外业务参数。
     * @return array<string, mixed> 支付宝扫码预下单业务响应，成功时包含二维码码串。
     */
    public function scanPay(
        string $outTradeNo,
        float $totalAmount,
        string $subject,
        string $notifyUrl = '',
        string $body = '',
        array $extra = []
    ): array {
        return $this->pay->scanPay($outTradeNo, $totalAmount, $subject, $notifyUrl, $body, $extra);
    }

    /**
     * 查询支付宝订单。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param string $tradeNo 支付宝交易号，可与商户订单号共同标识原交易。
     * @param array<string, mixed> $extra 额外业务参数。
     * @return array<string, mixed> 支付宝交易状态查询响应及其业务成功、验签状态和原始报文。
     */
    public function queryOrder(string $outTradeNo = '', string $tradeNo = '', array $extra = []): array
    {
        return $this->pay->queryOrder($outTradeNo, $tradeNo, $extra);
    }

    /**
     * 验证支付宝回调签名。
     *
     * @param array<string, mixed> $payload 回调参数。
     * @return bool 回调签名是否匹配，公钥缺失等配置错误由底层抛出异常。
     */
    public function verifyNotify(array $payload): bool
    {
        return $this->pay->verifyNotify($payload);
    }

    /**
     * 解析支付宝回调。
     *
     * @param array<string, mixed> $payload 回调参数。
     * @return array<string, mixed> 验签后的支付宝交易、收款方及金额资料，业务层仍须校验本地订单。
     */
    public function parseNotify(array $payload): array
    {
        return $this->pay->parseNotify($payload);
    }

    /**
     * 获取必填字符串。
     *
     * @param array<string, mixed> $payload 参数数组。
     * @param string $key 需要读取的必填业务字段名。
     * @param string $message 必填字段为空时向调用方报告的业务错误文案。
     * @return string 已去除首尾空白的必填字段，字段为空时抛出指定业务异常。
     */
    protected function requireString(array $payload, string $key, string $message): string
    {
        $value = $this->stringValue($payload, $key);
        if ($value === '') {
            throw new PaymentException($message);
        }

        return $value;
    }
}
