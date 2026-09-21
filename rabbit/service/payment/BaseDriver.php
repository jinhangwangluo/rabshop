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

namespace Rabbit\service\payment;

/**
 * 支付驱动基类。
 */
abstract class BaseDriver implements PaymentInterface
{
    /**
     * 支付运行时配置。
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * @param array<string, mixed> $config 支付配置。
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * @param array<string, mixed> $config 支付配置。
     * @return static
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;

        return $this;
    }

    /**
     * 关闭订单默认不支持。
     *
     * @param array<string, mixed> $payload 关闭参数。
     * @return array<string, mixed>
     */
    public function close(array $payload): array
    {
        throw new PaymentException('当前支付渠道不支持关闭订单');
    }

    /**
     * 查询退款默认不支持。
     *
     * @param array<string, mixed> $payload 查询参数。
     * @return array<string, mixed>
     */
    public function queryRefund(array $payload): array
    {
        throw new PaymentException('当前支付渠道不支持退款查询');
    }

    /**
     * 退款回调默认不支持。
     *
     * @param array<string, mixed>|string $payload 回调参数或原始报文。
     * @param array<string, mixed> $headers 回调请求头。
     * @return array<string, mixed>
     */
    public function refundNotify(array|string $payload, array $headers = []): array
    {
        throw new PaymentException('当前支付渠道不支持退款异步通知');
    }

    /**
     * 获取字符串参数。
     *
     * @param array<string, mixed> $payload 参数数组。
     * @param string $key 参数名。
     * @param string $default 默认值。
     * @return string 参数值。
     */
    protected function stringValue(array $payload, string $key, string $default = ''): string
    {
        return trim((string)($payload[$key] ?? $default));
    }

    /**
     * 获取浮点金额。
     *
     * @param array<string, mixed> $payload 参数数组。
     * @param string $key 参数名。
     * @return float 金额。
     */
    protected function floatValue(array $payload, string $key): float
    {
        return (float)($payload[$key] ?? 0);
    }

    /**
     * 获取渠道扩展参数。
     *
     * @param array<string, mixed> $payload 参数数组。
     * @return array<string, mixed> 扩展参数。
     */
    protected function extra(array $payload): array
    {
        return is_array($payload['extra'] ?? null) ? $payload['extra'] : [];
    }

    /**
     * 将元转为分。
     *
     * @param float $amount 金额，单位元。
     * @return int 金额，单位分。
     */
    protected function yuanToFen(float $amount): int
    {
        return (int)round($amount * 100);
    }

    /**
     * 标准化支付回调结构。
     *
     * @param array<string, mixed> $data 回调数据。
     * @return array<string, mixed> 标准结构。
     */
    protected function normalizeNotify(array $data): array
    {
        return array_merge([
            'channel' => $this->getChannel(),
            'event_type' => 'payment',
            'out_trade_no' => '',
            'transaction_id' => '',
            'trade_status' => '',
            'refund_id' => '',
            'out_refund_no' => '',
            'refund_status' => '',
            'is_success' => false,
            'pay_time' => '',
            'raw' => [],
        ], $data);
    }
}
