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

namespace app\common\util;

/**
 * 文件类型判断服务类
 * Class FileTypeService
 * @package app\common\service\util
 */
class FileTypeService
{
    // 文件类型常量定义
    const TYPE_IMAGE = 0;  // 图片
    const TYPE_AUDIO = 1;  // 音频
    const TYPE_VIDEO = 2;  // 视频
    const TYPE_OTHER = 3;  // 其他文件
    
    // 各类型对应的文件后缀
    private static $imageExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff', 'tif'
    ];
    
    private static $audioExtensions = [
        'mp3', 'wav', 'ogg', 'flac', 'aac', 'wma', 'm4a', 'ape', 'amr', 'mid', 'midi'
    ];
    
    private static $videoExtensions = [
        'mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'mpeg', 'mpg', 'm4v', '3gp', 'rmvb', 'rm'
    ];
    
    /**
     * 根据文件后缀获取文件类型
     * @param string $filename 文件名
     * @return int 文件类型 0=图片, 1=音频, 2=视频, 3=其他
     */
    public static function getFileTypeByExtension(string $filename): int
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (empty($extension)) {
            return self::TYPE_OTHER;
        }
        
        if (in_array($extension, self::$imageExtensions)) {
            return self::TYPE_IMAGE;
        }
        
        if (in_array($extension, self::$audioExtensions)) {
            return self::TYPE_AUDIO;
        }
        
        if (in_array($extension, self::$videoExtensions)) {
            return self::TYPE_VIDEO;
        }
        
        return self::TYPE_OTHER;
    }
    
    /**
     * 获取文件类型名称
     * @param int $fileType 文件类型数字
     * @return string 文件类型名称
     */
    public static function getFileTypeName(int $fileType): string
    {
        $typeNames = [
            self::TYPE_IMAGE => '图片',
            self::TYPE_AUDIO => '音频',
            self::TYPE_VIDEO => '视频',
            self::TYPE_OTHER => '其他文件'
        ];
        
        return $typeNames[$fileType] ?? '未知类型';
    }
    
    /**
     * 判断是否为图片文件
     * @param string $filename 文件名
     * @return bool
     */
    public static function isImage(string $filename): bool
    {
        return self::getFileTypeByExtension($filename) === self::TYPE_IMAGE;
    }
    
    /**
     * 判断是否为音频文件
     * @param string $filename 文件名
     * @return bool
     */
    public static function isAudio(string $filename): bool
    {
        return self::getFileTypeByExtension($filename) === self::TYPE_AUDIO;
    }
    
    /**
     * 判断是否为视频文件
     * @param string $filename 文件名
     * @return bool
     */
    public static function isVideo(string $filename): bool
    {
        return self::getFileTypeByExtension($filename) === self::TYPE_VIDEO;
    }
    
    /**
     * 获取所有支持的文件扩展名
     * @return array
     */
    public static function getAllSupportedExtensions(): array
    {
        return array_merge(
            self::$imageExtensions,
            self::$audioExtensions,
            self::$videoExtensions
        );
    }
    
    /**
     * 获取指定类型的文件扩展名
     * @param int $fileType 文件类型
     * @return array
     */
    public static function getExtensionsByType(int $fileType): array
    {
        switch ($fileType) {
            case self::TYPE_IMAGE:
                return self::$imageExtensions;
            case self::TYPE_AUDIO:
                return self::$audioExtensions;
            case self::TYPE_VIDEO:
                return self::$videoExtensions;
            default:
                return [];
        }
    }
}