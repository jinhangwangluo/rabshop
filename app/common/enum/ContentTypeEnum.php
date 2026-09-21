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

/**
 * 内容类型枚举
 */
enum ContentTypeEnum
{
    /** 图文 */
    public const GRAPHIC = 1;

    /** 文字 */
    public const TEXT = 2;

    /** 小说（多章节） */
    public const NOVEL = 3;

    /** 图片 */
    public const IMAGE = 4;

    /** 音频 */
    public const AUDIO = 5;

    /** 视频 */
    public const VIDEO = 6;

    public const CONTENT_TYPE_STR = '1,2,3,4,5,6';

    /**
     * 类型列表
     */
    public static function list(): array
    {
        return [
            self::GRAPHIC => '图文',
            self::TEXT    => '文字',
            self::NOVEL   => '小说',
            self::IMAGE   => '图片',
            self::AUDIO   => '音频',
            self::VIDEO   => '视频',
        ];
    }

    /**
     * 获取类型名称
     */
    public static function getName(int $type): string
    {
        return self::list()[$type] ?? '未知';
    }

    /**
     * 是否需要富文本内容（图文/文字/小说）
     */
    public static function needsContent(int $type): bool
    {
        return in_array($type, [self::GRAPHIC, self::TEXT, self::NOVEL]);
    }

    /**
     * 是否需要媒体文件（音频/视频）
     */
    public static function needsMedia(int $type): bool
    {
        return in_array($type, [self::AUDIO, self::VIDEO]);
    }

    /**
     * 是否需要图片集合（图片类型）
     */
    public static function needsImages(int $type): bool
    {
        return $type === self::IMAGE;
    }

    /**
     * 是否为小说类型（支持多章节）
     */
    public static function isNovel(int $type): bool
    {
        return $type === self::NOVEL;
    }
}
