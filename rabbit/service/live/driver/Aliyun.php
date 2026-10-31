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
use Rabbit\service\live\BaseDriver;
use Rabbit\service\live\LiveException;

/**
 * 阿里云直播驱动
 *
 * 配置键（对应 db_config key）：
 *   live_ali_access_key       AccessKey ID
 *   live_ali_access_secret    AccessKey Secret
 *   live_ali_app_name         直播应用名（AppName）
 *   live_ali_push_domain      推流域名
 *   live_ali_pull_domain      拉流域名
 *   live_ali_expire_seconds   URL 鉴权有效秒数（默认 7200）
 *   live_ali_record_bucket    回放录制存储的 OSS Bucket 名称
 *   live_ali_record_region    回放录制存储的 OSS Bucket 地域
 *
 * Class Aliyun
 * @package Rabbit\service\live\driver
 */
class Aliyun extends BaseDriver
{
    /** 阿里云直播 API 端点 */
    private const API_ENDPOINT = 'https://live.aliyuncs.com/';

    /** 阿里云直播 API 版本 */
    private const API_VERSION = '2016-11-01';

    /** @var string AccessKey ID */
    private string $accessKey = '';

    /** @var string AccessKey Secret */
    private string $accessSecret = '';

    /** @var string 录制回放用的 OSS Bucket */
    private string $recordBucket = '';

    /** @var string 录制回放用的 OSS 地域 */
    private string $recordRegion = '';

    // ==================== 初始化 ====================

    /**
     * 从配置服务加载配置，构造驱动实例
     * @param array $config 可直接传入配置，优先级高于 configServe
     */
    public function __construct(array $config = [])
    {
        if ($config) {
            $this->loadFromArray($config);
        }
    }

    /**
     * 从数组加载配置
     */
    private function loadFromArray(array $config): void
    {
        $this->accessKey    = $config['access_key']     ?? $config[LiveConfigKeyEnum::ALI_ACCESS_KEY]     ?? '';
        $this->accessSecret = $config['access_secret']  ?? $config[LiveConfigKeyEnum::ALI_ACCESS_SECRET]  ?? '';
        $this->appName      = $config['app_name']       ?? $config[LiveConfigKeyEnum::ALI_APP_NAME]       ?? 'live';
        $this->pushDomain   = $config['push_domain']    ?? $config[LiveConfigKeyEnum::ALI_PUSH_DOMAIN]    ?? '';
        $this->pullDomain   = $config['pull_domain']    ?? $config[LiveConfigKeyEnum::ALI_PULL_DOMAIN]    ?? '';
        $this->expireSeconds = (int)($config['expire_seconds'] ?? $config[LiveConfigKeyEnum::ALI_EXPIRE_SECONDS] ?? 7200);
        $this->recordBucket = $config['record_bucket']  ?? '';
        $this->recordRegion = $config['record_region']  ?? '';
    }

    /**
     * 从 configServe 加载配置（懒加载）
     */
    private function boot(): void
    {
        if ($this->accessKey) {
            return;
        }
        $this->accessKey     = (string)$this->cfg(LiveConfigKeyEnum::ALI_ACCESS_KEY);
        $this->accessSecret  = (string)$this->cfg(LiveConfigKeyEnum::ALI_ACCESS_SECRET);
        $this->appName       = (string)$this->cfg(LiveConfigKeyEnum::ALI_APP_NAME, 'live');
        $this->pushDomain    = (string)$this->cfg(LiveConfigKeyEnum::ALI_PUSH_DOMAIN);
        $this->pullDomain    = (string)$this->cfg(LiveConfigKeyEnum::ALI_PULL_DOMAIN);
        $this->expireSeconds = (int)$this->cfg(LiveConfigKeyEnum::ALI_EXPIRE_SECONDS, 7200);
    }

    /**
     * 验证核心配置
     * @throws LiveException
     */
    private function ensureConfig(): void
    {
        $this->boot();
        if (!$this->accessKey || !$this->accessSecret) {
            throw new LiveException('阿里云直播：未配置 AccessKey，请在「直播配置」中完善阿里云信息');
        }
        if (!$this->pushDomain || !$this->pullDomain) {
            throw new LiveException('阿里云直播：未配置推/拉流域名，请在「直播配置」中完善相关信息');
        }
    }

    // ==================== 推拉流 ====================

    /**
     * 获取驱动标识
     */
    public function getProvider(): string
    {
        return \Rabbit\service\live\LiveService::PROVIDER_ALIYUN;
    }

    /**
     * 生成推流地址（含 auth_key 鉴权）
     * 格式：rtmp://{push_domain}/{app_name}/{stream_name}?auth_key={timestamp}-{rand}-{uid}-{md5hash}
     */
    public function buildPushUrl(string $streamName): string
    {
        return $this->buildPushUrlParts($streamName)['push_url'];
    }

    /**
     * 拆分推流地址：OBS「服务器」= push_base（至 App），「推流码」= push_key（流名?auth_key=...）
     */
    public function buildPushUrlParts(string $streamName): array
    {
        $this->ensureConfig();
        $domain   = rtrim($this->pushDomain, '/');
        $app      = $this->appName ?: 'live';
        $expireAt = time() + $this->expireSeconds;
        $rand     = rand(0, 999999);
        $uid      = 0;
        $uri      = "/{$app}/{$streamName}";
        $md5Hash  = md5("{$uri}-{$expireAt}-{$rand}-{$uid}-{$this->accessSecret}");
        $authKey  = "{$expireAt}-{$rand}-{$uid}-{$md5Hash}";

        $pushBase = "rtmp://{$domain}/{$app}";
        $pushKey  = "{$streamName}?auth_key={$authKey}";
        $pushUrl  = "rtmp://{$domain}{$uri}?auth_key={$authKey}";

        return [
            'push_base' => $pushBase,
            'push_key'  => $pushKey,
            'push_url'  => $pushUrl,
        ];
    }

    /**
     * 生成拉流地址
     * 支持格式：flv（默认）| m3u8 | rtmp
     */
    public function buildPullUrl(string $streamName, string $format = 'flv'): string
    {
        $this->ensureConfig();
        $domain = rtrim($this->pullDomain, '/');
        $app    = $this->appName ?: 'live';

        return match ($format) {
            'm3u8'  => "https://{$domain}/{$app}/{$streamName}.m3u8",
            'rtmp'  => "rtmp://{$domain}/{$app}/{$streamName}",
            default => "http://{$domain}/{$app}/{$streamName}.flv",
        };
    }

    // ==================== 流管理 ====================

    /**
     * 禁推直播流（ForbidLiveStream）
     */
    public function forbidStream(string $streamName): bool
    {
        $this->ensureConfig();
        try {
            $this->callApi([
                'Action'         => 'ForbidLiveStream',
                'DomainName'     => $this->pushDomain,
                'AppName'        => $this->appName,
                'StreamName'     => $streamName,
                'LiveStreamType' => 'publisher',
            ]);
            return true;
        } catch (\Throwable $e) {
            trace('[LiveAliyun] forbidStream failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 恢复被禁推的流（ResumeLiveStream）
     */
    public function resumeStream(string $streamName): bool
    {
        $this->ensureConfig();
        try {
            $this->callApi([
                'Action'         => 'ResumeLiveStream',
                'DomainName'     => $this->pushDomain,
                'AppName'        => $this->appName,
                'StreamName'     => $streamName,
                'LiveStreamType' => 'publisher',
            ]);
            return true;
        } catch (\Throwable $e) {
            trace('[LiveAliyun] resumeStream failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 查询流在线状态（DescribeLiveStreamOnlineInfo）
     */
    public function isStreamOnline(string $streamName): bool
    {
        $this->ensureConfig();
        try {
            $data = $this->callApi([
                'Action'     => 'DescribeLiveStreamOnlineInfo',
                'DomainName' => $this->pushDomain,
                'AppName'    => $this->appName,
                'StreamName' => $streamName,
            ]);
            return (int)($data['TotalNum'] ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    // ==================== 回放 ====================

    /**
     * 查询录制文件列表（DescribeLiveRecordNotifyConfig / DescribeRecords）
     * 使用 DescribeRecords 接口获取已录制的回放列表
     *
     * @param string      $streamName
     * @param string|null $startTime  Y-m-d H:i:s 或时间戳
     * @param string|null $endTime
     * @return array 每项：[url, start_time, end_time, duration, record_id]
     */
    public function getRecordList(string $streamName, ?string $startTime = null, ?string $endTime = null): array
    {
        $this->ensureConfig();
        $params = [
            'Action'     => 'DescribeRecords',
            'DomainName' => $this->pushDomain,
            'AppName'    => $this->appName,
            'StreamName' => $streamName,
        ];

        if ($startTime) {
            // 阿里云要求 UTC ISO 8601 格式
            $params['StartTime'] = gmdate('Y-m-d\TH:i:s\Z', $this->toTimestamp($startTime));
        }
        if ($endTime) {
            $params['EndTime'] = gmdate('Y-m-d\TH:i:s\Z', $this->toTimestamp($endTime));
        }

        try {
            $data    = $this->callApi($params);
            $records = $data['RecordInfo']['Record'] ?? [];
            $list    = [];
            foreach ($records as $item) {
                $list[] = [
                    'record_id'  => $item['RecordId']  ?? '',
                    'url'        => $item['OssObject']  ?? $item['Url'] ?? '',
                    'start_time' => $item['StartTime'] ?? '',
                    'end_time'   => $item['StopTime']  ?? '',
                    'duration'   => $item['Duration']  ?? 0,
                    'format'     => $item['Format']    ?? '',
                ];
            }
            return $list;
        } catch (\Throwable $e) {
            throw new LiveException('阿里云：获取回放列表失败：' . $e->getMessage());
        }
    }

    /**
     * 将直播录制转存到 OSS 指定桶（CreateRecordTask / 手动转存）
     * 调用 AddLiveRecordVodConfig 或 AddMultipleStreamMixService（此处使用转存快照接口）
     *
     * 实际走 CreateRecord 接口：将指定时间段的录制内容转存到 OSS
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
        try {
            return $this->callApi([
                'Action'      => 'CreateRecord',
                'DomainName'  => $this->pushDomain,
                'AppName'     => $this->appName,
                'StreamName'  => $streamName,
                'OssBucket'   => $bucket,
                'OssObject'   => $fileKey,
                'StartTime'   => gmdate('Y-m-d\TH:i:s\Z', $this->toTimestamp($startTime)),
                'EndTime'     => gmdate('Y-m-d\TH:i:s\Z', $this->toTimestamp($endTime)),
                'Format'      => $format,
            ]);
        } catch (\Throwable $e) {
            throw new LiveException('阿里云：回放转存失败：' . $e->getMessage());
        }
    }

    /**
     * 删除 OSS 中的录制文件
     * 调用 DeleteRecordFiles 接口
     */
    public function deleteRecord(string $bucket, string $fileKey): bool
    {
        $this->ensureConfig();
        try {
            $this->callApi([
                'Action'      => 'DeleteRecordFiles',
                'OssBucket'   => $bucket,
                'OssObject'   => $fileKey,
                'DomainName'  => $this->pushDomain,
            ]);
            return true;
        } catch (\Throwable $e) {
            trace('[LiveAliyun] deleteRecord failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    // ==================== 桶管理（OSS） ====================

    /**
     * 创建 OSS Bucket
     * 注意：阿里云 OSS 创建桶依赖 OSS SDK，这里通过 API 模拟
     */
    public function createBucket(string $name, string $region = ''): mixed
    {
        $this->ensureConfig();
        // 优先使用已配置的回放 region
        $region = $region ?: $this->recordRegion;
        if (!$region) {
            throw new LiveException('阿里云：创建桶需指定地域（region）');
        }
        try {
            $ossClient = new \OSS\OssClient($this->accessKey, $this->accessSecret, $region);
            if ($ossClient->doesBucketExist($name)) {
                throw new LiveException("OSS Bucket [{$name}] 已存在");
            }
            return $ossClient->createBucket($name, \OSS\OssClient::OSS_ACL_TYPE_PRIVATE);
        } catch (\OSS\Core\OssException $e) {
            throw new LiveException('阿里云OSS创建桶失败：' . $e->getMessage());
        }
    }

    /**
     * 删除 OSS Bucket
     */
    public function deleteBucket(string $name, string $region = ''): mixed
    {
        $this->ensureConfig();
        $region = $region ?: $this->recordRegion;
        try {
            $ossClient = new \OSS\OssClient($this->accessKey, $this->accessSecret, $region);
            return $ossClient->deleteBucket($name);
        } catch (\OSS\Core\OssException $e) {
            throw new LiveException('阿里云OSS删除桶失败：' . $e->getMessage());
        }
    }

    /**
     * 获取 OSS Bucket 列表
     */
    public function listBuckets(): array
    {
        $this->ensureConfig();
        $region = $this->recordRegion ?: 'oss-cn-hangzhou.aliyuncs.com';
        try {
            $ossClient = new \OSS\OssClient($this->accessKey, $this->accessSecret, $region);
            $response  = $ossClient->listBuckets();
            $list      = [];
            foreach ($response->getBucketList() as $item) {
                $list[] = [
                    'name'       => $item->getName(),
                    'location'   => $item->getLocation(),
                    'createTime' => $item->getCreateDate(),
                ];
            }
            return $list;
        } catch (\OSS\Core\OssException $e) {
            throw new LiveException('阿里云OSS获取桶列表失败：' . $e->getMessage());
        }
    }

    /**
     * 阿里云 OSS 地域列表
     */
    public function getRegions(): array
    {
        return [
            ['value' => 'oss-cn-hangzhou.aliyuncs.com',    'label' => '华东1（杭州）'],
            ['value' => 'oss-cn-shanghai.aliyuncs.com',    'label' => '华东2（上海）'],
            ['value' => 'oss-cn-qingdao.aliyuncs.com',     'label' => '华北1（青岛）'],
            ['value' => 'oss-cn-beijing.aliyuncs.com',     'label' => '华北2（北京）'],
            ['value' => 'oss-cn-zhangjiakou.aliyuncs.com', 'label' => '华北3（张家口）'],
            ['value' => 'oss-cn-shenzhen.aliyuncs.com',    'label' => '华南1（深圳）'],
            ['value' => 'oss-cn-guangzhou.aliyuncs.com',   'label' => '华南3（广州）'],
            ['value' => 'oss-cn-chengdu.aliyuncs.com',     'label' => '西南1（成都）'],
            ['value' => 'oss-cn-hongkong.aliyuncs.com',    'label' => '中国（香港）'],
            ['value' => 'oss-ap-southeast-1.aliyuncs.com', 'label' => '新加坡'],
            ['value' => 'oss-us-west-1.aliyuncs.com',      'label' => '美国（硅谷）'],
        ];
    }

    // ==================== 阿里云 API 签名封装 ====================

    /**
     * 阿里云 API 签名请求（HMAC-SHA1，公共参数自动补全）
     * @param array $params 业务参数
     * @return array 响应数据
     * @throws LiveException
     */
    private function callApi(array $params): array
    {
        $params = array_merge($params, [
            'Format'           => 'JSON',
            'Version'          => self::API_VERSION,
            'AccessKeyId'      => $this->accessKey,
            'SignatureMethod'  => 'HMAC-SHA1',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce'   => uniqid('', true),
        ]);

        ksort($params);
        $query      = http_build_query($params);
        $strToSign  = 'GET&' . rawurlencode('/') . '&' . rawurlencode($query);
        $sign       = base64_encode(hash_hmac('sha1', $strToSign, $this->accessSecret . '&', true));
        $params['Signature'] = $sign;

        $data = $this->httpGet(self::API_ENDPOINT, $params);

        // 阿里云 API 错误判断
        if (isset($data['Code']) && $data['Code'] !== 'OK' && !isset($data['RequestId'])) {
            $msg = $data['Message'] ?? json_encode($data);
            throw new LiveException("阿里云API错误 [{$data['Code']}]：{$msg}");
        }

        return $data;
    }
}
