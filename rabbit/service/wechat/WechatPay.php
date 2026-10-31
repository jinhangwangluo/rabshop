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

use EasyWeChat\Pay\Application;
use Nyholm\Psr7\Uri;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 微信支付封装（API v3）
 *
 * 支持：JSAPI 支付、H5 支付、Native 支付、小程序支付、
 *       订单查询/关闭、退款申请/查询、支付/退款回调通知处理。
 *
 * 统一维护支付请求签名、回调验签与资源解密。
 * @package Rabbit\service\wechat
 */
class WechatPay
{
    use WechatApplicationConfigTrait;

    private const PAYMENT_CERT_BASE_PATH = 'storage/certs/wechat_pay';

    /**
     * 支付配置
     *
     * @var array|callable 静态支付配置或延迟返回配置数组的回调。
     */
    protected mixed $config = [];

    /**
     * EasyWeChat 微信支付应用实例。
     *
     * @var Application|null 按当前配置延迟创建并缓存的支付应用。
     */
    protected ?Application $serve = null;

    /**
     * 初始化微信支付应用配置。
     *
     * @param array $config 微信支付应用、商户、私钥和通知验签配置。
     * @return void 无返回值；保存微信支付配置并设置当前应用名称。
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->name = 'wechat_pay';
    }

    /**
     * 设置配置
     *
     * @param array|callable $config 直接配置数组或返回配置数组的回调，替换后在下次使用时重新初始化支付应用。
     * @return static 已替换配置并清除旧支付应用缓存的当前实例。
     */
    public function setConfig(array|callable $config): static
    {
        $this->config  = $config;
        $this->serve   = null;

        return $this;
    }

    /**
     * 获取 EasyWeChat 微信支付应用实例
     *
     * @return Application 按当前配置初始化并缓存的微信支付应用实例，创建失败时抛出微信业务异常。
     * @throws WechatException
     */
    protected function app(): Application
    {
        if ($this->serve) {
            return $this->serve;
        }

        $config = is_array($this->config) ? $this->config : call_user_func($this->config);

        try {
            $this->serve = new Application($config);
            $this->configureApplication($this->serve);
        } catch (\Throwable $e) {
            throw new WechatException($e->getMessage());
        }

        return $this->serve;
    }

    /**
     * 发起 POST 请求（v3 接口）
     *
     * @param string $url 微信支付 v3 接口相对路径。
     * @param array  $data   请求体数据
     * @param array $options 覆盖基础签名请求配置的附加 HTTP 客户端选项。
     * @return WechatPayResponse 通过 HTTP 成功状态检查的微信响应封装，传输或业务失败时抛出异常。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function postV3(string $url, array $data, array $options = []): WechatPayResponse
    {
        $response   = $this->app()->getClient()->post(
            $url,
            array_replace_recursive($this->buildV3RequestOptions('POST', $url, $data), $options)
        );
        $statusCode = $response->getStatusCode();
        $result     = $response->toArray(false);

        return new WechatPayResponse($result, $statusCode);
    }

    /**
     * 发起 GET 请求（v3 接口）
     *
     * @param string $url 微信支付 v3 查询接口相对路径。
     * @param array  $query 查询参数
     * @return WechatPayResponse 通过 HTTP 成功状态检查的微信查询响应封装，传输或业务失败时抛出异常。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function getV3(string $url, array $query = []): WechatPayResponse
    {
        $options    = $this->buildV3RequestOptions('GET', $url, null, $query);
        $response   = $this->app()->getClient()->get($url, $options);
        $statusCode = $response->getStatusCode();
        $result     = $response->toArray(false);

        return new WechatPayResponse($result, $statusCode);
    }

    /**
     * 生成随机字符串
     *
     * @param int $length 需要生成的随机字符串长度，默认为三十二个字符。
     * @return string 从字母和数字中随机生成的指定长度字符串。
     */
    protected function generateNonceStr(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str   = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $str;
    }

    /**
     * 构建统一下单基础参数
     *
     * @param string $outTradeNo 商户订单号
     * @param int    $totalFee   支付金额（分）
     * @param string $body       商品描述
     * @param string $notifyUrl  回调通知地址
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array 包含应用、商户、订单、人民币金额及可选支付截止时间的下单参数。
     */
    protected function buildBaseParams(
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $timeExpire = ''
    ): array {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);

        $params = [
            'appid'        => $config['app_id'] ?? '',
            'mchid'        => $config['mch_id'] ?? '',
            'description'  => $body,
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $notifyUrl,
            'amount'       => [
                'total'    => $totalFee,
                'currency' => 'CNY',
            ],
        ];
        if ($timeExpire !== '') {
            $params['time_expire'] = $timeExpire;
        }
        return $params;
    }

    // =========================================================
    // 下单接口
    // =========================================================

    /**
     * JSAPI 支付下单（公众号/小程序内支付）
     *
     * @param string $openid     支付用户的 openid
     * @param string $outTradeNo 商户订单号
     * @param int    $totalFee   支付金额（单位：分）
     * @param string $body       商品描述
     * @param string $notifyUrl  支付结果回调地址
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array{appId:string,timeStamp:string,nonceStr:string,package:string,signType:string,paySign:string} 公众号或小程序前端调起微信支付所需的应用标识、随机串、预支付参数及签名。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function jsapiPay(
        string $openid,
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $timeExpire = ''
    ): array {
        $params = array_merge($this->buildBaseParams($outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire), [
            'payer' => ['openid' => $openid],
        ]);

        $response  = $this->postV3('v3/pay/transactions/jsapi', $params);
        $prepayId  = $response->get('prepay_id');

        return $this->buildJsApiCallParams($prepayId);
    }

    /**
     * 小程序支付下单（miniprogram）
     *
     * @param string $openid 支付用户在当前小程序应用中的用户标识。
     * @param string $outTradeNo 商户订单号
     * @param int    $totalFee   支付金额（单位：分）
     * @param string $body       商品描述
     * @param string $notifyUrl  支付结果回调地址
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array{appId:string,timeStamp:string,nonceStr:string,package:string,signType:string,paySign:string} 使用小程序应用配置生成的前端调起支付参数及签名。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function miniprogramPay(
        string $openid,
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $timeExpire = ''
    ): array {
        // 小程序支付接口与 JSAPI 相同，区别在于 appid 传小程序的 appid
        return $this->jsapiPay($openid, $outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire);
    }

    /**
     * H5 支付下单（手机浏览器外部唤起微信支付）
     *
     * @param string $outTradeNo 商户订单号
     * @param int    $totalFee   支付金额（单位：分）
     * @param string $body       商品描述
     * @param string $notifyUrl  支付结果回调地址
     * @param string $clientIp  用户端 IP
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array{h5_url:string} 微信返回的手机网页支付跳转地址。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function h5Pay(
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $clientIp,
        string $timeExpire = ''
    ): array {
        $params = array_merge($this->buildBaseParams($outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire), [
            'scene_info' => [
                'payer_client_ip' => $clientIp,
                'h5_info'         => ['type' => 'Wap'],
            ],
        ]);

        $response = $this->postV3('v3/pay/transactions/h5', $params);

        return ['h5_url' => $response->get('h5_url')];
    }

    /**
     * Native 支付下单（扫码支付，返回二维码链接）
     *
     * @param string $outTradeNo 商户订单号
     * @param int    $totalFee   支付金额（单位：分）
     * @param string $body       商品描述
     * @param string $notifyUrl  支付结果回调地址
     * @param string $timeExpire 微信订单支付截止时间字符串，为空时不向渠道传入截止时间。
     * @return array{code_url:string} 微信返回的扫码支付二维码链接。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function nativePay(
        string $outTradeNo,
        int $totalFee,
        string $body,
        string $notifyUrl,
        string $timeExpire = ''
    ): array {
        $params   = $this->buildBaseParams($outTradeNo, $totalFee, $body, $notifyUrl, $timeExpire);
        $response = $this->postV3('v3/pay/transactions/native', $params);

        return ['code_url' => $response->get('code_url')];
    }

    // =========================================================
    // 订单管理
    // =========================================================

    /**
     * 查询订单（通过商户订单号）
     *
     * @param string $outTradeNo 商户订单号
     * @return array 指定商户订单的微信交易状态响应数据。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function queryOrder(string $outTradeNo): array
    {
        $config   = is_array($this->config) ? $this->config : call_user_func($this->config);
        $mchId    = $config['mch_id'] ?? '';
        $response = $this->getV3(
            'v3/pay/transactions/out-trade-no/' . $outTradeNo,
            ['mchid' => $mchId]
        );

        return $response->getResponse();
    }

    /**
     * 关闭订单
     *
     * @param string $outTradeNo 商户订单号
     * @return WechatPayResponse 已通过 HTTP 成功状态校验的微信关单响应封装。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function closeOrder(string $outTradeNo): WechatPayResponse
    {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);

        return $this->postV3(
            'v3/pay/transactions/out-trade-no/' . $outTradeNo . '/close',
            ['mchid' => $config['mch_id'] ?? '']
        );
    }

    // =========================================================
    // 退款
    // =========================================================

    /**
     * 申请退款
     *
     * @param string $outTradeNo  原商户订单号
     * @param string $outRefundNo 商户退款单号（唯一）
     * @param int    $totalFee    订单总金额（分）
     * @param int    $refundFee   退款金额（分）
     * @param string $reason      退款原因（可选）
     * @param string $notifyUrl 退款结果异步通知地址，允许为空。
     * @return array 微信退款申请响应资料；申请受理不代表最终退款完成。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function refund(
        string $outTradeNo,
        string $outRefundNo,
        int $totalFee,
        int $refundFee,
        string $reason = '',
        string $notifyUrl = ''
    ): array {
        $params = [
            'out_trade_no'  => $outTradeNo,
            'out_refund_no' => $outRefundNo,
            'amount'        => [
                'refund'   => $refundFee,
                'total'    => $totalFee,
                'currency' => 'CNY',
            ],
        ];

        if ($notifyUrl !== '') {
            $params['notify_url'] = $notifyUrl;
        }

        if (!empty($reason)) {
            $params['reason'] = $reason;
        }

        $response = $this->postV3('v3/refund/domestic/refunds', $params, $this->buildRefundCertificateOptions());

        return $response->getResponse();
    }

    /**
     * 查询退款（通过商户退款单号）
     *
     * @param string $outRefundNo 商户退款单号
     * @return array 指定商户退款单号的微信退款状态响应资料。
     * @throws WechatException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function queryRefund(string $outRefundNo): array
    {
        $response = $this->getV3('v3/refund/domestic/refunds/' . $outRefundNo);

        return $response->getResponse();
    }

    // =========================================================
    // 回调通知处理
    // =========================================================

    /**
     * 处理支付结果回调通知（验签 + 解密）
     *
     * 建议在控制器中将原始请求体和请求头传入此方法。
     * 利用 AEAD_AES_256_GCM 算法解密 resource.ciphertext，返回订单信息数组。
     *
     * @param string $rawBody 微信支付通知的原始 JSON 请求体，验签前后均不能重新编码。
     * @param array  $headers 请求头数组（需包含 Wechatpay-Signature 等字段）
     * @return array 已使用原始请求体验签并完成资源解密的通知数据，仍需业务层校验订单与金额。
     * @throws WechatException
     */
    public function handleNotify(string $rawBody, array $headers): array
    {
        try {
            $body = json_decode($rawBody, true);

            if (empty($body)) {
                throw new WechatException('回调通知体为空或格式错误');
            }

            // 验签：使用微信平台公钥对签名进行校验
            $this->verifyNotifySignature($rawBody, $headers);

            $resource = $body['resource'] ?? [];

            return $this->decryptNotifyResource($resource);
        } catch (WechatException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new WechatException('支付回调处理失败：' . $e->getMessage());
        }
    }

    /**
     * 处理退款结果回调通知（验签 + 解密）
     *
     * @param string $rawBody 微信退款通知的原始 JSON 请求体，验签前后均不能重新编码。
     * @param array  $headers 请求头
     * @return array 已使用原始请求体验签并解密后的微信退款通知资源。
     * @throws WechatException
     */
    public function handleRefundNotify(string $rawBody, array $headers): array
    {
        // 退款通知与支付通知结构相同，复用支付通知处理逻辑
        return $this->handleNotify($rawBody, $headers);
    }

    /**
     * 验证回调通知签名
     *
     * @param string $rawBody 签名覆盖的微信通知原始请求体，必须保留原始字节。
     * @param array  $headers 含时间戳、随机串、签名和序列号的微信请求头，支持标准名及全小写名。
     * @return void 无返回值；按时间戳、随机串和原始请求体验签，签名缺失、编码无效或验证失败时抛出异常。
     * @throws WechatException
     */
    protected function verifyNotifySignature(string $rawBody, array $headers): void
    {
        $timestamp = trim((string)($headers['Wechatpay-Timestamp'] ?? $headers['wechatpay-timestamp'] ?? ''));
        $nonce = trim((string)($headers['Wechatpay-Nonce'] ?? $headers['wechatpay-nonce'] ?? ''));
        $signature = trim((string)($headers['Wechatpay-Signature'] ?? $headers['wechatpay-signature'] ?? ''));
        $serial = trim((string)($headers['Wechatpay-Serial'] ?? $headers['wechatpay-serial'] ?? ''));

        if ($timestamp === '' || $nonce === '' || $signature === '' || $serial === '') {
            throw new WechatException('回调通知缺少签名信息');
        }

        $message = $timestamp . "\n" . $nonce . "\n" . $rawBody . "\n";

        $config = is_array($this->config) ? $this->config : call_user_func($this->config);
        $publicKey = $this->resolveNotifyPublicKey($serial, $config);
        $signatureDecoded = base64_decode($signature, true);
        if ($signatureDecoded === false) {
            throw new WechatException('回调通知签名不是有效的 Base64 数据');
        }

        $verified = openssl_verify($message, $signatureDecoded, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new WechatException(
                '回调通知签名验证失败（serial: ' . $serial . '），请检查微信支付公钥与公钥 ID 是否匹配'
            );
        }
    }

    /**
     * 根据回调序列号选择验签公钥。
     *
     * 优先使用按通知序列号登记的平台验签材料；未命中映射时，
     * PUB_KEY_ID_ 序列号必须匹配配置的公钥 ID，其它序列号兼容平台证书或公钥。
     *
     * @param string $serial 回调请求头 Wechatpay-Serial。
     * @param array $config 微信支付配置。
     * @return string 按序列号映射或回退公钥、证书配置取得的 PEM 验签材料。
     * @throws WechatException
     */
    protected function resolveNotifyPublicKey(string $serial, array $config): string
    {
        $platformCerts = is_array($config['platform_certs'] ?? null) ? $config['platform_certs'] : [];
        if (isset($platformCerts[$serial])) {
            return $this->readKeyContents((string)$platformCerts[$serial]);
        }

        $publicKey = $this->readKeyContents(trim((string)($config['public_key'] ?? '')));
        if (str_starts_with($serial, 'PUB_KEY_ID_')) {
            if ($publicKey === '') {
                throw new WechatException('未配置微信支付公钥，无法验证回调签名（serial: ' . $serial . '）');
            }
            if (str_contains($publicKey, 'BEGIN CERTIFICATE')) {
                throw new WechatException(
                    '当前 wechat_public_key 配置的是证书，PUB_KEY_ID 回调必须填写微信支付公钥（BEGIN PUBLIC KEY）'
                );
            }

            $publicKeyId = trim((string)($config['public_key_id'] ?? ''));
            if ($publicKeyId === '') {
                throw new WechatException('未配置微信支付公钥 ID（wechat_public_key_id）');
            }
            if (!hash_equals($publicKeyId, $serial)) {
                throw new WechatException(
                    '微信支付公钥 ID 与回调 serial 不匹配：configured=' . $publicKeyId . '，notify=' . $serial
                );
            }
            if (openssl_pkey_get_public($publicKey) === false) {
                throw new WechatException('微信支付公钥格式错误，请填写完整的 BEGIN/END PUBLIC KEY 内容');
            }

            return $publicKey;
        }

        if ($publicKey === '') {
            throw new WechatException('未配置微信平台证书，无法验证回调签名（serial: ' . $serial . '）');
        }

        if (str_contains($publicKey, 'BEGIN CERTIFICATE')) {
            $certificate = openssl_x509_parse($publicKey, false);
            if (!is_array($certificate)) {
                throw new WechatException('微信平台证书格式错误');
            }

            $certificateSerial = strtoupper((string)($certificate['serialNumberHex'] ?? ''));
            if ($certificateSerial !== '' && !hash_equals($certificateSerial, strtoupper($serial))) {
                throw new WechatException(
                    '微信平台证书序列号与回调 serial 不匹配：certificate='
                    . $certificateSerial
                    . '，notify='
                    . $serial
                );
            }
        } elseif (openssl_pkey_get_public($publicKey) === false) {
            throw new WechatException('微信平台公钥格式错误');
        }

        return $publicKey;
    }

    /**
     * 解密通知资源（AEAD_AES_256_GCM）
     *
     * @param array $resource resource 字段内容
     * @return array 使用商户接口密钥验证认证标签并解密后的通知资源数组。
     * @throws WechatException
     */
    protected function decryptNotifyResource(array $resource): array
    {
        $algorithm      = $resource['algorithm']       ?? '';
        $ciphertext     = $resource['ciphertext']      ?? '';
        $associatedData = $resource['associated_data'] ?? '';
        $nonce          = $resource['nonce']           ?? '';

        if (strtoupper($algorithm) !== 'AEAD_AES_256_GCM') {
            throw new WechatException('不支持的加密算法：' . $algorithm);
        }

        $config  = is_array($this->config) ? $this->config : call_user_func($this->config);
        $apiV3Key = $config['mch_api_v3_key'] ?? $config['api_v3_key'] ?? '';

        if (empty($apiV3Key)) {
            throw new WechatException('未配置 mch_api_v3_key，无法解密通知资源');
        }

        $ciphertextDecoded = base64_decode($ciphertext);
        // GCM 认证标签位于解码密文的末尾十六字节。
        $tagLength  = 16;
        $cLen       = strlen($ciphertextDecoded);
        $cipherData = substr($ciphertextDecoded, 0, $cLen - $tagLength);
        $tag        = substr($ciphertextDecoded, $cLen - $tagLength);

        $decrypted = openssl_decrypt(
            $cipherData,
            'aes-256-gcm',
            $apiV3Key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $associatedData
        );

        if ($decrypted === false) {
            throw new WechatException('通知资源解密失败');
        }

        $result = json_decode($decrypted, true);

        if (!is_array($result)) {
            throw new WechatException('通知资源解密后 JSON 解析失败');
        }

        return $result;
    }

    // =========================================================
    // 工具方法
    // =========================================================

    /**
     * 构建 JSAPI / 小程序 前端调起支付的参数（含签名）
     *
     * @param string $prepayId 预支付 ID
     * @return array{appId:string,timeStamp:string,nonceStr:string,package:string,signType:string,paySign:string} 按微信规则生成的前端支付应用标识、时间戳、随机串、预支付包及签名。
     * @throws WechatException
     */
    protected function buildJsApiCallParams(string $prepayId): array
    {
        $config    = is_array($this->config) ? $this->config : call_user_func($this->config);
        $appId     = $config['app_id'] ?? '';
        $timestamp = (string) time();
        $nonceStr  = $this->generateNonceStr();
        $package   = 'prepay_id=' . $prepayId;

        // 按微信 v3 规范拼接待签名字符串
        $message   = $appId . "\n" . $timestamp . "\n" . $nonceStr . "\n" . $package . "\n";

        try {
            $paySign = $this->signMessage($message);
        } catch (\Throwable $e) {
            throw new WechatException('生成支付签名失败：' . $e->getMessage());
        }

        return [
            'appId'     => $appId,
            'timeStamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'package'   => $package,
            'signType'  => 'RSA',
            'paySign'   => $paySign,
        ];
    }

    /**
     * 构建 v3 请求选项。
     *
     * 当项目仅保存商户证书序列号而未保存商户证书内容时，
     * 通过手动注入 Authorization 头兼容 EasyWeChat 的签名流程。
     *
     * @param string     $method 参与微信请求签名的 HTTP 方法。
     * @param string     $url    请求地址
     * @param array|null $data 可选的 JSON 请求数据；空值表示不发送请求体。
     * @param array      $query  查询参数
     * @return array<string, mixed> 包含查询参数、原始 JSON 请求体及可选手动授权签名头的 HTTP 客户端选项。
     * @throws WechatException
     */
    protected function buildV3RequestOptions(string $method, string $url, ?array $data = null, array $query = []): array
    {
        $options = [];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if ($data !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = $this->encodeJsonBody($data);
        }

        $authorization = $this->buildAuthorizationHeader(
            $method,
            $url,
            $query,
            (string) ($options['body'] ?? '')
        );

        if ($authorization !== null) {
            $options['headers']['Authorization'] = $authorization;
        }

        return $options;
    }

    /**
     * 构建退款请求商户证书选项。
     *
     * @return array<string,string> 同时适配两种 HTTP 客户端的商户证书及私钥文件选项，配置或文件缺失时抛出异常。
     */
    protected function buildRefundCertificateOptions(): array
    {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);
        $cert = $this->resolveCertificatePath((string) ($config['cert'] ?? $config['local_cert'] ?? ''));
        $sslKey = $this->resolveCertificatePath((string) ($config['ssl_key'] ?? $config['local_pk'] ?? ''));
        if ($sslKey === '') {
            $privateKey = (string) ($config['private_key'] ?? '');
            $sslKey = $this->looksLikeFilePath($privateKey) ? $this->resolveCertificatePath($privateKey) : '';
        }

        if ($cert === '' || $sslKey === '') {
            throw new WechatException('微信退款证书配置缺少：请填写 cert 和 ssl_key 文件路径');
        }
        if (!is_file($cert)) {
            throw new WechatException('微信退款商户证书文件不存在：' . $cert);
        }
        if (!is_file($sslKey)) {
            throw new WechatException('微信退款商户私钥文件不存在：' . $sslKey);
        }

        $options = [];
        if ($cert !== '') {
            $options['cert'] = $cert;
            $options['local_cert'] = $cert;
        }

        if ($sslKey !== '') {
            $options['ssl_key'] = $sslKey;
            $options['local_pk'] = $sslKey;
        }

        return $options;
    }

    /**
     * 将证书文件路径解析为绝对路径。
     *
     * @param string $path 原始路径。
     * @return string 按证书存储规则选择的绝对路径；空值、PEM 文本或无路径的链接返回空字符串。
     */
    protected function resolveCertificatePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, '-----BEGIN ')) {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($urlPath) ? $urlPath : '';
            if ($path === '') {
                return '';
            }
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $relativePath = ltrim($normalizedPath, '/');
        $isPaymentCertStoragePath = str_starts_with($relativePath, self::PAYMENT_CERT_BASE_PATH . '/');
        $isPublicStoragePath = str_starts_with($normalizedPath, '/storage/')
            || str_starts_with($normalizedPath, 'storage/');

        if ($this->isAbsolutePath($path) && !$isPublicStoragePath) {
            return $path;
        }

        $rootPath = rtrim(root_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
        $publicPath = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
        if ($isPaymentCertStoragePath) {
            $candidates = [$rootPath, $publicPath];
        } else {
            $candidates = str_starts_with($relativePath, 'storage/')
                ? [$publicPath, $rootPath]
                : [$rootPath, $publicPath];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * 判断字符串是否像文件路径。
     *
     * @param string $value 待判断内容。
     * @return bool 内容是否符合无换行、非 PEM 且带路径分隔符或证书后缀的文件路径特征。
     */
    protected function looksLikeFilePath(string $value): bool
    {
        $value = trim($value);

        return $value !== ''
            && !str_contains($value, "\n")
            && !str_contains($value, '-----BEGIN ')
            && (str_contains($value, '/') || str_contains($value, '\\') || str_ends_with($value, '.pem'));
    }

    /**
     * 判断路径是否为绝对路径。
     *
     * @param string $path 文件路径。
     * @return bool 是否为根路径或带盘符的绝对文件路径。
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    /**
     * 生成微信支付 v3 Authorization 头。
     *
     * 若已配置商户证书序列号，则直接使用序列号和商户私钥签名，
     * 避免 SDK 在缺少商户证书内容时读取失败。
     *
     * @param string $method 参与微信请求签名的 HTTP 方法。
     * @param string $url    请求地址
     * @param array  $query  查询参数
     * @param string $body 与实际发送内容完全一致的原始 JSON 请求体字符串。
     * @return string|null 使用商户证书序列号与私钥生成的授权签名头，未配置序列号时为空值并由 SDK 处理。
     * @throws WechatException
     */
    protected function buildAuthorizationHeader(string $method, string $url, array $query, string $body): ?string
    {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);
        $serialNo = trim((string) ($config['mch_serial_no'] ?? $config['merchant_serial_no'] ?? $config['serial_no'] ?? ''));
        if ($serialNo === '') {
            return null;
        }

        $mchId = trim((string) ($config['mch_id'] ?? ''));
        if ($mchId === '') {
            throw new WechatException('微信支付配置缺少：mch_id');
        }

        $uri = new Uri($url);
        parse_str($uri->getQuery(), $originalQuery);
        if ($query !== []) {
            $uri = $uri->withQuery(http_build_query(array_merge($originalQuery, $query)));
        }

        $queryString = $uri->getQuery();
        $path = '/' . ltrim($uri->getPath() . ($queryString === '' ? '' : '?' . $queryString), '/');
        $timestamp = (string) time();
        $nonceStr = $this->generateNonceStr();
        $message = strtoupper($method) . "\n"
            . $path . "\n"
            . $timestamp . "\n"
            . $nonceStr . "\n"
            . $body . "\n";

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%s",serial_no="%s",signature="%s"',
            $mchId,
            $nonceStr,
            $timestamp,
            $serialNo,
            $this->signMessage($message)
        );
    }

    /**
     * 对微信 v3 JSON 请求体做与 EasyWeChat 一致的编码。
     *
     * @param array $data 请求数据
     * @return string 与客户端编码规则一致的 JSON 请求体，空数组编码为对象，编码失败时抛出异常。
     * @throws WechatException
     */
    protected function encodeJsonBody(array $data): string
    {
        $body = json_encode($data, empty($data) ? JSON_FORCE_OBJECT : JSON_UNESCAPED_UNICODE);
        if (!is_string($body)) {
            throw new WechatException('微信支付请求体编码失败');
        }

        return $body;
    }

    /**
     * 读取私钥内容并生成签名。
     *
     * @param string $message 待签名字符串
     * @return string 使用商户私钥生成并经过 Base64 编码的微信请求或前端调起签名。
     * @throws WechatException
     */
    protected function signMessage(string $message): string
    {
        $config = is_array($this->config) ? $this->config : call_user_func($this->config);
        $privateKey = $this->readKeyContents((string) ($config['private_key'] ?? ''));
        if ($privateKey === '') {
            throw new WechatException('微信支付配置缺少：private_key');
        }

        $rawSign = '';
        $signed = openssl_sign($message, $rawSign, $privateKey, 'sha256WithRSAEncryption');
        if ($signed !== true) {
            throw new WechatException('微信商户私钥签名失败，请检查 private_key 与 mch_serial_no 配置');
        }

        return base64_encode($rawSign);
    }

    /**
     * 兼容“文件路径 / PEM 文本”两种密钥输入。
     *
     * @param string $value 密钥配置值
     * @return string 存在的密钥文件内容，否则保留原始配置文本交由签名或验签处理。
     */
    protected function readKeyContents(string $value): string
    {
        if ($value !== '' && is_file($value)) {
            return (string) file_get_contents($value);
        }

        $path = $this->resolveCertificatePath($value);
        if ($path !== '' && is_file($path)) {
            return (string) file_get_contents($path);
        }

        return $value;
    }
}
