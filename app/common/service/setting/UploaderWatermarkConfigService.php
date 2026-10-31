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

namespace app\common\service\setting;

use app\common\enum\UploaderConfigKeyEnum;
use app\common\enum\UploaderWatermarkEnum;
use app\common\exception\AdminResponseException;
use app\common\model\setting\system\Config;
use app\common\service\BaseService;
use Rabbit\service\uploader\UploaderService;

/**
 * 上传与图片水印配置服务。
 */
class UploaderWatermarkConfigService extends BaseService
{
    /**
     * 获取配置详情。
     */
    public function detail(): array
    {
        $keys = $this->configKeys();
        $stored = app()->make(ConfigService::class)->getMultiValue($keys);
        $config = $this->normalizeConfig($stored, false);

        return [
            'config' => $config,
            'options' => $this->options(),
            'limits' => UploaderWatermarkEnum::RANGES,
        ];
    }

    /**
     * 获取配置选项。
     */
    public function options(): array
    {
        return [
            'storage_options' => UploaderWatermarkEnum::storageOptions(),
            'watermark_type_options' => UploaderWatermarkEnum::typeOptions(),
            'watermark_position_options' => UploaderWatermarkEnum::positionOptions(),
        ];
    }

    /**
     * 保存配置。
     */
    public function save(array $data): array
    {
        $config = $this->normalizeConfig($data);
        app()->make(ConfigService::class)->saveKeyValues($config, 0, $this->buildMetas($config));

        return $this->detail();
    }

    /**
     * 获取上传和水印配置 key 列表。
     */
    public function configKeys(): array
    {
        return array_merge($this->storageKeys(), UploaderWatermarkEnum::watermarkKeys());
    }

    /**
     * 获取上传存储配置 key 列表。
     */
    protected function storageKeys(): array
    {
        return [
            UploaderConfigKeyEnum::UPLOADER_TYPE,
            UploaderConfigKeyEnum::QINIU_ACCESS_KEY,
            UploaderConfigKeyEnum::QINIU_SECRET_KEY,
            UploaderConfigKeyEnum::QINIU_NAME,
            UploaderConfigKeyEnum::QINIU_REGION,
            UploaderConfigKeyEnum::QINIU_URL,
            UploaderConfigKeyEnum::OSS_ACCESS_KEY,
            UploaderConfigKeyEnum::OSS_SECRET_KEY,
            UploaderConfigKeyEnum::OSS_NAME,
            UploaderConfigKeyEnum::OSS_REGION,
            UploaderConfigKeyEnum::OSS_URL,
            UploaderConfigKeyEnum::COS_ACCESS_KEY,
            UploaderConfigKeyEnum::COS_SECRET_KEY,
            UploaderConfigKeyEnum::COS_APPID,
            UploaderConfigKeyEnum::COS_NAME,
            UploaderConfigKeyEnum::COS_REGION,
            UploaderConfigKeyEnum::COS_URL,
            UploaderConfigKeyEnum::COS_CDN,
        ];
    }

    /**
     * 标准化配置。
     */
    protected function normalizeConfig(array $data, bool $validate = true): array
    {
        $config = [];

        foreach ($this->storageKeys() as $key) {
            $config[$key] = trim((string)($data[$key] ?? ''));
        }

        if (!in_array($config[UploaderConfigKeyEnum::UPLOADER_TYPE], UploaderWatermarkEnum::storageValues(), true)) {
            $config[UploaderConfigKeyEnum::UPLOADER_TYPE] = UploaderService::LOCAL_UPLOADER;
        }

        foreach (UploaderWatermarkEnum::DEFAULTS as $key => $default) {
            $config[$key] = $data[$key] ?? $default;
        }

        $config[UploaderWatermarkEnum::WATERMARK_ENABLED] = $this->normalizeSwitch(
            $config[UploaderWatermarkEnum::WATERMARK_ENABLED]
        );

        if (!in_array($config[UploaderWatermarkEnum::WATERMARK_TYPE], UploaderWatermarkEnum::typeValues(), true)) {
            $config[UploaderWatermarkEnum::WATERMARK_TYPE] = UploaderWatermarkEnum::DEFAULTS[UploaderWatermarkEnum::WATERMARK_TYPE];
        }
        if (!in_array($config[UploaderWatermarkEnum::WATERMARK_POSITION], UploaderWatermarkEnum::positionValues(), true)) {
            $config[UploaderWatermarkEnum::WATERMARK_POSITION] = UploaderWatermarkEnum::DEFAULTS[UploaderWatermarkEnum::WATERMARK_POSITION];
        }

        foreach (UploaderWatermarkEnum::RANGES as $key => $range) {
            $config[$key] = $this->normalizeInteger(
                $config[$key],
                (int)$range['min'],
                (int)$range['max'],
                (int)UploaderWatermarkEnum::DEFAULTS[$key]
            );
        }

        $config[UploaderWatermarkEnum::WATERMARK_IMAGE] = trim((string)$config[UploaderWatermarkEnum::WATERMARK_IMAGE]);
        $config[UploaderWatermarkEnum::WATERMARK_TEXT] = trim((string)$config[UploaderWatermarkEnum::WATERMARK_TEXT]);
        $config[UploaderWatermarkEnum::WATERMARK_TEXT_COLOR] = $this->normalizeColor(
            (string)$config[UploaderWatermarkEnum::WATERMARK_TEXT_COLOR]
        );

        if ($validate && (int)$config[UploaderWatermarkEnum::WATERMARK_ENABLED] === 1) {
            $this->validateWatermarkRequired($config);
        }

        return $config;
    }

    /**
     * 校验启用水印时的必填项。
     */
    protected function validateWatermarkRequired(array $config): void
    {
        if ($config[UploaderWatermarkEnum::WATERMARK_TYPE] === UploaderWatermarkEnum::TYPE_IMAGE
            && $config[UploaderWatermarkEnum::WATERMARK_IMAGE] === ''
        ) {
            throw new AdminResponseException('请上传水印图片');
        }

        if ($config[UploaderWatermarkEnum::WATERMARK_TYPE] === UploaderWatermarkEnum::TYPE_TEXT
            && $config[UploaderWatermarkEnum::WATERMARK_TEXT] === ''
        ) {
            throw new AdminResponseException('请输入水印文字');
        }
    }

    /**
     * 标准化开关值。
     */
    protected function normalizeSwitch(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
    }

    /**
     * 标准化整数范围。
     */
    protected function normalizeInteger(mixed $value, int $min, int $max, int $default): int
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int)$value));
    }

    /**
     * 标准化十六进制颜色。
     */
    protected function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtoupper($color);
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
            return '#' . strtoupper($color);
        }

        return UploaderWatermarkEnum::DEFAULTS[UploaderWatermarkEnum::WATERMARK_TEXT_COLOR];
    }

    /**
     * 构建 config 表元信息。
     */
    protected function buildMetas(array $config): array
    {
        $titles = $this->configTitles();
        $metas = [];
        $sort = 120;

        foreach (array_keys($config) as $key) {
            $metas[$key] = [
                'title' => $titles[$key] ?? $key,
                'into' => '平台端水印配置页面维护的上传和图片水印参数',
                'sort' => $sort++,
                'status' => 1,
            ];
        }

        return $metas;
    }

    /**
     * 配置标题映射。
     */
    protected function configTitles(): array
    {
        return [
            UploaderConfigKeyEnum::UPLOADER_TYPE => '存储方式',
            UploaderConfigKeyEnum::QINIU_ACCESS_KEY => '七牛云 AccessKey',
            UploaderConfigKeyEnum::QINIU_SECRET_KEY => '七牛云 SecretKey',
            UploaderConfigKeyEnum::QINIU_NAME => '七牛云 Bucket',
            UploaderConfigKeyEnum::QINIU_REGION => '七牛云区域',
            UploaderConfigKeyEnum::QINIU_URL => '七牛云访问域名',
            UploaderConfigKeyEnum::OSS_ACCESS_KEY => '阿里云 AccessKey',
            UploaderConfigKeyEnum::OSS_SECRET_KEY => '阿里云 SecretKey',
            UploaderConfigKeyEnum::OSS_NAME => '阿里云 Bucket',
            UploaderConfigKeyEnum::OSS_REGION => '阿里云区域',
            UploaderConfigKeyEnum::OSS_URL => '阿里云访问域名',
            UploaderConfigKeyEnum::COS_ACCESS_KEY => '腾讯云 AccessKey',
            UploaderConfigKeyEnum::COS_SECRET_KEY => '腾讯云 SecretKey',
            UploaderConfigKeyEnum::COS_APPID => '腾讯云 AppID',
            UploaderConfigKeyEnum::COS_NAME => '腾讯云 Bucket',
            UploaderConfigKeyEnum::COS_REGION => '腾讯云区域',
            UploaderConfigKeyEnum::COS_URL => '腾讯云访问域名',
            UploaderConfigKeyEnum::COS_CDN => '腾讯云 CDN 域名',
            UploaderWatermarkEnum::WATERMARK_ENABLED => '是否开启水印',
            UploaderWatermarkEnum::WATERMARK_TYPE => '水印类型',
            UploaderWatermarkEnum::WATERMARK_POSITION => '水印位置',
            UploaderWatermarkEnum::WATERMARK_X_OFFSET => '水印横坐标偏移量',
            UploaderWatermarkEnum::WATERMARK_Y_OFFSET => '水印纵坐标偏移量',
            UploaderWatermarkEnum::WATERMARK_IMAGE => '水印图片',
            UploaderWatermarkEnum::WATERMARK_OPACITY => '水印透明度',
            UploaderWatermarkEnum::WATERMARK_ANGLE => '水印倾斜度',
            UploaderWatermarkEnum::WATERMARK_TEXT => '水印文字',
            UploaderWatermarkEnum::WATERMARK_TEXT_SIZE => '水印文字大小',
            UploaderWatermarkEnum::WATERMARK_TEXT_COLOR => '水印文字颜色',
            UploaderWatermarkEnum::WATERMARK_TEXT_ANGLE => '水印文字旋转角度',
        ];
    }

    /**
     * 设置对应模型。
     */
    protected function setModel(): string
    {
        return Config::class;
    }
}
