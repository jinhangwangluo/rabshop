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

namespace Rabbit\service\live;

use Rabbit\interfaces\ConfigInterface;
use Rabbit\util\Curl;

/**
 * 直播驱动基类
 * 提供通用工具方法：HMAC 签名、HTTP 请求封装、流名生成等
 * Class BaseDriver
 * @package Rabbit\service\live
 */
abstract class BaseDriver implements LiveInterface
{
    /**
     * 配置服务（读取 db_config）
     * @var ConfigInterface|null
     */
    protected ?ConfigInterface $configServe = null;

    /**
     * 推流域名
     * @var string
     */
    protected string $pushDomain = '';

    /**
     * 拉流域名
     * @var string
     */
    protected string $pullDomain = '';

    /**
     * 直播应用/Hub 名称
     * @var string
     */
    protected string $appName = 'live';

    /**
     * 鉴权有效秒数
     * @var int
     */
    protected int $expireSeconds = 7200;

    /**
     * 设置配置服务
     */
    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    /**
     * 获取当前驱动标识
     */
    abstract public function getProvider(): string;

    /**
     * 从配置服务读取单个值
     */
    protected function cfg(string $key, mixed $default = ''): mixed
    {
        if ($this->configServe === null) {
            return $default;
        }
        $val = $this->configServe->getValue($key);
        return $val ?? $default;
    }

    /**
     * 生成全局唯一流名称
     * @param int $roomId
     * @return string
     */
    public function generateStreamName(int $roomId): string
    {
        return 'room_' . $roomId . '_' . time();
    }

    /**
     * 规范化域名（去除末尾斜杠，自动补协议前缀）
     * @param string $domain
     * @param string $scheme rtmp | http | https
     * @return string
     */
    protected function normalizeDomain(string $domain, string $scheme = 'rtmp'): string
    {
        $domain = rtrim($domain, '/');
        if ($domain && !str_contains($domain, '://')) {
            $domain = $scheme . '://' . $domain;
        }
        return $domain;
    }

    /**
     * 将推流 URL 拆分为基础地址 + 鉴权 Key
     * @param string $fullUrl 完整推流 URL（含 ? 参数）
     * @return array{push_base: string, push_key: string}
     */
    protected function splitPushUrl(string $fullUrl): array
    {
        if (str_contains($fullUrl, '?')) {
            [$base, $key] = explode('?', $fullUrl, 2);
        } else {
            $base = $fullUrl;
            $key  = '';
        }
        return ['push_base' => $base, 'push_key' => $key];
    }

    /**
     * 发起 HTTP GET 请求（通过项目 Curl 工具）
     * @param string $url
     * @param array  $params  查询参数
     * @param array  $headers 请求头
     * @return array
     * @throws LiveException
     */
    protected function httpGet(string $url, array $params = [], array $headers = []): array
    {
        $curl = app()->make(Curl::class);
        $resp = $curl->request($url, 'GET', $params, $headers);
        if ($resp === false) {
            throw new LiveException("HTTP GET 请求失败：{$url}");
        }
        return $resp ? (json_decode($resp, true) ?? []) : [];
    }

    /**
     * 发起 HTTP POST 请求
     * @param string       $url
     * @param string|array $body    JSON 字符串或数组（自动序列化）
     * @param array        $headers
     * @return array
     * @throws LiveException
     */
    protected function httpPost(string $url, string|array $body = '', array $headers = []): array
    {
        if (is_array($body)) {
            $body = json_encode($body);
        }
        $curl = app()->make(Curl::class);
        $resp = $curl->request($url, 'POST', $body, $headers);
        if ($resp === false) {
            throw new LiveException("HTTP POST 请求失败：{$url}");
        }
        return $resp ? (json_decode($resp, true) ?? []) : [];
    }

    /**
     * HMAC-SHA1 签名（URL-safe Base64）
     * @param string $data
     * @param string $secret
     * @return string
     */
    protected function hmacSha1(string $data, string $secret): string
    {
        $sign = base64_encode(hash_hmac('sha1', $data, $secret, true));
        return strtr($sign, ['+' => '-', '/' => '_']);
    }

    /**
     * HMAC-SHA256 签名（标准 Base64）
     * @param string $data
     * @param string $secret
     * @return string
     */
    protected function hmacSha256(string $data, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $data, $secret, true));
    }

    /**
     * 将时间字符串转换为时间戳
     * 支持：Unix 时间戳字符串、'Y-m-d H:i:s' 格式
     * @param string $time
     * @return int
     */
    protected function toTimestamp(string $time): int
    {
        if (ctype_digit($time)) {
            return (int)$time;
        }
        return strtotime($time) ?: time();
    }
}
