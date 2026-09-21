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
use app\common\service\plugin\contract\PluginGatewayKeyDirectoryInterface;

/**
 * 基于 cURL 的官网网关公钥目录读取器。
 *
 * 公钥目录只通过已校验证书的 HTTPS GET 读取，不接受重定向，也不把
 * 官网返回的原始内容拼接进异常，避免错误信息泄露远端响应。
 */
final class CurlPluginGatewayKeyDirectory implements PluginGatewayKeyDirectoryInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
        private readonly int $connectTimeoutSeconds = 3,
        private readonly bool $verifyPeer = true
    ) {
    }

    /**
     * 读取并解析官网网关公钥目录。
     *
     * @param array<string,mixed> $options 可覆盖 timeout/connect_timeout/verify_peer。
     * @return array<string,mixed>
     * @throws PluginSecurityException HTTPS、cURL、状态码或 JSON 响应无效时抛出。
     */
    public function fetch(string $url, array $options = []): array
    {
        $this->assertHttpsUrl($url);
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

        curl_setopt_array($curl, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
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
        curl_close($curl);

        if ($response === false || $errorNo !== 0) {
            $message = trim((string)(preg_replace('/[\x00-\x1F\x7F]/', ' ', $errorMessage) ?? ''));
            $message = preg_replace('~https?://\S+~i', '[endpoint]', $message) ?? '';
            $message = trim(mb_substr($message, 0, 160));
            throw new PluginSecurityException(
                '官网密钥目录请求失败（cURL ' . $errorNo . '：' . ($message !== '' ? $message : '未知 cURL 错误') . '）'
            );
        }
        if ($status < 200 || $status >= 300) {
            throw new PluginSecurityException('官网密钥目录返回状态无效（HTTP ' . $status . '）');
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new PluginSecurityException('官网密钥目录响应无效');
        }
        return $decoded;
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
            throw new PluginSecurityException('官网密钥目录必须使用无查询参数的 HTTPS 地址');
        }
    }

    private function boundedTimeout(mixed $value, int $minimum, int $maximum): int
    {
        if (!is_int($value) && !is_numeric($value)) {
            throw new PluginSecurityException('官网密钥目录超时参数无效');
        }
        return max($minimum, min($maximum, (int)$value));
    }
}
