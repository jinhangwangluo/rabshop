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

namespace app\common\service\plugin;

use app\common\exception\PluginSecurityException;
use app\common\service\plugin\contract\PluginGatewayTransportInterface;

/**
 * 基于 cURL 的 HTTPS 插件网关传输适配器。
 *
 * 适配器只传输已经完成 JWS/JWE 封装的字符串，不负责解析或记录业务载荷。
 */
final class CurlPluginGatewayTransport implements PluginGatewayTransportInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
        private readonly int $connectTimeoutSeconds = 3,
        private readonly bool $verifyPeer = true
    ) {
    }

    /**
     * 发送一份 application/jose 请求并返回同样的加密响应。
     *
     * @param array<string,string> $headers 仅允许非敏感协议头；业务字段必须留在密文中。
     * @param array<string,mixed> $options 可覆盖 timeout/connect_timeout/verify_peer。
     * @throws PluginSecurityException HTTPS、cURL 或非 2xx 响应时抛出。
     */
    public function request(
        string $url,
        string $body,
        array $headers = [],
        array $options = []
    ): string {
        $this->assertHttpsUrl($url);
        if ($body === '') {
            throw new PluginSecurityException('安全网关请求体不能为空');
        }
        if (!function_exists('curl_init')) {
            throw new PluginSecurityException('HTTPS 传输组件不可用');
        }

        $timeout = $this->boundedTimeout($options['timeout'] ?? $this->timeoutSeconds, 1, 10);
        $connectTimeout = $this->boundedTimeout(
            $options['connect_timeout'] ?? $this->connectTimeoutSeconds,
            1,
            $timeout
        );
        $verifyPeer = array_key_exists('verify_peer', $options)
            ? (bool)$options['verify_peer']
            : $this->verifyPeer;
        if ($verifyPeer === false) {
            throw new PluginSecurityException('安全网关禁止关闭 TLS 证书校验');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new PluginSecurityException('HTTPS 请求初始化失败');
        }

        $wireHeaders = [
            'Content-Type: application/jose',
            'Accept: application/jose',
        ];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)
                || preg_match('/^[A-Za-z0-9-]{1,64}$/', $name) !== 1
                || preg_match('/[\r\n]/', $value) === 1
            ) {
                curl_close($curl);
                throw new PluginSecurityException('安全网关协议头无效');
            }
            // 调用方不能覆盖内容类型或 Host，避免把密文发送到错误协议。
            if (in_array(strtolower($name), ['content-type', 'accept', 'host', 'content-length'], true)) {
                continue;
            }
            $wireHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $wireHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
            CURLOPT_HEADER => false,
        ]);

        $response = curl_exec($curl);
        $errorNo = curl_errno($curl);
        $errorMessage = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower(trim(explode(';', (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE), 2)[0]));
        curl_close($curl);

        if ($response === false || $errorNo !== 0) {
            // 只保留脱敏后的 cURL 诊断信息，不回显 URL、请求体或网关返回内容。
            throw new PluginSecurityException($this->formatCurlFailure($errorNo, $errorMessage));
        }
        $response = (string)$response;
        if ($status < 200 || $status >= 300) {
            $remoteMessage = $this->extractRemoteMessage($response);
            if ($remoteMessage !== null) {
                throw new PluginSecurityException(
                    '安全网关返回业务错误（HTTP ' . $status . '）：' . $remoteMessage
                );
            }
            throw new PluginSecurityException('安全网关返回状态无效（HTTP ' . $status . '）');
        }
        if ($response === '') {
            throw new PluginSecurityException('安全网关响应为空');
        }
        if ($contentType !== 'application/jose') {
            $remoteError = json_decode($response, true);
            if (is_array($remoteError) && isset($remoteError['message']) && is_scalar($remoteError['message'])) {
                $message = trim((string)$remoteError['message']);
                $message = preg_replace('/[\x00-\x1F\x7F]/', '', $message) ?? '';
                if ($message !== '') {
                    throw new PluginSecurityException(
                        '安全网关返回业务错误（HTTP ' . $status . '）：'
                        . mb_substr($message, 0, 200)
                    );
                }
            }
            // 只报告有限的协议元数据，不能把未验签的错误正文当成可信业务响应。
            $reportedType = preg_match('~^[a-z0-9.+-]{1,64}/[a-z0-9.+-]{1,64}$~D', $contentType) === 1
                ? $contentType
                : '缺失或无效';
            throw new PluginSecurityException(
                '安全网关响应类型错误（HTTP ' . $status . '，Content-Type: ' . $reportedType
                . '），预期 application/jose；请检查开放平台网关地址和路由配置'
            );
        }
        return $response;
    }

    /**
     * 生成不包含地址和密文的 cURL 失败信息，便于区分超时、DNS 和连接失败。
     *
     * @param int $errorNo cURL 错误编号。
     * @param string $errorMessage cURL 原始错误描述。
     * @return string 脱敏后的安全网关错误消息。
     */
    private function formatCurlFailure(int $errorNo, string $errorMessage): string
    {
        $message = trim((string)(preg_replace('/[\x00-\x1F\x7F]/', ' ', $errorMessage) ?? ''));
        $message = preg_replace('~https?://\S+~i', '[endpoint]', $message) ?? '';
        $message = trim(mb_substr($message, 0, 160));
        if ($message === '') {
            $message = '未知 cURL 错误';
        }
        return '安全网关传输失败（cURL ' . $errorNo . '：' . $message . '）';
    }

    /**
     * 从未加密的远端 JSON 错误中提取有限长度的可读信息。
     *
     * @param string $response 远端响应正文。
     * @return string|null 脱敏后的错误消息；无法解析时返回 null。
     */
    private function extractRemoteMessage(string $response): ?string
    {
        $decoded = json_decode($response, true);
        $message = is_array($decoded) && isset($decoded['message']) && is_scalar($decoded['message'])
            ? trim((string)$decoded['message'])
            : '';
        $message = preg_replace('/[\x00-\x1F\x7F]/', '', $message) ?? '';
        return $message !== '' ? mb_substr($message, 0, 200) : null;
    }

    private function assertHttpsUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || trim((string)($parts['host'] ?? '')) === ''
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new PluginSecurityException('安全网关必须使用无查询参数的 HTTPS 地址');
        }
    }

    private function boundedTimeout(mixed $value, int $minimum, int $maximum): int
    {
        if (!is_int($value) && !is_numeric($value)) {
            throw new PluginSecurityException('安全网关超时参数无效');
        }
        return max($minimum, min($maximum, (int)$value));
    }
}
