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

use app\common\enum\LiveConfigKeyEnum;
use Rabbit\interfaces\ConfigInterface;
use Rabbit\service\live\driver\Aliyun;
use Rabbit\service\live\driver\Qiniu;

/**
 * 直播服务入口
 * Class LiveService
 * @package Rabbit\service\live
 */
class LiveService
{
    use \Rabbit\traits\DriverRegistryTrait;

    /** 插件 manifest 中的声明字段 */
    private const MANIFEST_KEY = 'live_providers';

    /** 阿里云直播 */
    const PROVIDER_ALIYUN = 'aliyun';

    /** 七牛云直播 */
    const PROVIDER_QINIU = 'qiniu';

    /**
     * 配置服务（db_config 代理）
     *
     * @var ConfigInterface|null
     */
    protected ?ConfigInterface $configServe = null;

    /**
     * 设置配置服务（通常在 AppService 中绑定）
     *
     * @param ConfigInterface $configService
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static
    {
        $this->configServe = $configService;
        return $this;
    }

    /**
     * 获取当前配置的直播驱动（单一云）
     *
     * @param string|null $provider  指定云：aliyun | qiniu，null 则自动读取 live_default_provider
     * @param array       $config    可选：直接传入配置数组（优先级高于 db_config）
     * @return LiveInterface
     * @throws LiveException
     */
    public function driver(?string $provider = null, array $config = []): LiveInterface
    {
        if ($provider === null) {
            $provider = $this->resolveProvider();
        }

        // 运行期注册的直播实现优先（插件声明或宿主 extend），未命中再走内置实现
        $registered = self::resolveRegistered($provider, $config);
        if ($registered !== null) {
            if (!$registered instanceof LiveInterface) {
                throw new LiveException('直播驱动实现无效');
            }

            return $registered;
        }

        return match ($provider) {
            self::PROVIDER_ALIYUN => $this->makeAliyun($config),
            self::PROVIDER_QINIU  => $this->makeQiniu($config),
            default               => throw new LiveException("不支持的直播云服务商：[{$provider}]"),
        };
    }

    /**
     * 为直播间生成推拉流地址（使用当前配置的单一云）
     *
     * @param string $streamName 流名称
     * push_base / push_key：供 OBS 等拆分（服务器=push_base，推流码=push_key，拼接为 rtmp://.../Hub/推流码）
     *
     * @return array{
     *   stream_name: string,
     *   provider: string,
     *   push_url: string,
     *   push_base: string,
     *   push_key: string,
     *   pull_flv: string,
     *   pull_m3u8: string,
     *   pull_rtmp: string,
     *   stream_created_at: string
     * }
     * @throws LiveException
     */
    public function generateStreamUrls(string $streamName): array
    {
        $provider = $this->resolveProvider();
        $driver   = $this->driver($provider);
        $parts    = $driver->buildPushUrlParts($streamName);
        $pushUrl  = $parts['push_url'] ?? ($parts['push_base'] . '?' . $parts['push_key']);

        return [
            'stream_name'       => $streamName,
            'provider'          => $provider,
            'push_url'          => $pushUrl,
            'push_base'         => $parts['push_base'],
            'push_key'          => $parts['push_key'],
            'pull_flv'          => $driver->buildPullUrl($streamName, 'flv'),
            'pull_m3u8'         => $driver->buildPullUrl($streamName, 'm3u8'),
            'pull_rtmp'         => $driver->buildPullUrl($streamName, 'rtmp'),
            'stream_created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 禁推直播流（使用当前配置的单一云）
     *
     * @param string $streamName
     * @return bool
     */
    public function forbidStream(string $streamName): bool
    {
        return $this->driver()->forbidStream($streamName);
    }

    /**
     * 恢复直播流（使用当前配置的单一云）
     *
     * @param string $streamName
     * @return bool
     */
    public function resumeStream(string $streamName): bool
    {
        return $this->driver()->resumeStream($streamName);
    }

    // ==================== 回放（录制/转存/删除） ====================

    /**
     * 获取直播回放列表（timeshift 片段列表）
     *
     * @param string      $streamName
     * @param string|null $startTime Y-m-d H:i:s 或时间戳字符串
     * @param string|null $endTime
     * @param string|null $provider aliyun | qiniu
     * @return array
     * @throws LiveException
     */
    public function getRecordList(
        string $streamName,
        ?string $startTime = null,
        ?string $endTime = null,
        ?string $provider = null
    ): array {
        $provider = $provider ?? $this->resolveProvider();
        $driver = $this->driver($provider);
        return $driver->getRecordList($streamName, $startTime, $endTime);
    }

    /**
     * 将指定时间段的录制转存到 OSS/Kodo 指定桶（回放归档）
     *
     * @param string      $streamName
     * @param string      $bucket
     * @param string      $fileKey
     * @param string      $startTime
     * @param string      $endTime
     * @param string      $format mp4|flv
     * @param string|null $provider
     * @return array
     * @throws LiveException
     */
    public function saveRecordToBucket(
        string $streamName,
        string $bucket,
        string $fileKey,
        string $startTime,
        string $endTime,
        string $format = 'mp4',
        ?string $provider = null
    ): array {
        $provider = $provider ?? $this->resolveProvider();
        $driver = $this->driver($provider);
        return $driver->saveRecordToBucket($streamName, $bucket, $fileKey, $startTime, $endTime, $format);
    }

    /**
     * 删除录制文件（从 OSS/Kodo 中删除）
     *
     * @param string      $bucket
     * @param string      $fileKey
     * @param string|null $provider
     * @return bool
     * @throws LiveException
     */
    public function deleteRecord(string $bucket, string $fileKey, ?string $provider = null): bool
    {
        $provider = $provider ?? $this->resolveProvider();
        $driver = $this->driver($provider);
        return $driver->deleteRecord($bucket, $fileKey);
    }

    /**
     * 七牛私有下载 URL（带 token/e）
     */
    public function getQiniuPrivateDownloadUrl(
        string $bucket,
        string $region,
        string $fileKey,
        int $expires = 3600
    ): string {
        $driver = $this->driver(self::PROVIDER_QINIU);
        if ($driver instanceof Qiniu) {
            return $driver->privateDownloadUrl($bucket, $region, $fileKey, $expires);
        }
        throw new \RuntimeException('七牛私有下载 URL 生成失败：驱动类型不正确');
    }

    /**
     * 生成唯一流名称
     *
     * @param int $roomId
     * @return string
     */
    public function generateStreamName(int $roomId): string
    {
        return 'room_' . $roomId . '_' . time();
    }

    /**
     * 获取当前配置的云服务商标识
     *
     * @return string
     * @throws LiveException
     */
    public function getProvider(): string
    {
        return $this->resolveProvider();
    }

    /**
     * 读取当前配置的云服务商
     * 优先级：live_default_provider -> 哪个 accessKey 已配置 -> 异常
     *
     * @throws LiveException
     */
    private function resolveProvider(): string
    {
        if ($this->configServe === null) {
            throw new LiveException('直播配置服务未初始化，请先调用 setConfigServe()');
        }

        $provider = (string)$this->configServe->getValue(LiveConfigKeyEnum::DEFAULT_PROVIDER);

        if (in_array($provider, [self::PROVIDER_ALIYUN, self::PROVIDER_QINIU], true) || self::hasDriver($provider)) {
            return $provider;
        }

        // 回退：哪个 accessKey 已配置就用哪个
        if (!empty($this->configServe->getValue(LiveConfigKeyEnum::ALI_ACCESS_KEY))) {
            return self::PROVIDER_ALIYUN;
        }
        if (!empty($this->configServe->getValue(LiveConfigKeyEnum::QINIU_ACCESS_KEY))) {
            return self::PROVIDER_QINIU;
        }

        throw new LiveException('未配置任何直播云服务，请在「直播配置」中添加配置');
    }

    /**
     * 构建阿里云驱动实例
     */
    private function makeAliyun(array $config = []): Aliyun
    {
        $driver = new Aliyun($config);
        if ($this->configServe !== null && !$config) {
            $driver->setConfigServe($this->configServe);
        }
        return $driver;
    }

    /**
     * 构建七牛云驱动实例
     */
    private function makeQiniu(array $config = []): Qiniu
    {
        $driver = new Qiniu($config);
        if ($this->configServe !== null && !$config) {
            $driver->setConfigServe($this->configServe);
        }
        return $driver;
    }
}
