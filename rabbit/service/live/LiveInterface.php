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

/**
 * 直播驱动接口
 * Interface LiveInterface
 * @package Rabbit\service\live
 */
interface LiveInterface
{
    // ==================== 推拉流 ====================

    /**
     * 生成推流地址（含鉴权）
     * @param string $streamName 流名称
     * @return string 完整推流 URL
     */
    public function buildPushUrl(string $streamName): string;

    /**
     * 获取推流地址拆分结果（OBS 等：服务器=push_base，推流码=push_key）
     * push_base：rtmp://推流域/{Hub 或 App}，不含流名
     * push_key：{流名}?鉴权参数（与 push_base 拼接为 rtmp://.../Hub/{push_key} 即完整推流 URL）
     * push_url：完整推流地址（可选；存在时 LiveService 优先使用）
     *
     * @param string $streamName
     * @return array{push_base: string, push_key: string, push_url?: string}
     */
    public function buildPushUrlParts(string $streamName): array;

    /**
     * 生成拉流（播放）地址
     * @param string $streamName 流名称
     * @param string $format     拉流格式：flv | m3u8 | rtmp
     * @return string 完整拉流 URL
     */
    public function buildPullUrl(string $streamName, string $format = 'flv'): string;

    // ==================== 流管理 ====================

    /**
     * 禁推（踢流/关闭直播）
     * @param string $streamName
     * @return bool
     */
    public function forbidStream(string $streamName): bool;

    /**
     * 恢复被禁推的流
     * @param string $streamName
     * @return bool
     */
    public function resumeStream(string $streamName): bool;

    /**
     * 查询流在线状态
     * @param string $streamName
     * @return bool
     */
    public function isStreamOnline(string $streamName): bool;

    // ==================== 回放 ====================

    /**
     * 查询直播回放列表（录制文件列表）
     * @param string      $streamName  流名称
     * @param string|null $startTime   开始时间（Y-m-d H:i:s 或时间戳字符串）
     * @param string|null $endTime     结束时间
     * @return array  每个元素包含 [url, start_time, end_time, duration]
     */
    public function getRecordList(string $streamName, ?string $startTime = null, ?string $endTime = null): array;

    /**
     * 将直播录制转存到 OSS/Kodo 指定桶（回放归档）
     * @param string $streamName  流名称
     * @param string $bucket      目标桶名
     * @param string $fileKey     目标文件路径 key
     * @param string $startTime   录制开始时间
     * @param string $endTime     录制结束时间
     * @return array  返回转存任务信息
     */
    public function saveRecordToBucket(
        string $streamName,
        string $bucket,
        string $fileKey,
        string $startTime,
        string $endTime,
        string $format = 'mp4'
    ): array;

    /**
     * 删除录制文件（从桶中删除）
     * @param string $bucket
     * @param string $fileKey
     * @return bool
     */
    public function deleteRecord(string $bucket, string $fileKey): bool;

    // ==================== 桶管理 ====================

    /**
     * 创建存储桶（用于回放归档）
     * @param string $name    桶名
     * @param string $region  地域
     * @return mixed
     */
    public function createBucket(string $name, string $region = ''): mixed;

    /**
     * 删除存储桶
     * @param string $name
     * @param string $region
     * @return mixed
     */
    public function deleteBucket(string $name, string $region = ''): mixed;

    /**
     * 获取存储桶列表
     * @return array
     */
    public function listBuckets(): array;

    /**
     * 获取支持的地域列表
     * @return array  每个元素：[value => string, label => string]
     */
    public function getRegions(): array;

    // ==================== 配置 ====================

    /**
     * 获取当前驱动标识（aliyun | qiniu）
     * @return string
     */
    public function getProvider(): string;

    /**
     * 设置配置服务（db_config 代理）
     * @param ConfigInterface $configService
     * @return static
     */
    public function setConfigServe(ConfigInterface $configService): static;
}
