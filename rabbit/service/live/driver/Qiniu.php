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

namespace Rabbit\service\live\driver;

use app\common\enum\LiveConfigKeyEnum;
use Qiniu\Auth;
use Qiniu\Http\Client;
use Qiniu\Http\Error;
use Qiniu\Storage\BucketManager;
use Qiniu\Config as QiniuConfig;
use Rabbit\service\live\BaseDriver;
use Rabbit\service\live\LiveException;

/**
 * 七牛云直播驱动（Pili 直播）
 *
 * 配置键（对应 db_config key）：
 *   live_qiniu_access_key      AccessKey
 *   live_qiniu_secret_key      SecretKey
 *   live_qiniu_hub             直播空间（Hub）名称
 *   live_qiniu_push_domain     推流域名
 *   live_qiniu_pull_domain     拉流域名
 *   live_qiniu_expire_seconds  URL 鉴权有效秒数（默认 7200）
 *   live_qiniu_record_bucket   回放录制存储的 Kodo Bucket 名称
 *   live_qiniu_record_region   回放录制存储的 Kodo 地域
 *
 * Class Qiniu
 * @package Rabbit\service\live\driver
 */
class Qiniu extends BaseDriver
{
    /** 七牛 Pili 直播 API 基地址 */
    private const PILI_API = 'http://pili.qiniuapi.com';

    /** @var string AccessKey */
    private string $accessKey = '';

    /** @var string SecretKey */
    private string $secretKey = '';

    /** @var string 直播 Hub 名称 */
    private string $hub = 'live';

    /** @var string 回放存储 Bucket */
    private string $recordBucket = '';

    /** @var string 回放存储地域 */
    private string $recordRegion = 'z0';

    // ==================== 初始化 ====================

    public function __construct(array $config = [])
    {
        if ($config) {
            $this->loadFromArray($config);
        }
    }

    private function loadFromArray(array $config): void
    {
        $this->accessKey     = $config['access_key']     ?? $config[LiveConfigKeyEnum::QINIU_ACCESS_KEY] ?? '';
        $this->secretKey     = $config['secret_key']     ?? $config[LiveConfigKeyEnum::QINIU_SECRET_KEY] ?? '';
        $this->hub           = $config['hub']            ?? $config[LiveConfigKeyEnum::QINIU_HUB]        ?? 'live';
        $this->appName       = $this->hub;
        $this->pushDomain    = $config['push_domain']    ?? $config[LiveConfigKeyEnum::QINIU_PUSH_DOMAIN]    ?? '';
        $this->pullDomain    = $config['pull_domain']    ?? $config[LiveConfigKeyEnum::QINIU_PULL_DOMAIN]    ?? '';
        $this->expireSeconds = (int)($config['expire_seconds'] ?? $config[LiveConfigKeyEnum::QINIU_EXPIRE_SECONDS] ?? 7200);
        $this->recordBucket  = $config['record_bucket']  ?? '';
        $this->recordRegion  = $config['record_region']  ?? 'z0';
    }

    private function boot(): void
    {
        if ($this->accessKey) {
            return;
        }
        $this->accessKey     = (string)$this->cfg(LiveConfigKeyEnum::QINIU_ACCESS_KEY);
        $this->secretKey     = (string)$this->cfg(LiveConfigKeyEnum::QINIU_SECRET_KEY);
        $this->hub           = (string)$this->cfg(LiveConfigKeyEnum::QINIU_HUB, 'live');
        $this->appName       = $this->hub;
        $this->pushDomain    = (string)$this->cfg(LiveConfigKeyEnum::QINIU_PUSH_DOMAIN);
        $this->pullDomain    = (string)$this->cfg(LiveConfigKeyEnum::QINIU_PULL_DOMAIN);
        $this->expireSeconds = (int)$this->cfg(LiveConfigKeyEnum::QINIU_EXPIRE_SECONDS, 7200);
    }

    private function ensureConfig(): void
    {
        $this->boot();
        if (!$this->accessKey || !$this->secretKey) {
            throw new LiveException('七牛云直播：未配置 AccessKey/SecretKey，请在「直播配置」中完善七牛云信息');
        }
        if (!$this->pushDomain || !$this->pullDomain) {
            throw new LiveException('七牛云直播：未配置推/拉流域名，请在「直播配置」中完善相关信息');
        }
    }

    /**
     * 初始化七牛 Auth 对象
     */
    private function auth(): Auth
    {
        return new Auth($this->accessKey, $this->secretKey);
    }

    // ==================== 推拉流 ====================

    /**
     * 获取驱动标识
     */
    public function getProvider(): string
    {
        return \Rabbit\service\live\LiveService::PROVIDER_QINIU;
    }

    /**
     * 生成推流地址
     * 格式：rtmp://{push_domain}/{hub}/{stream_name}?e={expireAt}&token={token}
     */
    public function buildPushUrl(string $streamName): string
    {
        return $this->buildPushUrlParts($streamName)['push_url'];
    }

    /**
     * 拆分推流地址：OBS「服务器」= push_base（至 Hub），「推流码」= push_key（流名?e=...&token=...）
     */
    public function buildPushUrlParts(string $streamName): array
    {
        $this->ensureConfig();
        $domain   = rtrim($this->pushDomain, '/');
        $hub      = $this->hub ?: 'live';
        $expireAt = time() + $this->expireSeconds;
        $path     = "/{$hub}/{$streamName}?e={$expireAt}";
        $token    = $this->hmacSha1($path, $this->secretKey);
        $query    = "e={$expireAt}&token={$this->accessKey}:{$token}";

        $pushBase = "rtmp://{$domain}/{$hub}";
        $pushKey  = "{$streamName}?{$query}";
        $pushUrl  = "{$pushBase}/{$streamName}?{$query}";

        return [
            'push_base' => $pushBase,
            'push_key'  => $pushKey,
            'push_url'  => $pushUrl,
        ];
    }

    /**
     * 生成拉流（播放）地址
     * 支持格式：flv（默认）| m3u8 | rtmp
     */
    public function buildPullUrl(string $streamName, string $format = 'flv'): string
    {
        $this->ensureConfig();
        $domain = rtrim($this->pullDomain, '/');
        $hub    = $this->hub ?: 'live';

        // 与推流路径一致：rtmp://推流域/{hub}/{stream}，拉流为 http(s)://播放域/{hub}/{stream}.flv
        return match ($format) {
            'm3u8'  => "http://{$domain}/{$hub}/{$streamName}.m3u8",
            'rtmp'  => "rtmp://{$domain}/{$hub}/{$streamName}",
            default => "http://{$domain}/{$hub}/{$streamName}.flv",
        };
    }

    // ==================== 流管理 ====================

    /**
     * 禁推直播流（永久禁用，disabledTill=-1）
     */
    public function forbidStream(string $streamName): bool
    {
        $this->ensureConfig();
        try {
            $hub          = $this->hub ?: 'live';
            $encodedTitle = base64_encode($streamName);
            $url          = self::PILI_API . "/v2/hubs/{$hub}/streams/{$encodedTitle}/disabled";
            $this->piliPost($url, ['disabledTill' => -1]);
            return true;
        } catch (\Throwable $e) {
            trace('[LiveQiniu] forbidStream failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 恢复被禁推的流（disabledTill=0）
     */
    public function resumeStream(string $streamName): bool
    {
        $this->ensureConfig();
        try {
            $hub          = $this->hub ?: 'live';
            $encodedTitle = base64_encode($streamName);
            $url          = self::PILI_API . "/v2/hubs/{$hub}/streams/{$encodedTitle}/disabled";
            $this->piliPost($url, ['disabledTill' => 0]);
            return true;
        } catch (\Throwable $e) {
            trace('[LiveQiniu] resumeStream failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 查询流在线状态
     */
    public function isStreamOnline(string $streamName): bool
    {
        $this->ensureConfig();
        try {
            $hub          = $this->hub ?: 'live';
            $encodedTitle = base64_encode($streamName);
            $url          = self::PILI_API . "/v2/hubs/{$hub}/streams/{$encodedTitle}";
            $data         = $this->piliGet($url);
            return isset($data['status']) && $data['status'] === 'connected';
        } catch (\Throwable) {
            return false;
        }
    }

    // ==================== 回放 ====================

    /**
     * 查询直播回放列表（timeshift 录制片段列表）
     * 七牛 Pili API：GET /v2/hubs/{hub}/streams/{encodedStreamTitle}/historyactivity
     *
     * @return array 每项：[url, start_time, end_time, duration]
     */
    public function getRecordList(string $streamName, ?string $startTime = null, ?string $endTime = null): array
    {
        $this->ensureConfig();
        $hub          = $this->hub ?: 'live';
        $encodedTitle = base64_encode($streamName);
        $url          = self::PILI_API . "/v2/hubs/{$hub}/streams/{$encodedTitle}/historyactivity";

        $params = [];
        if ($startTime) {
            $params['start'] = $this->toTimestamp($startTime);
        }
        if ($endTime) {
            $params['end'] = $this->toTimestamp($endTime);
        }
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        try {
            $data  = $this->piliGet($url);
            $items = $data['items'] ?? [];
            $list  = [];
            foreach ($items as $item) {
                $start    = $item['start'] ?? 0;
                $end      = $item['end']   ?? 0;
                $duration = $end - $start;
                $list[]   = [
                    'url'        => $this->buildPlaybackUrl($streamName, $start, $end),
                    'start_time' => date('Y-m-d H:i:s', $start),
                    'end_time'   => date('Y-m-d H:i:s', $end),
                    'duration'   => $duration,
                    'record_id'  => "{$streamName}_{$start}_{$end}",
                ];
            }
            return $list;
        } catch (\Throwable $e) {
            throw new LiveException('七牛云：获取回放列表失败：' . $e->getMessage());
        }
    }

    /**
     * 将指定时间段的录制保存到 Kodo 指定桶
     * 七牛 Pili API：POST /v2/hubs/{hub}/streams/{encodedTitle}/saveas
     */
    public function saveRecordToBucket(
        string $streamName,
        string $bucket,
        string $fileKey,
        string $startTime,
        string $endTime,
        string $format = 'mp4'
    ): array {
        $this->ensureConfig();
        $hub          = $this->hub ?: 'live';
        $encodedTitle = base64_encode($streamName);
        $url          = self::PILI_API . "/v2/hubs/{$hub}/streams/{$encodedTitle}/saveas";

        try {
            return $this->piliPost($url, [
                'fname'  => $fileKey,
                'start'  => $this->toTimestamp($startTime),
                'end'    => $this->toTimestamp($endTime),
                'format' => $format,
                'bucket' => $bucket,
            ]);
        } catch (\Throwable $e) {
            throw new LiveException('七牛云：回放转存失败：' . $e->getMessage());
        }
    }

    /**
     * 生成私有下载 URL（带 e/token）
     *
     * @param string $bucket Kodo Bucket
     * @param string $region Kodo 区域（如 z0/z1/cn-east-2 等）
     * @param string $fileKey 归档文件 Key
     * @param int    $expires 有效期（秒）
     */
    public function privateDownloadUrl(string $bucket, string $region, string $fileKey, int $expires = 3600): string
    {
        $this->ensureConfig();
        $bucket  = trim($bucket);
        $region  = trim($region);
        $fileKey = ltrim($fileKey, '/');

        if ($bucket === '' || $region === '' || $fileKey === '') {
            throw new LiveException('七牛云：生成私有下载 URL 失败，bucket/region/fileKey 不能为空');
        }

        // 规则：{bucket}.kodo-{region}.qiniucs.com/{key}
        $baseUrl = sprintf('https://%s.kodo-%s.qiniucs.com/%s', $bucket, $region, $fileKey);
        return $this->auth()->privateDownloadUrl($baseUrl, $expires);
    }

    /**
     * 删除 Kodo 中的录制文件
     */
    public function deleteRecord(string $bucket, string $fileKey): bool
    {
        $this->ensureConfig();
        try {
            $bucketMgr = new BucketManager($this->auth(), new QiniuConfig());
            [$response, $error] = $bucketMgr->delete($bucket, $fileKey);
            if ($error !== null) {
                throw new LiveException($error->message());
            }
            return true;
        } catch (\Throwable $e) {
            trace('[LiveQiniu] deleteRecord failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 生成回放播放地址（时间戳模式，需拉流域名支持 timeshift）
     * 格式：http://{pull_domain}/{hub}/{streamName}.m3u8?starttime={start}&endtime={end}
     */
    public function buildPlaybackUrl(string $streamName, int $start, int $end): string
    {
        $domain = rtrim($this->pullDomain, '/');
        $hub    = $this->hub ?: 'live';
        return "http://{$domain}/{$hub}/{$streamName}.m3u8?starttime={$start}&endtime={$end}";
    }

    // ==================== 桶管理（Kodo） ====================

    /**
     * 创建 Kodo Bucket
     */
    public function createBucket(string $name, string $region = 'z0'): mixed
    {
        $this->ensureConfig();
        $regionList = array_column($this->getRegions(), 'value');
        if (!in_array($region, $regionList, true)) {
            throw new LiveException("七牛云：无效的地域 [{$region}]");
        }
        $url    = 'https://' . QiniuConfig::UC_HOST . '/mkbucketv3/' . $name . '/region/' . $region;
        $auth   = $this->auth();
        $headers = $auth->authorizationV2($url, 'POST', null, 'application/json');
        $headers['Content-Type'] = 'application/json';
        $ret = Client::post($url, null, $headers);
        if (!$ret->ok()) {
            $error = new Error($url, $ret);
            $msg   = $error->message();
            if ($msg === 'bucket exists') {
                throw new LiveException("七牛云：Bucket [{$name}] 已存在");
            }
            throw new LiveException('七牛云创建桶失败：' . $msg);
        }
        return $ret->body === null ? [] : $ret->json();
    }

    /**
     * 删除 Kodo Bucket
     */
    public function deleteBucket(string $name, string $region = ''): mixed
    {
        $this->ensureConfig();
        try {
            $bucketMgr = new BucketManager($this->auth(), new QiniuConfig());
            [$response, $error] = $bucketMgr->deleteBucket($name);
            if ($error !== null) {
                throw new LiveException($error->message());
            }
            return $response ?? true;
        } catch (\Throwable $e) {
            throw new LiveException('七牛云删除桶失败：' . $e->getMessage());
        }
    }

    /**
     * 获取 Kodo Bucket 列表
     */
    public function listBuckets(): array
    {
        $this->ensureConfig();
        $bucketMgr = new BucketManager($this->auth(), new QiniuConfig());
        [$buckets, $error] = $bucketMgr->listbuckets();
        if ($error !== null) {
            throw new LiveException('七牛云：获取桶列表失败：' . $error->message());
        }
        return $buckets ?? [];
    }

    /**
     * 七牛云 Kodo 地域列表
     */
    public function getRegions(): array
    {
        return [
            ['value' => 'z0',         'label' => '华东（上海）'],
            ['value' => 'z1',         'label' => '华北（河北）'],
            ['value' => 'z2',         'label' => '华南（广东）'],
            ['value' => 'na0',        'label' => '北美'],
            ['value' => 'as0',        'label' => '东南亚（新加坡）'],
            ['value' => 'cn-east-2',  'label' => '华东（浙江）'],
        ];
    }

    // ==================== 七牛 Pili HTTP 签名封装 ====================

    /**
     * 七牛 Pili GET 请求
     */
    private function piliGet(string $url): array
    {
        $auth    = $this->auth();
        $headers = $auth->authorizationV2($url, 'GET', null, 'application/x-www-form-urlencoded');
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $ret = Client::get($url, $headers);
        if (!$ret->ok()) {
            $error = new Error($url, $ret);
            throw new LiveException('七牛Pili请求失败：' . $error->message());
        }
        return $ret->body === null ? [] : ($ret->json() ?? []);
    }

    /**
     * 七牛 Pili POST 请求（JSON body）
     */
    private function piliPost(string $url, array $body = []): array
    {
        $auth      = $this->auth();
        $bodyJson  = json_encode($body);
        $headers   = $auth->authorizationV2($url, 'POST', $bodyJson, 'application/json');
        $headers['Content-Type'] = 'application/json';
        $ret = Client::post($url, $bodyJson, $headers);
        if (!$ret->ok()) {
            $error = new Error($url, $ret);
            throw new LiveException('七牛Pili请求失败：' . $error->message());
        }
        return $ret->body === null ? [] : ($ret->json() ?? []);
    }
}
