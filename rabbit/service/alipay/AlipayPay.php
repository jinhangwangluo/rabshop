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

namespace Rabbit\service\alipay;

use RuntimeException;

/**
 * 支付宝开放平台支付封装。
 *
 * 当前类使用支付宝开放平台 AOP 网关的 RSA/RSA2 参数签名方式，
 * 覆盖手机网站支付、APP 支付、扫码预下单、交易查询、交易退款与异步通知验签。
 * 业务层仍需要在回调验签后校验订单号、金额、收款方和 app_id，避免伪造通知或串单。
 */
class AlipayPay
{
    /**
     * 支付宝正式环境 AOP 网关。
     */
    protected string $gateway = 'https://openapi.alipay.com/gateway.do';

    /**
     * 支付宝运行时配置。
     *
     * @var array<string, mixed> 支付宝应用、签名算法、网关及传输配置。
     */
    protected array $config = [];

    /**
     * 初始化支付宝支付配置。
     *
     * @param array<string, mixed> $config 支付宝应用、签名、网关和超时配置，常用字段：
     *                                     - app_id: 支付宝应用 ID
     *                                     - private_key: 应用私钥
     *                                     - public_key: 支付宝公钥
     *                                     - gateway: 可选，自定义网关/沙箱网关
     *                                     - app_auth_token: 可选，服务商代调用授权令牌
     *                                     - sign_type: 可选，默认 RSA2
     *                                     - charset: 可选，默认 utf-8
     *                                     - timeout: 可选，网关请求超时秒数
     *                                     - verify_response_sign: 可选，是否校验同步接口响应签名，默认 true
     * @return void 无返回值；初始化支付宝配置及可选网关地址。
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * 设置支付宝运行时配置。
     *
     * @param array<string, mixed> $config 支付宝配置。
     * @return self 已更新运行时配置的当前支付宝支付实例。
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;
        $gateway = trim((string)($config['gateway'] ?? ''));
        if ($gateway !== '') {
            $this->gateway = $gateway;
        }

        return $this;
    }

    /**
     * 手机网站支付。
     *
     * 生成 `alipay.trade.wap.pay` 的跳转地址和自动提交表单，兼容现有业务层返回结构。
     *
     * @param string $outTradeNo 商户订单号，商户侧唯一。
     * @param float $totalAmount 订单金额，单位元，保留两位小数。
     * @param string $subject 订单标题，最长 256 字符；为空时使用“订单支付”。
     * @param string $notifyUrl 支付宝异步通知地址。
     * @param string $returnUrl 支付完成后的同步回跳地址。
     * @param string $body 订单描述，最长 128 字符。
     * @param array<string, mixed> $extra 额外 biz_content 参数，例如 timeout_express、passback_params。
     * @return array{pay_url:string,form_html:string,params:array<string, mixed>} 手机网站支付跳转地址、自动提交表单及已签名请求参数。
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
        $bizContent = $this->buildPayBizContent(
            $outTradeNo,
            $totalAmount,
            $subject,
            'QUICK_WAP_WAY',
            $body,
            $extra
        );
        $params = $this->buildSignedParams('alipay.trade.wap.pay', $bizContent, $notifyUrl, $returnUrl);

        return [
            'pay_url' => $this->gateway . '?' . http_build_query($params),
            'form_html' => $this->buildAutoSubmitForm($params),
            'params' => $params,
        ];
    }

    /**
     * APP 支付。
     *
     * 生成 `alipay.trade.app.pay` 的订单参数字符串，由服务端签名后交给客户端 SDK 调起支付宝。
     *
     * @param string $outTradeNo 商户订单号，商户侧唯一。
     * @param float $totalAmount 订单金额，单位元，保留两位小数。
     * @param string $subject 订单标题，最长 256 字符；为空时使用“订单支付”。
     * @param string $notifyUrl 支付宝异步通知地址。
     * @param string $returnUrl APP 支付同步回跳地址，可为空。
     * @param string $body 订单描述，最长 128 字符。
     * @param array<string, mixed> $extra 额外 biz_content 参数，例如 timeout_express、passback_params。
     * @return array{order_string:string,params:array<string, mixed>} 供客户端调起支付宝的订单参数字符串及已签名请求参数。
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
        $bizContent = $this->buildPayBizContent(
            $outTradeNo,
            $totalAmount,
            $subject,
            'QUICK_MSECURITY_PAY',
            $body,
            $extra
        );
        $params = $this->buildSignedParams('alipay.trade.app.pay', $bizContent, $notifyUrl, $returnUrl);

        return [
            'order_string' => http_build_query($params),
            'params' => $params,
        ];
    }

    /**
     * 扫码支付预下单。
     *
     * 调用 `alipay.trade.precreate` 获取二维码码串，调用方可将 `qr_code` 生成二维码展示给用户扫码。
     *
     * @param string $outTradeNo 商户订单号，商户侧唯一。
     * @param float $totalAmount 订单金额，单位元，保留两位小数。
     * @param string $subject 订单标题，最长 256 字符；为空时使用“订单支付”。
     * @param string $notifyUrl 支付宝异步通知地址，可为空。
     * @param string $body 订单描述，最长 128 字符。
     * @param array<string, mixed> $extra 额外 biz_content 参数，例如 store_id、timeout_express、qr_code_timeout_express。
     * @return array<string, mixed> 扫码预下单业务响应，成功时包含二维码码串，并附带业务成功、验签状态和原始报文。
     */
    public function scanPay(
        string $outTradeNo,
        float $totalAmount,
        string $subject,
        string $notifyUrl = '',
        string $body = '',
        array $extra = []
    ): array {
        $bizContent = $this->buildPayBizContent($outTradeNo, $totalAmount, $subject, '', $body, $extra);

        return $this->requestApi('alipay.trade.precreate', $bizContent, $notifyUrl);
    }

    /**
     * 订单查询。
     *
     * 调用 `alipay.trade.query` 主动查询支付宝订单状态；当异步回调丢失或支付状态未知时使用。
     *
     * @param string $outTradeNo 商户订单号，与 tradeNo 不能同时为空。
     * @param string $tradeNo 支付宝交易号，与 outTradeNo 不能同时为空；两者同时存在时支付宝优先使用 tradeNo。
     * @param array<string, mixed> $extra 额外 biz_content 参数。
     * @return array<string, mixed> 支付宝交易查单业务响应，包含交易状态及业务成功、验签状态和原始报文。
     */
    public function queryOrder(string $outTradeNo = '', string $tradeNo = '', array $extra = []): array
    {
        $bizContent = $this->buildTradeIdentityBizContent($outTradeNo, $tradeNo, $extra);

        return $this->requestApi('alipay.trade.query', $bizContent);
    }

    /**
     * 按原商户订单号关闭支付宝交易。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @return array 支付宝关单业务响应，包含业务成功标志、验签状态和原始报文，调用方仍须确认业务是否关单成功。
     */
    public function closeOrder(string $outTradeNo): array
    {
        return $this->requestApi('alipay.trade.close', $this->buildTradeIdentityBizContent($outTradeNo));
    }

    /**
     * 按原交易及固定退款请求号查询支付宝退款。
     *
     * @param string $outTradeNo 商户侧唯一订单号，支付、查询及关闭须使用同一值。
     * @param string $outRequestNo 原退款请求号，同次退款及异常重试须保持一致。
     * @return array 支付宝退款查单响应及业务成功、验签状态和原始报文；退款是否完成须结合返回状态确认。
     */
    public function queryRefund(string $outTradeNo, string $outRequestNo): array
    {
        return $this->requestApi('alipay.trade.fastpay.refund.query', $this->buildTradeIdentityBizContent($outTradeNo, '', ['out_request_no' => $outRequestNo]));
    }

    /**
     * 交易退款。
     *
     * 调用 `alipay.trade.refund` 发起全额或部分退款。部分退款时建议传入唯一 `outRequestNo`，
     * 同一次退款异常重试必须复用相同退款请求号，避免重复退款。
     *
     * @param string $outTradeNo 商户订单号，与 tradeNo 不能同时为空。
     * @param float $refundAmount 退款金额，单位元，不能大于订单实付金额。
     * @param string $refundReason 退款原因，最长 256 字符。
     * @param string $outRequestNo 退款请求号，同一交易下唯一，部分退款必传。
     * @param string $tradeNo 支付宝交易号，与 outTradeNo 不能同时为空。
     * @param array<string, mixed> $extra 额外 biz_content 参数，例如 goods_detail、operator_id、store_id。
     * @return array<string, mixed> 支付宝退款申请响应及业务成功、验签状态和原始报文；不以请求受理替代本地退款结果确认。
     */
    public function refund(
        string $outTradeNo,
        float $refundAmount,
        string $refundReason = '',
        string $outRequestNo = '',
        string $tradeNo = '',
        array $extra = []
    ): array {
        if ($refundAmount <= 0) {
            throw new RuntimeException('退款金额必须大于 0');
        }

        $bizContent = $this->buildTradeIdentityBizContent($outTradeNo, $tradeNo, $extra);
        $bizContent['refund_amount'] = $this->formatAmount($refundAmount);
        if ($refundReason !== '') {
            $bizContent['refund_reason'] = mb_substr($refundReason, 0, 256);
        }
        if ($outRequestNo !== '') {
            $bizContent['out_request_no'] = $outRequestNo;
        }

        return $this->requestApi('alipay.trade.refund', $bizContent);
    }

    /**
     * 验证支付宝异步通知签名。
     *
     * @param array<string, mixed> $payload 支付宝表单通知字段，除签名校验外仍需业务层核对订单、金额和收款方。
     * @return bool 通知签名是否匹配；缺少签名或编码无效时返回否，公钥未配置时抛出异常。
     */
    public function verifyNotify(array $payload): bool
    {
        $sign = (string) ($payload['sign'] ?? '');
        if ($sign === '') {
            return false;
        }

        $params = $payload;
        unset($params['sign'], $params['sign_type']);

        return $this->verifySignature($this->buildSignContent($params), $sign);
    }

    /**
     * 解析并校验支付宝异步通知。
     *
     * @param array<string, mixed> $payload 待验签并解析的支付宝原始表单通知字段。
     * @return array{
     *     out_trade_no:string,
     *     trade_no:string,
     *     trade_status:string,
     *     is_paid:bool,
     *     app_id:string,
     *     seller_id:string,
     *     total_amount:string,
     *     receipt_amount:string,
     *     gmt_payment:string,
     *     payload:array<string, mixed>
     * } 已通过验签的交易标识、支付状态、收款方、金额及原始通知参数，业务层仍须校验本地订单。
     */
    public function parseNotify(array $payload): array
    {
        if (!$this->verifyNotify($payload)) {
            throw new RuntimeException('支付宝回调验签失败');
        }

        $tradeStatus = strtoupper((string)($payload['trade_status'] ?? ''));

        return [
            'out_trade_no' => (string)($payload['out_trade_no'] ?? ''),
            'trade_no' => (string)($payload['trade_no'] ?? ''),
            'trade_status' => $tradeStatus,
            'is_paid' => in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true),
            'app_id' => (string)($payload['app_id'] ?? ''),
            'seller_id' => (string)($payload['seller_id'] ?? ''),
            'total_amount' => (string)($payload['total_amount'] ?? ''),
            'receipt_amount' => (string)($payload['receipt_amount'] ?? ''),
            'gmt_payment' => (string)($payload['gmt_payment'] ?? ''),
            'payload' => $payload,
        ];
    }

    /**
     * 调用支付宝 AOP 网关。
     *
     * @param string $method 支付宝开放平台接口名。
     * @param array<string, mixed> $bizContent 业务参数。
     * @param string $notifyUrl 异步通知地址。
     * @param string $returnUrl 同步回跳地址。
     * @return array<string, mixed> 支付宝响应节点数据，附带业务成功标志、验签状态及原始响应报文。
     */
    protected function requestApi(
        string $method,
        array $bizContent,
        string $notifyUrl = '',
        string $returnUrl = ''
    ): array {
        $params = $this->buildSignedParams($method, $bizContent, $notifyUrl, $returnUrl);
        $rawBody = $this->postGateway($params);

        return $this->parseGatewayResponse($method, $rawBody);
    }

    /**
     * 生成已签名公共请求参数。
     *
     * @param string $method 支付宝开放平台接口名。
     * @param array<string, mixed> $bizContent 业务参数。
     * @param string $notifyUrl 异步通知地址。
     * @param string $returnUrl 同步回跳地址。
     * @return array<string, mixed> 含序列化业务参数和应用私钥签名的支付宝网关请求参数。
     */
    protected function buildSignedParams(
        string $method,
        array $bizContent,
        string $notifyUrl = '',
        string $returnUrl = ''
    ): array {
        $params = $this->buildBaseParams($method, $notifyUrl, $returnUrl);
        $params['biz_content'] = json_encode(
            $this->filterEmptyValues($bizContent),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $params['sign'] = $this->sign($params);

        return $params;
    }

    /**
     * 构建支付宝公共请求参数。
     *
     * @param string $method 支付宝开放平台接口名。
     * @param string $notifyUrl 异步通知地址。
     * @param string $returnUrl 同步回跳地址。
     * @return array<string, mixed> 包含应用、接口、字符集、签名类型、时间戳及可选通知和回跳地址的公共参数。
     */
    protected function buildBaseParams(string $method, string $notifyUrl = '', string $returnUrl = ''): array
    {
        $appId = trim((string) ($this->config['app_id'] ?? ''));
        if ($appId === '') {
            throw new RuntimeException('支付宝 AppID 未配置');
        }

        $params = [
            'app_id' => $appId,
            'method' => $method,
            'format' => 'JSON',
            'charset' => $this->charset(),
            'sign_type' => $this->signType(),
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
        ];

        if ($notifyUrl !== '') {
            $params['notify_url'] = $notifyUrl;
        }
        if ($returnUrl !== '') {
            $params['return_url'] = $returnUrl;
        }
        $appAuthToken = trim((string)($this->config['app_auth_token'] ?? ''));
        if ($appAuthToken !== '') {
            $params['app_auth_token'] = $appAuthToken;
        }

        return $params;
    }

    /**
     * 构建支付类接口的业务参数。
     *
     * @param string $outTradeNo 商户订单号。
     * @param float $totalAmount 订单金额，单位元。
     * @param string $subject 订单标题。
     * @param string $productCode 销售产品码；扫码预下单可传空。
     * @param string $body 订单描述。
     * @param array<string, mixed> $extra 额外业务参数。
     * @return array<string, mixed> 已校验订单号与正金额、合并扩展字段并按需补齐产品码和描述的支付业务参数。
     */
    protected function buildPayBizContent(
        string $outTradeNo,
        float $totalAmount,
        string $subject,
        string $productCode = '',
        string $body = '',
        array $extra = []
    ): array {
        if ($outTradeNo === '') {
            throw new RuntimeException('商户订单号不能为空');
        }
        if ($totalAmount <= 0) {
            throw new RuntimeException('支付金额必须大于 0');
        }

        $bizContent = array_merge($this->filterEmptyValues($extra), [
            'out_trade_no' => $outTradeNo,
            'total_amount' => $this->formatAmount($totalAmount),
            'subject' => mb_substr($subject !== '' ? $subject : '订单支付', 0, 256),
        ]);
        if ($productCode !== '') {
            $bizContent['product_code'] = $productCode;
        }
        if ($body !== '') {
            $bizContent['body'] = mb_substr($body, 0, 128);
        }

        return $bizContent;
    }

    /**
     * 构建包含交易标识的业务参数。
     *
     * @param string $outTradeNo 商户订单号。
     * @param string $tradeNo 支付宝交易号。
     * @param array<string, mixed> $extra 额外业务参数。
     * @return array<string, mixed> 包含商户订单号或支付宝交易号及非空扩展字段的交易业务参数。
     */
    protected function buildTradeIdentityBizContent(string $outTradeNo = '', string $tradeNo = '', array $extra = []): array
    {
        if ($outTradeNo === '' && $tradeNo === '') {
            throw new RuntimeException('商户订单号和支付宝交易号不能同时为空');
        }

        $bizContent = $this->filterEmptyValues($extra);
        if ($outTradeNo !== '') {
            $bizContent['out_trade_no'] = $outTradeNo;
        }
        if ($tradeNo !== '') {
            $bizContent['trade_no'] = $tradeNo;
        }

        return $bizContent;
    }

    /**
     * 向支付宝网关发起 POST 请求。
     *
     * @param array<string, mixed> $params 已签名网关参数。
     * @return string HTTP 状态通过检查的原始支付宝响应字符串，传输或状态异常时抛出异常。
     */
    protected function postGateway(array $params): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('当前 PHP 环境未启用 curl 扩展');
        }

        $curl = curl_init($this->gateway);
        if ($curl === false) {
            throw new RuntimeException('支付宝网关请求初始化失败');
        }

        $sslVerify = (bool)($this->config['ssl_verify'] ?? true);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => (int)($this->config['connect_timeout'] ?? 10),
            CURLOPT_TIMEOUT => (int)($this->config['timeout'] ?? 20),
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded;charset=' . $this->charset(),
            ],
        ]);

        $body = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $errno !== 0) {
            throw new RuntimeException('支付宝网关请求失败：' . $error);
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('支付宝网关 HTTP 状态异常：' . $statusCode);
        }

        return (string)$body;
    }

    /**
     * 解析并校验支付宝网关响应。
     *
     * @param string $method 用于定位响应节点的支付宝接口名。
     * @param string $rawBody 支付宝同步响应的原始 JSON 字符串，验签时不能重新编码。
     * @return array<string, mixed> 业务响应节点及业务成功标志、验签状态和原始报文，关闭验签时验签状态为空。
     */
    protected function parseGatewayResponse(string $method, string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('支付宝响应 JSON 解析失败');
        }

        $responseNode = $this->responseNode($method);
        $response = $decoded[$responseNode] ?? null;
        if (!is_array($response)) {
            throw new RuntimeException('支付宝响应节点缺失：' . $responseNode);
        }

        $signVerified = null;
        if ((bool)($this->config['verify_response_sign'] ?? true)) {
            $signVerified = $this->verifyGatewayResponse($rawBody, $responseNode, (string)($decoded['sign'] ?? ''));
            if (!$signVerified) {
                throw new RuntimeException('支付宝响应验签失败');
            }
        }

        $response['_success'] = (string)($response['code'] ?? '') === '10000';
        $response['_sign_verified'] = $signVerified;
        $response['_raw_body'] = $rawBody;

        return $response;
    }

    /**
     * 校验支付宝网关同步响应签名。
     *
     * @param string $rawBody 支付宝同步响应的原始 JSON 字符串，必须保留业务节点原文。
     * @param string $responseNode 响应节点名。
     * @param string $sign 支付宝响应签名。
     * @return bool 原始响应节点的签名是否匹配，签名或节点原文缺失时抛出异常。
     */
    protected function verifyGatewayResponse(string $rawBody, string $responseNode, string $sign): bool
    {
        if ($sign === '') {
            throw new RuntimeException('支付宝响应缺少签名');
        }

        $content = $this->extractJsonNodeContent($rawBody, $responseNode);
        if ($content === '') {
            throw new RuntimeException('支付宝响应验签内容解析失败');
        }

        return $this->verifySignature($content, $sign);
    }

    /**
     * 从支付宝原始 JSON 中提取指定响应节点字符串。
     *
     * 支付宝同步响应验签需要使用响应节点原文，不应重新 json_encode 后再验签。
     *
     * @param string $rawBody 原始响应 JSON。
     * @param string $node 响应节点名。
     * @return string 保留原始字节的响应节点 JSON 子串，节点不存在或边界无效时为空字符串。
     */
    protected function extractJsonNodeContent(string $rawBody, string $node): string
    {
        $needle = '"' . $node . '"';
        $nodePosition = strpos($rawBody, $needle);
        if ($nodePosition === false) {
            return '';
        }

        $colonPosition = strpos($rawBody, ':', $nodePosition + strlen($needle));
        if ($colonPosition === false) {
            return '';
        }

        $start = $colonPosition + 1;
        $length = strlen($rawBody);
        while ($start < $length && ctype_space($rawBody[$start])) {
            $start++;
        }

        $opening = $rawBody[$start] ?? '';
        $closing = $opening === '{' ? '}' : ($opening === '[' ? ']' : '');
        if ($closing === '') {
            return '';
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        for ($index = $start; $index < $length; $index++) {
            $char = $rawBody[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === $opening) {
                $depth++;
                continue;
            }
            if ($char === $closing) {
                $depth--;
                if ($depth === 0) {
                    return substr($rawBody, $start, $index - $start + 1);
                }
            }
        }

        return '';
    }

    /**
     * 支付宝响应节点名。
     *
     * @param string $method 支付宝接口名。
     * @return string 按接口名替换分隔符并追加响应后缀的支付宝业务节点名。
     */
    protected function responseNode(string $method): string
    {
        return str_replace('.', '_', $method) . '_response';
    }

    /**
     * 对支付宝请求参数签名。
     *
     * @param array<string, mixed> $params 待签名参数。
     * @return string 使用配置算法与应用私钥生成并经 Base64 编码的请求签名。
     */
    protected function sign(array $params): string
    {
        $privateKey = $this->formatPrivateKey((string) ($this->config['private_key'] ?? ''));
        if ($privateKey === '') {
            throw new RuntimeException('支付宝应用私钥未配置');
        }

        $signature = '';
        $signed = openssl_sign($this->buildSignContent($params), $signature, $privateKey, $this->signAlgorithm());
        if (!$signed) {
            throw new RuntimeException('支付宝参数签名失败');
        }

        return base64_encode($signature);
    }

    /**
     * 校验支付宝签名。
     *
     * @param string $content 待验签原文。
     * @param string $sign base64 签名。
     * @return bool 给定原文的支付宝签名是否有效，非法 Base64 签名返回否。
     */
    protected function verifySignature(string $content, string $sign): bool
    {
        $publicKey = $this->formatPublicKey((string) ($this->config['public_key'] ?? ''));
        if ($publicKey === '') {
            throw new RuntimeException('支付宝公钥未配置');
        }

        $signature = base64_decode($sign, true);
        if ($signature === false) {
            return false;
        }

        $verified = openssl_verify($content, $signature, $publicKey, $this->signAlgorithm());

        return $verified === 1;
    }

    /**
     * 构造支付宝签名原文。
     *
     * 参数按字典序排序，并跳过空字符串和空值；通知验签前由调用方移除 sign/sign_type，
     * 请求签名仍保留 sign_type 公共参数。
     *
     * @param array<string, mixed> $params 待签名参数。
     * @return string 按字段名排序、跳过空字符串及空值后以连接符拼接的签名原文。
     */
    protected function buildSignContent(array $params): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $pairs[] = $key . '=' . $value;
        }

        return implode('&', $pairs);
    }

    /**
     * 构建手机网站支付自动提交表单。
     *
     * @param array<string, mixed> $params 已签名请求参数。
     * @return string 包含已转义隐藏字段及自动提交脚本的手机网站支付表单。
     */
    protected function buildAutoSubmitForm(array $params): string
    {
        $inputs = [];
        foreach ($params as $key => $value) {
            $inputs[] = sprintf(
                '<input type="hidden" name="%s" value="%s">',
                htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
            );
        }

        $charset = (string) ($params['charset'] ?? $this->charset());
        $gateway = $this->gateway . '?charset=' . rawurlencode($charset);

        return '<form id="alipay_submit" name="alipay_submit" action="' . htmlspecialchars($gateway, ENT_QUOTES, 'UTF-8') . '" method="POST" accept-charset="' . htmlspecialchars($charset, ENT_QUOTES, 'UTF-8') . '">'
            . implode('', $inputs)
            . '</form><script>document.forms["alipay_submit"].submit();</script>';
    }

    /**
     * 格式化金额为支付宝要求的元格式。
     *
     * @param float $amount 金额。
     * @return string 以元为单位且固定保留两位小数的金额字符串。
     */
    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * 过滤支付宝参数中的空值。
     *
     * @param array<string, mixed> $params 原始参数。
     * @return array<string, mixed> 仅剔除空字符串和空值后保留原字段结构的参数。
     */
    protected function filterEmptyValues(array $params): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * 当前请求字符集。
     *
     * @return string 配置的非空小写字符集名称，未配置或为空时使用默认字符集。
     */
    protected function charset(): string
    {
        $charset = strtolower(trim((string)($this->config['charset'] ?? 'utf-8')));

        return $charset !== '' ? $charset : 'utf-8';
    }

    /**
     * 当前签名类型。
     *
     * @return string 有效的 RSA 或 RSA2 签名类型，未知配置回退为 RSA2。
     */
    protected function signType(): string
    {
        $signType = strtoupper(trim((string)($this->config['sign_type'] ?? 'RSA2')));

        return in_array($signType, ['RSA', 'RSA2'], true) ? $signType : 'RSA2';
    }

    /**
     * OpenSSL 签名算法。
     *
     * @return int 与当前签名类型对应的 OpenSSL 算法常量，RSA 使用 SHA-1，RSA2 使用 SHA-256。
     */
    protected function signAlgorithm(): int
    {
        return $this->signType() === 'RSA' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256;
    }

    /**
     * 格式化应用私钥。
     *
     * @param string $privateKey 配置的商户或应用私钥原文，允许使用已有 PEM 边界。
     * @return string 整理后的应用私钥 PEM 文本，未提供密钥时为空字符串。
     */
    protected function formatPrivateKey(string $privateKey): string
    {
        return $this->formatKey($privateKey, 'PRIVATE KEY');
    }

    /**
     * 格式化支付宝公钥。
     *
     * @param string $publicKey 配置的支付渠道公钥原文，允许使用已有 PEM 边界。
     * @return string 整理后的支付宝公钥 PEM 文本，未提供密钥时为空字符串。
     */
    protected function formatPublicKey(string $publicKey): string
    {
        return $this->formatKey($publicKey, 'PUBLIC KEY');
    }

    /**
     * 将一行密钥或 PEM 密钥统一整理为 OpenSSL 可识别格式。
     *
     * @param string $key 需要整理的原始密钥文本，允许为单行内容或完整 PEM 格式。
     * @param string $type 密钥类型，如 PRIVATE KEY、PUBLIC KEY。
     * @return string 移除旧边界和空白后重新包裹的 PEM 密钥，原始输入为空时返回空字符串。
     */
    protected function formatKey(string $key, string $type): string
    {
        $key = trim(str_replace('\\n', "\n", $key));
        if ($key === '') {
            return '';
        }

        $begin = '-----BEGIN ' . $type . '-----';
        $end = '-----END ' . $type . '-----';
        if (str_contains($key, $begin) && str_contains($key, $end)) {
            $key = str_replace([$begin, $end], '', $key);
        }

        $compactType = str_replace(' ', '', $type);
        $key = preg_replace('/\s+/', '', $key) ?: '';
        $key = str_replace([
            '-----BEGIN' . $compactType . '-----',
            '-----END' . $compactType . '-----',
        ], '', $key);

        return $begin . "\n"
            . chunk_split($key, 64, "\n")
            . $end;
    }
}
