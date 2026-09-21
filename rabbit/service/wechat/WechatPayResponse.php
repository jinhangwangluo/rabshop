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

namespace Rabbit\service\wechat;

/**
 * 微信支付 API v3 响应封装
 *
 * 微信支付 v3 接口通过 HTTP 状态码表示成功/失败，不再使用 errcode 字段。
 * 成功时 HTTP 2xx，失败时返回 JSON 包含 code 与 message 字段。
 *
 * Class WechatPayResponse
 * @package Rabbit\service\wechat
 */
class WechatPayResponse
{
    /**
     * 响应数据
     * @var array
     */
    protected array $data = [];

    /**
     * @param array  $response   已解析的响应数组
     * @param int    $statusCode HTTP 状态码
     * @throws WechatException
     */
    public function __construct(array $response, int $statusCode = 200)
    {
        // 微信支付 v3 以 HTTP 状态码判断成功与否
        if ($statusCode < 200 || $statusCode >= 300) {
            $code    = $response['code']    ?? 'UNKNOWN';
            $message = $response['message'] ?? 'WechatPay API error';
            throw new WechatException('[' . $code . '] ' . $message);
        }

        $this->data = $response;
    }

    /**
     * 获取响应数据
     * @return array
     */
    public function getResponse(): array
    {
        return $this->data;
    }

    /**
     * 获取指定字段值
     * @param string $key     字段名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
