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

namespace Rabbit\service\collector\driver;

use app\common\enum\ConfigKeyEnum;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Rabbit\service\collector\BaseDriver;
use Rabbit\service\collector\CollectorException;

/**
 * 万邦 / OneBound 商品采集驱动。
 *
 * 该驱动只负责封装万邦通用请求层，淘宝、京东等平台能力以后都在这里继续扩展。
 */
class OneBound extends BaseDriver
{
    /**
     * 万邦接口域名。
     */
    private const HOST = 'https://api-gw.onebound.cn';

    /**
     * 默认服务商标识。
     */
    public function getProvider(): string
    {
        return 'wanbang';
    }

    /**
     * 通过商品链接采集淘宝商品详情。
     *
     * @param string $url 淘宝商品链接或商品 ID。
     * @return array<string,mixed>
     * @throws CollectorException 采集失败时抛出。
     */
    public function collectByUrl(string $url): array
    {
        $platform = $this->getPlatformByUrl($url);
        return $this->collectByPlatform($platform, $this->resolveNumIid($url, $platform));
    }

    /**
     * 采集淘宝商品详情。
     *
     * @param string $numIid 淘宝商品 ID。
     * @return array<string,mixed>
     * @throws CollectorException 采集失败时抛出。
     */
    public function collect(string $numIid): array
    {
        return $this->collectByPlatform('taobao', $numIid);
    }

    /**
     * 按平台和商品 ID 采集商品详情。
     *
     * 淘宝使用 item_get，京东使用 item_get_pro。
     *
     * @param string $platform 平台标识，例如 taobao、jd。
     * @param string $numIid 第三方商品 ID。
     * @return array<string,mixed>
     * @throws CollectorException 采集失败时抛出。
     */
    public function collectByPlatform(string $platform, string $numIid): array
    {
        $platform = strtolower(trim($platform));
        $numIid = trim($numIid);
        if (!preg_match('/^\d+$/', $numIid)) {
            throw new CollectorException('请输入正确的商品 ID');
        }

        $apiName = match ($platform) {
            'taobao' => 'item_get',
            'jd' => 'item_get_pro',
            default => throw new CollectorException('暂不支持该商品平台'),
        };

        $query = [
            'num_iid' => $numIid,
            ...($platform === 'taobao' ? ['is_promotion' => '1'] : []),
        ];
        $payload = $this->request($platform, $apiName, $query);

        $data = $payload['data'] ?? [];
        $item = $payload['item'] ?? (is_array($data) ? ($data['item'] ?? []) : []);
        if (is_array($item) && array_is_list($item)) {
            $item = $item[0] ?? [];
        }
        if (!is_array($item) || trim((string) ($item['title'] ?? '')) === '') {
            throw new CollectorException('未获取到商品详情，请确认商品 ID 正确且商品可采集');
        }

        $description = $item['desc'] ?? '';
        $description = is_scalar($description) ? trim((string) $description) : '';
        if ($platform === 'jd' && $description === '') {
            $item['desc'] = $this->collectJdDescription($numIid, $query);
        }

        return $item;
    }

    /**
     * 从商品链接识别平台。
     *
     * @param string $url 第三方商品链接或商品 ID。
     * @return string
     * @throws CollectorException 链接无法识别时抛出。
     */
    public function getPlatformByUrl(string $url): string
    {
        $value = trim($url);
        if ($value === '' || preg_match('/^\d+$/', $value)) {
            return 'taobao';
        }

        $normalized = $this->normalizeSourceUrl($value);
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        if (
            str_contains($host, 'jd.com')
            || str_contains($host, '3.cn')
            || $host === 'jd.cn'
        ) {
            return 'jd';
        }
        if (
            str_contains($host, 'taobao.com')
            || str_contains($host, 'tmall.com')
            || str_contains($host, 'tb.cn')
        ) {
            return 'taobao';
        }

        throw new CollectorException('暂不支持该商品链接，请输入淘宝或京东商品链接');
    }

    /**
     * 调用万邦接口。
     *
     * 返回万邦原始响应，不绑定商品详情字段，方便后续扩展审核详情、
     * 店铺详情和其他平台接口。
     *
     * @param string $platform 平台标识，例如 taobao。
     * @param string $apiName 接口名，例如 item_get。
     * @param array<string,mixed> $query 业务查询参数。
     * @return array<string,mixed>
     * @throws CollectorException 采集失败时抛出。
     */
    public function request(string $platform, string $apiName, array $query = []): array
    {
        $platform = trim($platform);
        $apiName = trim($apiName);
        if (
            $platform === ''
            || $apiName === ''
            || preg_match('/^[a-z0-9_]+$/i', $platform) !== 1
            || preg_match('/^[a-z0-9_]+$/i', $apiName) !== 1
        ) {
            throw new CollectorException('请填写正确的万邦平台和接口名');
        }

        $enabled = (int) $this->config(ConfigKeyEnum::PRODUCT_COLLECTOR_ENABLED);
        if ($enabled !== 1) {
            throw new CollectorException('商品采集功能未开启');
        }

        $key = trim((string) $this->config(ConfigKeyEnum::PRODUCT_COLLECTOR_KEY));
        $secret = trim((string) $this->config(ConfigKeyEnum::PRODUCT_COLLECTOR_SECRET));
        if ($key === '' || $secret === '') {
            throw new CollectorException('请先在系统配置中填写商品采集 Key 和 Secret');
        }

        $params = $query;
        $params['key'] = $key;
        $params['secret'] = $secret;
        $params['api_name'] = $apiName;
        $params['cache'] = $params['cache'] ?? 'yes';
        $params['result_type'] = $params['result_type'] ?? 'json';
        $params['lang'] = $params['lang'] ?? 'cn';

        try {
            $response = (new Client([
                'timeout' => 30,
                'connect_timeout' => 10,
            ]))->get(self::HOST . '/' . $platform . '/' . $apiName, [
                'query' => $params,
            ]);
        } catch (GuzzleException $exception) {
            throw new CollectorException('商品采集服务请求失败，请稍后重试', 0, $exception);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new CollectorException('商品采集服务返回异常，请稍后重试');
        }

        try {
            $payload = json_decode(
                (string) $response->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new CollectorException('商品采集服务返回数据无法解析', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new CollectorException('商品采集服务返回数据格式错误');
        }

        $errorCode = trim((string) ($payload['error_code'] ?? ''));
        if ($errorCode !== '' && $errorCode !== '0000') {
            $error = $payload['reason'] ?? ($payload['error'] ?? ($payload['message'] ?? ($payload['msg'] ?? '')));
            $error = is_scalar($error) ? trim((string) $error) : '未知错误';
            throw new CollectorException('商品采集失败：' . $error);
        }

        $error = $payload['error'] ?? ($payload['message'] ?? ($payload['msg'] ?? ''));
        $error = is_scalar($error) ? trim((string) $error) : '';
        if ($error !== '') {
            throw new CollectorException('商品采集失败：' . $error);
        }

        return $payload;
    }

    /**
     * 单独采集京东商品详情富文本。
     *
     * @param string $numIid 京东商品 ID。
     * @param array<string,mixed> $query 商品详情查询参数。
     * @return string 详情 HTML。
     */
    protected function collectJdDescription(string $numIid, array $query): string
    {
        try {
            $payload = $this->request('jd', 'item_get_desc', [
                ...$query,
                'main_sku_id' => $numIid,
                'cache' => 'no',
            ]);
        } catch (CollectorException) {
            return '';
        }

        $data = $payload['data'] ?? [];
        $item = $payload['item'] ?? (is_array($data) ? ($data['item'] ?? []) : []);
        if (is_array($item) && array_is_list($item)) {
            $item = $item[0] ?? [];
        }

        $description = is_array($item) ? ($item['desc'] ?? '') : '';
        return is_scalar($description) ? trim((string) $description) : '';
    }

    /**
     * 从淘宝链接中解析商品 ID。
     *
     * @param string $url 淘宝商品链接或商品 ID。
     * @return string
     * @throws CollectorException 解析失败时抛出。
     */
    protected function resolveNumIid(string $url, string $platform = 'taobao'): string
    {
        $value = trim($url);
        if ($value === '') {
            throw new CollectorException('请输入正确的商品链接');
        }

        if (preg_match('/^\d+$/', $value)) {
            return $value;
        }

        $parts = parse_url($this->normalizeSourceUrl($value));
        if (is_array($parts)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $queryKeys = $platform === 'jd'
                ? ['sku', 'sku_id', 'num_iid', 'id']
                : ['num_iid', 'id'];
            foreach ($queryKeys as $key) {
                if (!empty($query[$key]) && preg_match('/^\d+$/', (string) $query[$key])) {
                    return (string) $query[$key];
                }
            }

            $path = (string) ($parts['path'] ?? '');
            if ($platform === 'jd' && preg_match('#/(\d+)\.html#i', $path, $matches)) {
                return $matches[1];
            }
            if (preg_match('/(\d{6,})/', $path, $matches)) {
                return $matches[1];
            }
        }

        if (preg_match('/(\d{6,})/', $value, $matches)) {
            return $matches[1];
        }

        throw new CollectorException('请输入正确的商品链接');
    }

    /**
     * 标准化无协议商品链接。
     *
     * @param string $url 原始链接。
     * @return string
     */
    protected function normalizeSourceUrl(string $url): string
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $url)
            ? $url
            : 'https://' . ltrim($url, '/');
    }
}
