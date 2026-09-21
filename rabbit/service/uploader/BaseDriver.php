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

namespace Rabbit\service\uploader;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\UploaderWatermarkEnum;

/**
 * 上传扩展基类
 * Class BaseDriver
 * @package dengfenglai\service\uploader
 */
abstract class BaseDriver
{
    /**
     * 水印文字字体路径。
     */
    private const WATERMARK_FONT = 'storage/font/SourceHanSansCN-Normal.ttf';

    /**
     * 错误信息
     * @var string
     */
    protected string|null $error = null;

    /**
     * 验证配置
     * @var string
     */
    protected string $validate;

    /**
     * 上传文件路径
     * @var string
     */
    protected string   $path;

    /**
     * 待清理的水印临时文件。
     */
    protected array $watermarkTempFiles = [];

    /**
     * 水印运行时配置缓存。
     */
    protected array|null $watermarkConfig = null;

    /**
     * 上传文件路径
     * @param string $path
     * @return $this
     */
    public function to(string $path): static
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 设置上传域名
     * @param string $url
     * @return string
     */
    protected function setUrl(string $url): string
    {
        if ($url && !str_contains($url, 'http')) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    /**
     * 设置验证规则
     * @param array|null $validate
     * @return $this
     */
    public function validate(?array $validate = null): static
    {
        if (is_null($validate)) {
            $validate['filesize'] = config('filesystem.storage.file_size',);
            $validate['fileExt'] = config('filesystem.storage.file_ext',);
            $validate['fileMime'] = config('filesystem.storage.file_mime');
        }
        $this->extractValidate($validate);
        return $this;
    }

    /**
     * 提取上传验证
     */
    protected function extractValidate(array $validateArray): void
    {
        $validate = [];
        foreach ($validateArray as $key => $value) {
            $validate[] = $key . ':' . (is_array($value) ? implode(',', $value) : $value);
        }
        $this->validate = implode('|', $validate);
        unset($validate);
    }

    /**
     * 提取文件名
     * @param string $path
     * @param string $ext
     * @return string
     */
    protected function getSaveFileName(string $path = null, string $ext = 'jpg'): string
    {
        mt_srand();
        return ($path ? substr(md5($path), 0, 15) : '') . date('YmdHis') . rand(0, 9999) . '.' . $ext;
    }

    /**
     * 验证目录是否正确
     * @param string $key
     * @return string
     */
    protected function getUploadPath(string $key): string
    {
        $path = ($this->path ? $this->path . '/' : '') . $key;
        if ($path && $path[0] === '/') {
            $path = substr($path, 1);
        }
        return $path;
    }

    /**
     * 设置错误信息
     * @param string|null $error
     * @return bool
     */
    protected function setError(?string $error = null): bool
    {
        $this->error = $error ?: '未知错误';
        return false;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError(): string
    {
        $error = $this->error;
        $this->error = null;
        return (string)$error;
    }

    /**
     * 为上传文件准备水印处理后的路径。
     */
    protected function prepareWatermarkUploadFile(\think\File $fileHandle): array
    {
        $sourcePath = (string)$fileHandle->getRealPath();
        $result = [
            'path' => $sourcePath,
            'temp' => false,
            'watermarked' => false,
        ];

        try {
            $watermarkPath = $this->makeWatermarkedImage($sourcePath);
        } catch (\Throwable) {
            $watermarkPath = null;
        }

        if (!$watermarkPath) {
            return $result;
        }

        return [
            'path' => $watermarkPath,
            'temp' => true,
            'watermarked' => true,
        ];
    }

    /**
     * 清理水印临时文件。
     */
    protected function cleanupWatermarkUploadFile(array $preparedFile): void
    {
        if (!($preparedFile['temp'] ?? false)) {
            return;
        }

        $path = (string)($preparedFile['path'] ?? '');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * 生成带水印的临时图片。
     */
    protected function makeWatermarkedImage(string $sourcePath): string|null
    {
        if (!$this->shouldApplyWatermark($sourcePath)) {
            return null;
        }

        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return null;
        }

        $imageType = (int)($imageInfo[2] ?? 0);
        $sourceImage = $this->createImageFromPath($sourcePath, $imageType);
        if (!$sourceImage) {
            return null;
        }

        $config = $this->getWatermarkConfig();
        $watermarkImage = $this->buildWatermarkImage($config);
        if (!$watermarkImage) {
            imagedestroy($sourceImage);
            return null;
        }

        $this->copyWatermarkToSource($sourceImage, $watermarkImage, $config);

        $targetPath = $this->createWatermarkTempPath($imageType);
        $saved = $this->saveImageByType($sourceImage, $targetPath, $imageType);

        imagedestroy($watermarkImage);
        imagedestroy($sourceImage);

        if (!$saved) {
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }
            return null;
        }

        return $targetPath;
    }

    /**
     * 判断当前上传是否需要加水印。
     */
    protected function shouldApplyWatermark(string $sourcePath): bool
    {
        if (!is_file($sourcePath) || $this->isSkipWatermarkRequest()) {
            return false;
        }

        $config = $this->getWatermarkConfig();
        if ((int)$config[UploaderWatermarkEnum::WATERMARK_ENABLED] !== 1) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        $imageType = (int)($imageInfo[2] ?? 0);
        if (!in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return false;
        }

        if ($imageType === IMAGETYPE_GIF && $this->isAnimatedGif($sourcePath)) {
            return false;
        }

        return $this->hasImageSupport($imageType);
    }

    /**
     * 是否显式跳过水印。
     */
    protected function isSkipWatermarkRequest(): bool
    {
        $value = app()->request->param('skip_watermark', 0);
        return in_array($value, [1, '1', true, 'true', 'on'], true);
    }

    /**
     * 获取水印配置。
     */
    protected function getWatermarkConfig(): array
    {
        if ($this->watermarkConfig !== null) {
            return $this->watermarkConfig;
        }

        $config = [];
        foreach (UploaderWatermarkEnum::DEFAULTS as $key => $default) {
            $config[$key] = function_exists('db_config') ? db_config($key, $default) : $default;
        }

        $config[UploaderWatermarkEnum::WATERMARK_ENABLED] = $this->normalizeWatermarkSwitch(
            $config[UploaderWatermarkEnum::WATERMARK_ENABLED]
        );
        $config[UploaderWatermarkEnum::WATERMARK_TYPE] = in_array(
            $config[UploaderWatermarkEnum::WATERMARK_TYPE],
            UploaderWatermarkEnum::typeValues(),
            true
        ) ? $config[UploaderWatermarkEnum::WATERMARK_TYPE] : UploaderWatermarkEnum::DEFAULTS[UploaderWatermarkEnum::WATERMARK_TYPE];
        $config[UploaderWatermarkEnum::WATERMARK_POSITION] = in_array(
            $config[UploaderWatermarkEnum::WATERMARK_POSITION],
            UploaderWatermarkEnum::positionValues(),
            true
        ) ? $config[UploaderWatermarkEnum::WATERMARK_POSITION] : UploaderWatermarkEnum::DEFAULTS[UploaderWatermarkEnum::WATERMARK_POSITION];

        foreach (UploaderWatermarkEnum::RANGES as $key => $range) {
            $config[$key] = $this->normalizeWatermarkInteger(
                $config[$key],
                (int)$range['min'],
                (int)$range['max'],
                (int)UploaderWatermarkEnum::DEFAULTS[$key]
            );
        }

        $config[UploaderWatermarkEnum::WATERMARK_IMAGE] = trim((string)$config[UploaderWatermarkEnum::WATERMARK_IMAGE]);
        $config[UploaderWatermarkEnum::WATERMARK_TEXT] = trim((string)$config[UploaderWatermarkEnum::WATERMARK_TEXT]);
        $config[UploaderWatermarkEnum::WATERMARK_TEXT_COLOR] = $this->normalizeWatermarkColor(
            (string)$config[UploaderWatermarkEnum::WATERMARK_TEXT_COLOR]
        );

        $this->watermarkConfig = $config;
        return $config;
    }

    /**
     * 构建水印图层。
     */
    protected function buildWatermarkImage(array $config): mixed
    {
        if ($config[UploaderWatermarkEnum::WATERMARK_TYPE] === UploaderWatermarkEnum::TYPE_TEXT) {
            return $this->buildTextWatermarkImage($config);
        }

        return $this->buildImageWatermarkImage($config);
    }

    /**
     * 构建图片水印图层。
     */
    protected function buildImageWatermarkImage(array $config): mixed
    {
        $watermark = $this->resolveWatermarkImagePath((string)$config[UploaderWatermarkEnum::WATERMARK_IMAGE]);
        if (!$watermark || !is_file($watermark['path'])) {
            return null;
        }

        $imageInfo = @getimagesize($watermark['path']);
        if (!$imageInfo) {
            $this->cleanupResolvedWatermarkImage($watermark);
            return null;
        }

        $imageType = (int)($imageInfo[2] ?? 0);
        if (!in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)
            || !$this->hasImageSupport($imageType)
        ) {
            $this->cleanupResolvedWatermarkImage($watermark);
            return null;
        }

        $image = $this->createImageFromPath($watermark['path'], $imageType);
        $this->cleanupResolvedWatermarkImage($watermark);
        if (!$image) {
            return null;
        }

        $this->applyImageOpacity($image, (int)$config[UploaderWatermarkEnum::WATERMARK_OPACITY]);

        return $this->rotateTransparentImage($image, (int)$config[UploaderWatermarkEnum::WATERMARK_ANGLE]);
    }

    /**
     * 构建文字水印图层。
     */
    protected function buildTextWatermarkImage(array $config): mixed
    {
        $text = (string)$config[UploaderWatermarkEnum::WATERMARK_TEXT];
        $fontPath = app()->getRootPath() . 'public/' . self::WATERMARK_FONT;
        if ($text === '' || !is_file($fontPath) || !function_exists('imagettfbbox') || !function_exists('imagettftext')) {
            return null;
        }

        $fontSize = (int)$config[UploaderWatermarkEnum::WATERMARK_TEXT_SIZE];
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);
        if (!$box) {
            return null;
        }

        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        $minY = min($box[1], $box[3], $box[5], $box[7]);
        $maxY = max($box[1], $box[3], $box[5], $box[7]);
        $width = max(1, $maxX - $minX + 8);
        $height = max(1, $maxY - $minY + 8);

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        [$red, $green, $blue] = $this->hexToRgb((string)$config[UploaderWatermarkEnum::WATERMARK_TEXT_COLOR]);
        $color = imagecolorallocatealpha($image, $red, $green, $blue, 0);
        imagettftext($image, $fontSize, 0, 4 - $minX, 4 - $minY, $color, $fontPath, $text);

        return $this->rotateTransparentImage($image, (int)$config[UploaderWatermarkEnum::WATERMARK_TEXT_ANGLE]);
    }

    /**
     * 将水印图层合成到原图。
     */
    protected function copyWatermarkToSource(mixed $sourceImage, mixed $watermarkImage, array $config): void
    {
        imagealphablending($sourceImage, true);
        imagesavealpha($sourceImage, true);

        $position = $this->calculateWatermarkPosition(
            imagesx($sourceImage),
            imagesy($sourceImage),
            imagesx($watermarkImage),
            imagesy($watermarkImage),
            (string)$config[UploaderWatermarkEnum::WATERMARK_POSITION],
            (int)$config[UploaderWatermarkEnum::WATERMARK_X_OFFSET],
            (int)$config[UploaderWatermarkEnum::WATERMARK_Y_OFFSET],
        );

        imagecopy(
            $sourceImage,
            $watermarkImage,
            $position['x'],
            $position['y'],
            0,
            0,
            imagesx($watermarkImage),
            imagesy($watermarkImage)
        );
    }

    /**
     * 计算九宫格水印坐标。
     */
    protected function calculateWatermarkPosition(
        int $sourceWidth,
        int $sourceHeight,
        int $watermarkWidth,
        int $watermarkHeight,
        string $position,
        int $xOffset,
        int $yOffset
    ): array {
        $x = match ($position) {
            UploaderWatermarkEnum::POSITION_TOP_CENTER,
            UploaderWatermarkEnum::POSITION_CENTER,
            UploaderWatermarkEnum::POSITION_BOTTOM_CENTER => (int)(($sourceWidth - $watermarkWidth) / 2) + $xOffset,
            UploaderWatermarkEnum::POSITION_TOP_RIGHT,
            UploaderWatermarkEnum::POSITION_MIDDLE_RIGHT,
            UploaderWatermarkEnum::POSITION_BOTTOM_RIGHT => $sourceWidth - $watermarkWidth - $xOffset,
            default => $xOffset,
        };

        $y = match ($position) {
            UploaderWatermarkEnum::POSITION_MIDDLE_LEFT,
            UploaderWatermarkEnum::POSITION_CENTER,
            UploaderWatermarkEnum::POSITION_MIDDLE_RIGHT => (int)(($sourceHeight - $watermarkHeight) / 2) + $yOffset,
            UploaderWatermarkEnum::POSITION_BOTTOM_LEFT,
            UploaderWatermarkEnum::POSITION_BOTTOM_CENTER,
            UploaderWatermarkEnum::POSITION_BOTTOM_RIGHT => $sourceHeight - $watermarkHeight - $yOffset,
            default => $yOffset,
        };

        return [
            'x' => max(0, min($x, max(0, $sourceWidth - $watermarkWidth))),
            'y' => max(0, min($y, max(0, $sourceHeight - $watermarkHeight))),
        ];
    }

    /**
     * 按图片类型创建图像资源。
     */
    protected function createImageFromPath(string $path, int $imageType): mixed
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    /**
     * 按图片类型保存图像资源。
     */
    protected function saveImageByType(mixed $image, string $path, int $imageType): bool
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => function_exists('imagejpeg') && imagejpeg($image, $path, 90),
            IMAGETYPE_PNG => function_exists('imagepng') && imagepng($image, $path, 6),
            IMAGETYPE_GIF => function_exists('imagegif') && imagegif($image, $path),
            IMAGETYPE_WEBP => function_exists('imagewebp') && imagewebp($image, $path, 90),
            default => false,
        };
    }

    /**
     * 检查 GD 是否支持对应类型。
     */
    protected function hasImageSupport(int $imageType): bool
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') && function_exists('imagegif'),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            default => false,
        };
    }

    /**
     * 透明旋转图片。
     */
    protected function rotateTransparentImage(mixed $image, int $angle): mixed
    {
        if ($angle === 0 || !function_exists('imagerotate')) {
            return $image;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = imagerotate($image, -$angle, $transparent);
        if (!$rotated) {
            return $image;
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        imagedestroy($image);
        return $rotated;
    }

    /**
     * 对图片水印整体应用透明度。
     */
    protected function applyImageOpacity(mixed $image, int $opacity): void
    {
        $opacity = max(0, min(100, $opacity));
        if ($opacity >= 100) {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = $opacity / 100;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $newAlpha = 127 - (int)((127 - $alpha) * $ratio);
                $color = imagecolorallocatealpha($image, $red, $green, $blue, $newAlpha);
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }

    /**
     * 解析水印图片路径。
     */
    protected function resolveWatermarkImagePath(string $image): array|null
    {
        $image = trim($image);
        if ($image === '') {
            return null;
        }

        if (is_file($image)) {
            return ['path' => $image, 'temp' => false];
        }

        $publicPath = $this->resolvePublicImagePath($image);
        if ($publicPath && is_file($publicPath)) {
            return ['path' => $publicPath, 'temp' => false];
        }

        if (!str_starts_with($image, 'http://') && !str_starts_with($image, 'https://')) {
            return null;
        }

        $content = @file_get_contents($image);
        if ($content === false || $content === '') {
            return null;
        }

        $tempPath = $this->createWatermarkTempPath(IMAGETYPE_PNG);
        if (file_put_contents($tempPath, $content) === false) {
            return null;
        }

        return ['path' => $tempPath, 'temp' => true];
    }

    /**
     * 将本地 URL 或 storage 路径转成 public 下文件路径。
     */
    protected function resolvePublicImagePath(string $image): string|null
    {
        $path = $image;
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            $webHost = function_exists('db_config') ? trim((string)db_config(ConfigKeyEnum::WEB_HOST, '')) : '';
            $hostCandidates = [];
            if ($webHost !== '') {
                $hostCandidates[] = rtrim($webHost, '/');
                if (!preg_match('/^https?:\/\//i', $webHost)) {
                    $hostCandidates[] = 'https://' . rtrim($webHost, '/');
                    $hostCandidates[] = 'http://' . rtrim($webHost, '/');
                }
            }

            $matched = false;
            foreach (array_unique($hostCandidates) as $host) {
                if ($host !== '' && str_starts_with($image, $host)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return null;
            }
            $path = (string)parse_url($image, PHP_URL_PATH);
        }

        $path = ltrim($path, '/');
        if ($path === '') {
            return null;
        }

        return app()->getRootPath() . 'public/' . $path;
    }

    /**
     * 清理解析水印图时创建的临时文件。
     */
    protected function cleanupResolvedWatermarkImage(array $watermark): void
    {
        if (($watermark['temp'] ?? false) && is_file((string)$watermark['path'])) {
            @unlink((string)$watermark['path']);
        }
    }

    /**
     * 创建水印临时文件路径。
     */
    protected function createWatermarkTempPath(int $imageType): string
    {
        $extension = match ($imageType) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => 'png',
        };

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'rabbit_watermark_'
            . bin2hex(random_bytes(8))
            . '.'
            . $extension;
    }

    /**
     * 判断 GIF 是否为动图。
     */
    protected function isAnimatedGif(string $path): bool
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return true;
        }

        return preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $content) > 1;
    }

    /**
     * 标准化开关值。
     */
    protected function normalizeWatermarkSwitch(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
    }

    /**
     * 标准化整数范围。
     */
    protected function normalizeWatermarkInteger(mixed $value, int $min, int $max, int $default): int
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int)$value));
    }

    /**
     * 标准化颜色值。
     */
    protected function normalizeWatermarkColor(string $color): string
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
     * 十六进制颜色转 RGB。
     */
    protected function hexToRgb(string $color): array
    {
        $color = ltrim($this->normalizeWatermarkColor($color), '#');

        return [
            hexdec(substr($color, 0, 2)),
            hexdec(substr($color, 2, 2)),
            hexdec(substr($color, 4, 2)),
        ];
    }

}
