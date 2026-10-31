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

namespace Rabbit\service\express\driver;

use app\common\enum\ConfigKeyEnum;
use Rabbit\service\express\BaseDriver;
use Rabbit\service\express\ExpressException;
use Rabbit\service\express\ExpressService;

/**
 * 阿里云市场物流查询驱动
 */
class AliyunMarket extends BaseDriver
{
    private const API_HOST = 'https://kzexpress.market.alicloudapi.com';
    private const API_PATH = '/api-mall/api/express/query';

    private string $appcode = '';

    public function __construct(array $config = [])
    {
        if ($config) {
            $this->appcode = trim((string)($config['appcode'] ?? $config[ConfigKeyEnum::EXPRESS_QUERY_APPCODE] ?? ''));
        }
    }

    public function getProvider(): string
    {
        return ExpressService::PROVIDER_ALIYUN_MARKET;
    }

    public function query(string $expressNo, string $cpCode, string $mobile = ''): array
    {
        $expressNo = trim($expressNo);
        $cpCode = strtoupper(trim($cpCode));
        $mobile = trim($mobile);
        if ($expressNo === '') {
            throw new ExpressException('请输入快递单号');
        }
        if ($cpCode === '') {
            throw new ExpressException('请选择快递公司');
        }

        $this->ensureConfig();
        $response = $this->httpFormPost(self::API_HOST . self::API_PATH, [
            'expressNo' => $expressNo,
            'mobile' => $mobile,
            'cpCode' => $cpCode,
        ], [
            'Authorization' => 'APPCODE ' . $this->appcode,
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ]);

        return $this->normalizeResponse($response);
    }

    private function ensureConfig(): void
    {
        if ($this->appcode === '') {
            $this->appcode = trim((string)$this->cfg(ConfigKeyEnum::EXPRESS_QUERY_APPCODE));
        }
        if ($this->appcode === '') {
            throw new ExpressException('未配置物流查询 AppCode，请在「快递公司」中完善配置');
        }
    }

    private function normalizeResponse(array $response): array
    {
        $data = $this->extractPayload($response);
        $traces = $this->extractTraceList($response);

        return [
            'provider' => $this->getProvider(),
            'state' => (string)($data['state'] ?? $data['status'] ?? $data['deliverystatus'] ?? $data['issign'] ?? ''),
            'message' => $this->extractMessage($response),
            'traces' => $traces,
            'raw' => $response,
        ];
    }

    private function extractPayload(array $response): array
    {
        foreach (['data', 'result', 'body', 'showapi_res_body'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return $response;
    }

    private function extractMessage(array $response): string
    {
        foreach (['msg', 'message', 'reason', 'statusText', 'showapi_res_error'] as $key) {
            if (isset($response[$key]) && trim((string)$response[$key]) !== '') {
                return (string)$response[$key];
            }
        }

        $payload = $this->extractPayload($response);
        foreach (['msg', 'message', 'reason', 'statusText'] as $key) {
            if (isset($payload[$key]) && trim((string)$payload[$key]) !== '') {
                return (string)$payload[$key];
            }
        }

        return '';
    }

    private function extractTraceList(array $response): array
    {
        $candidates = [
            $response['data']['result']['list'] ?? null,
            $response['data']['result']['data'] ?? null,
            $response['data']['list'] ?? null,
            $response['data']['traces'] ?? null,
            $response['data']['trace'] ?? null,
            $response['data']['data'] ?? null,
            $response['result']['list'] ?? null,
            $response['result']['data'] ?? null,
            $response['result'] ?? null,
            $response['showapi_res_body']['data'] ?? null,
            $response['showapi_res_body']['list'] ?? null,
            $response['list'] ?? null,
            $response['traces'] ?? null,
            $response['trace'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && $this->isTraceList($candidate)) {
                return $candidate;
            }
        }

        return $this->findTraceList($response);
    }

    private function findTraceList(array $data): array
    {
        if ($this->isTraceList($data)) {
            return $data;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }
            $matched = $this->findTraceList($value);
            if ($matched !== []) {
                return $matched;
            }
        }

        return [];
    }

    private function isTraceList(array $items): bool
    {
        $first = reset($items);
        if (!is_array($first)) {
            return false;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                return false;
            }
            if ($this->hasTraceField($item)) {
                return true;
            }
        }

        return false;
    }

    private function hasTraceField(array $item): bool
    {
        $timeKeys = ['time', 'ftime', 'acceptTime', 'AcceptTime', 'date', 'datetime', 'createTime'];
        $contentKeys = ['context', 'status', 'acceptStation', 'AcceptStation', 'desc', 'description', 'remark', 'message', 'content'];

        foreach (array_merge($timeKeys, $contentKeys) as $key) {
            if (isset($item[$key]) && trim((string)$item[$key]) !== '') {
                return true;
            }
        }

        return false;
    }
}
