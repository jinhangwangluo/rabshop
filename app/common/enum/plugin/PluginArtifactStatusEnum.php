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

declare(strict_types=1);

namespace app\common\enum\plugin;

/**
 * 制品下载阶段状态。官网的构建状态不在此枚举中。
 */
enum PluginArtifactStatusEnum
{
    public const PENDING = 'pending';
    public const DOWNLOADING = 'downloading';
    public const DOWNLOADED = 'downloaded';
    public const VERIFIED = 'verified';
    public const FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::DOWNLOADING,
            self::DOWNLOADED,
            self::VERIFIED,
            self::FAILED,
        ];
    }
}
