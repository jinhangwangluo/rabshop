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
 * 支付驱动统一能力接口。
 */
interface PaymentInterface
{
    /**
     * 设置支付运行时配置。
     *
     * @param array<string, mixed> $config 支付渠道配置。
     * @return static
     */
    public function setConfig(array $config): static;

    /**
     * 获取当前支付渠道标识。
     *
     * @return string 渠道标识。
     */
    public function getChannel(): string;

    /**
     * 发起支付。
     *
     * @param array<string, mixed> $payload 统一支付参数。
     * @return array<string, mixed> 支付渠道响应。
     */
    public function pay(array $payload): array;

    /**
     * 查询支付订单。
     *
     * @param array<string, mixed> $payload 查询参数。
     * @return array<string, mixed> 查询结果。
     */
    public function query(array $payload): array;

    /**
     * 关闭支付订单。
     *
     * @param array<string, mixed> $payload 关闭参数。
     * @return array<string, mixed> 关闭结果。
     */
    public function close(array $payload): array;

    /**
     * 发起退款。
     *
     * @param array<string, mixed> $payload 退款参数。
     * @return array<string, mixed> 退款结果。
     */
    public function refund(array $payload): array;

    /**
     * 查询退款。
     *
     * @param array<string, mixed> $payload 查询参数。
     * @return array<string, mixed> 查询结果。
     */
    public function queryRefund(array $payload): array;

    /**
     * 处理支付回调。
     *
     * @param array<string, mixed>|string $payload 回调参数或原始报文。
     * @param array<string, mixed> $headers 回调请求头。
     * @return array<string, mixed> 标准化回调数据。
     */
    public function notify(array|string $payload, array $headers = []): array;

    /**
     * 处理退款回调。
     *
     * @param array<string, mixed>|string $payload 回调参数或原始报文。
     * @param array<string, mixed> $headers 回调请求头。
     * @return array<string, mixed> 标准化回调数据。
     */
    public function refundNotify(array|string $payload, array $headers = []): array;
}
