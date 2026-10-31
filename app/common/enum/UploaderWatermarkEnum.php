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

namespace app\common\enum;

use Rabbit\service\uploader\UploaderService;

/**
 * 上传水印配置枚举。
 */
enum UploaderWatermarkEnum
{
    // ==================== 水印配置 key ====================

    /** @var string 是否开启水印 */
    const WATERMARK_ENABLED = 'watermark_enabled';

    /** @var string 水印类型 */
    const WATERMARK_TYPE = 'watermark_type';

    /** @var string 水印位置 */
    const WATERMARK_POSITION = 'watermark_position';

    /** @var string 横坐标偏移量 */
    const WATERMARK_X_OFFSET = 'watermark_x_offset';

    /** @var string 纵坐标偏移量 */
    const WATERMARK_Y_OFFSET = 'watermark_y_offset';

    /** @var string 图片水印地址 */
    const WATERMARK_IMAGE = 'watermark_image';

    /** @var string 图片水印透明度 */
    const WATERMARK_OPACITY = 'watermark_opacity';

    /** @var string 图片水印倾斜角度 */
    const WATERMARK_ANGLE = 'watermark_angle';

    /** @var string 文字水印内容 */
    const WATERMARK_TEXT = 'watermark_text';

    /** @var string 文字水印字号 */
    const WATERMARK_TEXT_SIZE = 'watermark_text_size';

    /** @var string 文字水印颜色 */
    const WATERMARK_TEXT_COLOR = 'watermark_text_color';

    /** @var string 文字水印旋转角度 */
    const WATERMARK_TEXT_ANGLE = 'watermark_text_angle';

    // ==================== 类型枚举 ====================

    /** @var string 图片水印 */
    const TYPE_IMAGE = 'image';

    /** @var string 文字水印 */
    const TYPE_TEXT = 'text';

    /** @var string 左上 */
    const POSITION_TOP_LEFT = 'top_left';

    /** @var string 上中 */
    const POSITION_TOP_CENTER = 'top_center';

    /** @var string 右上 */
    const POSITION_TOP_RIGHT = 'top_right';

    /** @var string 左中 */
    const POSITION_MIDDLE_LEFT = 'middle_left';

    /** @var string 居中 */
    const POSITION_CENTER = 'center';

    /** @var string 右中 */
    const POSITION_MIDDLE_RIGHT = 'middle_right';

    /** @var string 左下 */
    const POSITION_BOTTOM_LEFT = 'bottom_left';

    /** @var string 下中 */
    const POSITION_BOTTOM_CENTER = 'bottom_center';

    /** @var string 右下 */
    const POSITION_BOTTOM_RIGHT = 'bottom_right';

    /** @var array 水印字段默认值 */
    const DEFAULTS = [
        self::WATERMARK_ENABLED => 0,
        self::WATERMARK_TYPE => self::TYPE_IMAGE,
        self::WATERMARK_POSITION => self::POSITION_TOP_LEFT,
        self::WATERMARK_X_OFFSET => 0,
        self::WATERMARK_Y_OFFSET => 0,
        self::WATERMARK_IMAGE => '',
        self::WATERMARK_OPACITY => 80,
        self::WATERMARK_ANGLE => 0,
        self::WATERMARK_TEXT => '',
        self::WATERMARK_TEXT_SIZE => 16,
        self::WATERMARK_TEXT_COLOR => '#000000',
        self::WATERMARK_TEXT_ANGLE => 0,
    ];

    /** @var array 数值字段范围 */
    const RANGES = [
        self::WATERMARK_X_OFFSET => ['min' => 0, 'max' => 9999],
        self::WATERMARK_Y_OFFSET => ['min' => 0, 'max' => 9999],
        self::WATERMARK_OPACITY => ['min' => 0, 'max' => 100],
        self::WATERMARK_ANGLE => ['min' => -360, 'max' => 360],
        self::WATERMARK_TEXT_SIZE => ['min' => 8, 'max' => 200],
        self::WATERMARK_TEXT_ANGLE => ['min' => -360, 'max' => 360],
    ];

    /**
     * 获取水印配置 key 列表。
     */
    public static function watermarkKeys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * 获取水印类型选项。
     */
    public static function typeOptions(): array
    {
        return [
            ['label' => '图片', 'value' => self::TYPE_IMAGE],
            ['label' => '文字', 'value' => self::TYPE_TEXT],
        ];
    }

    /**
     * 获取水印类型值。
     */
    public static function typeValues(): array
    {
        return array_column(self::typeOptions(), 'value');
    }

    /**
     * 获取九宫格位置选项。
     */
    public static function positionOptions(): array
    {
        return [
            ['label' => '左上', 'value' => self::POSITION_TOP_LEFT],
            ['label' => '上中', 'value' => self::POSITION_TOP_CENTER],
            ['label' => '右上', 'value' => self::POSITION_TOP_RIGHT],
            ['label' => '左中', 'value' => self::POSITION_MIDDLE_LEFT],
            ['label' => '居中', 'value' => self::POSITION_CENTER],
            ['label' => '右中', 'value' => self::POSITION_MIDDLE_RIGHT],
            ['label' => '左下', 'value' => self::POSITION_BOTTOM_LEFT],
            ['label' => '下中', 'value' => self::POSITION_BOTTOM_CENTER],
            ['label' => '右下', 'value' => self::POSITION_BOTTOM_RIGHT],
        ];
    }

    /**
     * 获取九宫格位置值。
     */
    public static function positionValues(): array
    {
        return array_column(self::positionOptions(), 'value');
    }

    /**
     * 获取存储方式选项。
     */
    public static function storageOptions(): array
    {
        return [
            ['label' => '本地存储', 'value' => UploaderService::LOCAL_UPLOADER],
            ['label' => '七牛云存储', 'value' => UploaderService::QINIU_UPLOADER],
            ['label' => '阿里云存储', 'value' => UploaderService::OSS_UPLOADER],
            ['label' => '腾讯云存储', 'value' => UploaderService::COS_UPLOADER],
        ];
    }

    /**
     * 获取存储方式值。
     */
    public static function storageValues(): array
    {
        return array_column(self::storageOptions(), 'value');
    }
}
