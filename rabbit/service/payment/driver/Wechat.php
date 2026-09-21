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

use Rabbit\service\payment\BaseDriver;
use Rabbit\service\payment\PaymentException;
use Rabbit\service\payment\PaymentService;
use Rabbit\service\wechat\WechatPay;
use Rabbit\service\wechat\WechatPayResponse;

/**
 * 微信支付统一驱动。
 */
class Wechat extends BaseDriver
{
    /**
     * 当前驱动使用的微信底层支付封装。
     *
     * @var WechatPay 按驱动配置初始化的微信请求、验签及解密实例。
     */
    protected WechatPay $pay;

    /**
     * 设置微信统一驱动配置。
     *
     * @param array<string, mixed> $config 微信支付配置。
     * @return static 已更新配置并初始化微信支付底层封装的当前驱动实例。
     */
    public function setConfig(array $config): static
    {
        parent::setConfig($config);
        $this->pay = new WechatPay($config);

        return $this;
    }

    /**
     * 获取微信统一支付渠道标识。
     *
     * @return string 统一支付服务中微信支付渠道的固定标识。
     */
    public function getChannel(): string
    {
        return PaymentService::CHANNEL_WECHAT;
    }

    /**
     * 发起微信支付。
     *
     * @param array<string, mixed> $payload 统一支付参数。
     * @return array<string, mixed> 所选微信支付场景的客户端签名参数、手机网页跳转地址或扫码链接。
     */
    public function pay(array $payload): array
    {
        $tradeType = $this->normalizeTradeType($this->stringValue($payload, 'trade_type', 'oa_jsapi'));
        $outTradeNo = $this->requireString($payload, 'out_trade_no', '商户订单号不能为空');
        $totalFee = isset($payload['amount_fen'])
            ? (int)$payload['amount_fen']
            : $this->yuanToFen($this->floatValue($payload, 'amount'));
        $body = $this->resolveDescription($payload);
        $notifyUrl = $this->requireString($payload, 'notify_url', '微信支付回调地址不能为空');
        $timeExpire = $this->stringValue($payload, 'time_expire');

        if ($totalFee <= 0) {
            throw new PaymentException('支付金额必须大于 0');
        }

        return match ($tradeType) {
            'miniapp_jsapi' => $this->miniprogramPay(
                $this->requireString($payload, 'openid', '微信支付 OpenID 不能为空'),
                $outTradeNo,
                $totalFee,
                $body,
                $notifyUrl,
                $timeExpire
            ),
            'h5' => $this->h5Pay(
                $outTradeNo,
                $totalFee,
                $body,
                $notifyUrl,
                $this->stringValue($payload, 'client_ip'),
                $timeExpire
            ),
            'native' => $this->nativePay($outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire),
            default => $this->jsapiPay(
                $this->requireString($payload, 'openid', '微信支付 OpenID 不能为空'),
                $outTradeNo,
                $totalFee,
                $body,
                $notifyUrl,
                $timeExpire
            ),
        };
    }

    /**
     * 查询微信支付订单。
     *
     * @param array<string, mixed> $payload 包含原商户订单号的微信查单参数。
     * @return array<string, mixed> 微信订单状态查询响应数据，非成功 HTTP 状态由底层抛出异常。
     */
    public function query(array $payload): array
    {
        return $this->queryOrder($this->requireString($payload, 'out_trade_no', '商户订单号不能为空'));
    }

    /**
     * 关闭微信支付订单。
     *
     * @param array<string, mixed> $payload 包含原商户订单号的微信关单参数。
     * @return array<string, mixed> 微信关单响应数据，非成功 HTTP 状态由底层校验并抛出异常。
     */
    public function close(array $payload): array
    {
        return $this->closeOrder($this->requireString($payload, 'out_trade_no', '商户订单号不能为空'))->getResponse();
    }

    /**
     * 发起微信退款。
     *
     * @param array<string, mixed> $payload 原订单与退款单号、原金额及退款金额；分值字段优先于元值字段。
     * @return array<string, mixed> 微信退款申请响应数据，申请受理并不等同退款已完成。
     */
    public function refund(array $payload): array
    {
        $outTradeNo = $this->requireString($payload, 'out_trade_no', '商户订单号不能为空');
        $outRefundNo = $this->requireString($payload, 'out_refund_no', '商户退款单号不能为空');
        $totalFee = isset($payload['total_amount_fen'])
            ? (int)$payload['total_amount_fen']
            : $this->yuanToFen($this->floatValue($payload, 'total_amount'));
        $refundFee = isset($payload['refund_amount_fen'])
            ? (int)$payload['refund_amount_fen']
            : $this->yuanToFen($this->floatValue($payload, 'refund_amount'));

        if ($totalFee <= 0 || $refundFee <= 0) {
            throw new PaymentException('退款金额必须大于 0');
        }

        return $this->pay->refund(
            $outTradeNo,
            $outRefundNo,
            $totalFee,
            $refundFee,
            $this->stringValue($payload, 'reason'),
            $this->stringValue($payload, 'notify_url')
        );
    }

    /**
     * 查询微信退款。
     *
     * @param array<string, mixed> $payload 优先使用 out_refund_no；兼容 refund_id 字段，但其值仍按商户退款单号查询。
     * @return array<string, mixed> 按商户退款单号调用微信接口所得的退款状态数据，不能从金额推断完成状态。
     */
    public function queryRefund(array $payload): array
    {
        $outRefundNo = $this->stringValue($payload, 'out_refund_no');
        if ($outRefundNo === '') {
            $outRefundNo = $this->requireString($payload, 'refund_id', '商户退款单号不能为空');
        }

        return $this->pay->queryRefund($outRefundNo);
    }

    /**
     * 处理微信支付回调。
     *
     * @param array<string, mixed>|string $payload 微信原始通知字符串；数组仅作兼容，不能保证重新编码后仍可验签。
     * @param array<string, mixed> $headers 微信签名请求头，须保留时间戳、随机串、签名和序列号。
     * @return array<string, mixed> 验签、解密后包含微信渠道、交易状态、支付时间及解密资源数据的统一支付通知。
     */
    public function notify(array|string $payload, array $headers = []): array
    {
        $raw = $this->handleNotify($this->rawPayload($payload), $headers);
        $tradeState = strtoupper((string)($raw['trade_state'] ?? ''));

        return $this->normalizeNotify([
            'out_trade_no' => (string)($raw['out_trade_no'] ?? ''),
            'transaction_id' => (string)($raw['transaction_id'] ?? ''),
            'trade_status' => $tradeState,
            'is_success' => $tradeState === 'SUCCESS',
            'pay_time' => (string)($raw['success_time'] ?? ''),
            'raw' => $raw,
        ]);
    }

    /**
     * 处理微信退款回调。
     *
     * @param array<string, mixed>|string $payload 微信原始退款通知字符串；数组仅作兼容，不能保证重新编码后仍可验签。
     * @param array<string, mixed> $headers 微信签名请求头，须保留时间戳、随机串、签名和序列号。
     * @return array<string, mixed> 验签、解密后包含交易标识、退款单号、退款状态及解密资源数据的统一退款通知。
     */
    public function refundNotify(array|string $payload, array $headers = []): array
    {
        $raw = $this->handleRefundNotify($this->rawPayload($payload), $headers);
        $refundStatus = strtoupper((string)($raw['refund_status'] ?? $raw['status'] ?? ''));

        return $this->normalizeNotify([
            'event_type' => 'refund',
            'out_trade_no' => (string)($raw['out_trade_no'] ?? ''),
            'transaction_id' => (string)($raw['transaction_id'] ?? ''),
            'trade_status' => strtoupper((string)($raw['trade_state'] ?? '')),
            'refund_id' => (string)($raw['refund_id'] ?? ''),
            'out_refund_no' => (string)($raw['out_refund_no'] ?? ''),
            'refund_status' => $refundStatus,
            'is_success' => $refundStatus === 'SUCCESS',
            'raw' => $raw,
        ]);
    }

    /**
     * JSAPI 支付下单。
     *
     * @param string $openid 支付用户在当前微信应用中的用户标识。
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param int $totalFee 订单支付总金额，单位为分。
     * @param string $body 订单商品描述。
     * @param string $notifyUrl 支付结果异步通知地址。
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array<string, mixed> 公众号内调起微信支付所需的应用标识、随机串、预支付参数及签名。
     */
    public function jsapiPay(
        string $openid,
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $timeExpire = ''
    ): array {
        return $this->pay->jsapiPay($openid, $outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire);
    }

    /**
     * 小程序支付下单。
     *
     * @param string $openid 支付用户在当前小程序应用中的用户标识。
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param int $totalFee 订单支付总金额，单位为分。
     * @param string $body 订单商品描述。
     * @param string $notifyUrl 支付结果异步通知地址。
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array<string, mixed> 小程序内调起微信支付所需的应用标识、随机串、预支付参数及签名。
     */
    public function miniprogramPay(
        string $openid,
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $timeExpire = ''
    ): array {
        return $this->pay->miniprogramPay($openid, $outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire);
    }

    /**
     * H5 支付下单。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param int $totalFee 订单支付总金额，单位为分。
     * @param string $body 订单商品描述。
     * @param string $notifyUrl 支付结果异步通知地址。
     * @param string $clientIp 手机网页支付场景的用户客户端地址。
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array<string, mixed> 手机网页外部调起微信支付所需的跳转地址。
     */
    public function h5Pay(
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $clientIp,
        string $timeExpire = ''
    ): array {
        return $this->pay->h5Pay($outTradeNo, $totalFee, $body, $notifyUrl, $clientIp, $timeExpire);
    }

    /**
     * Native 支付下单。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param int $totalFee 订单支付总金额，单位为分。
     * @param string $body 订单商品描述。
     * @param string $notifyUrl 支付结果异步通知地址。
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array<string, mixed> 供扫码支付使用的微信二维码链接。
     */
    public function nativePay(string $outTradeNo, int $totalFee, string $body, string $notifyUrl, string $timeExpire = ''): array
    {
        return $this->pay->nativePay($outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire);
    }

    /**
     * 查询微信订单。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @return array<string, mixed> 指定商户订单的微信交易查询响应数据。
     */
    public function queryOrder(string $outTradeNo): array
    {
        return $this->pay->queryOrder($outTradeNo);
    }

    /**
     * 关闭微信订单。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @return WechatPayResponse 已通过 HTTP 成功状态校验的微信关单响应封装。
     */
    public function closeOrder(string $outTradeNo): WechatPayResponse
    {
        return $this->pay->closeOrder($outTradeNo);
    }

    /**
     * 处理微信支付原始回调。
     *
     * @param string $rawBody 微信支付通知的原始请求体，必须保留签名覆盖的原始字节。
     * @param array<string, mixed> $headers 微信签名请求头，须保留时间戳、随机串、签名和序列号。
     * @return array<string, mixed> 使用原始请求体验签并解密后的微信支付通知资源。
     */
    public function handleNotify(string $rawBody, array $headers): array
    {
        return $this->pay->handleNotify($rawBody, $headers);
    }

    /**
     * 处理微信退款原始回调。
     *
     * @param string $rawBody 微信退款通知的原始请求体，必须保留签名覆盖的原始字节。
     * @param array<string, mixed> $headers 微信签名请求头，须保留时间戳、随机串、签名和序列号。
     * @return array<string, mixed> 使用原始请求体验签并解密后的微信退款通知资源。
     */
    public function handleRefundNotify(string $rawBody, array $headers): array
    {
        return $this->pay->handleRefundNotify($rawBody, $headers);
    }

    /**
     * 标准化微信交易类型。
     *
     * @param string $tradeType 需要归一化的微信支付场景名称。
     * @return string 小程序、手机网页、扫码或默认公众号内支付的统一场景编码。
     */
    protected function normalizeTradeType(string $tradeType): string
    {
        return match ($tradeType) {
            'miniapp', 'mini_program', 'miniapp_jsapi', 'wechat_mp' => 'miniapp_jsapi',
            'h5', 'mweb', 'wap', 'wechat_wap', 'weixinh5', 'wechat_h5' => 'h5',
            'native', 'scan', 'qrcode', 'wechat_native' => 'native',
            default => 'oa_jsapi',
        };
    }

    /**
     * 获取支付描述。
     *
     * @param array<string, mixed> $payload 支付参数。
     * @return string 优先使用商品描述，其次使用订单标题，均为空时回退到默认支付标题。
     */
    protected function resolveDescription(array $payload): string
    {
        $body = $this->stringValue($payload, 'body');
        if ($body !== '') {
            return $body;
        }

        $subject = $this->stringValue($payload, 'subject');

        return $subject !== '' ? $subject : '订单支付';
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

    /**
     * 获取回调报文并保留原始字符串。
     *
     * 微信 v3 签名覆盖原始请求体；数组仅兼容旧调用，重新编码不能保证仍与签名原文一致。
     *
     * @param array<string, mixed>|string $payload 需透传的微信原始通知字符串，或需要兼容重新编码的数组。
     * @return string 字符串输入按原文返回；数组兼容输入返回重新编码的 JSON，编码失败时为空字符串。
     */
    protected function rawPayload(array|string $payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
