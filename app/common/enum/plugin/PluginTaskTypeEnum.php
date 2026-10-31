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
 * 插件本地任务类型。
 *
 * 该枚举只描述 v1.2 本地任务，不代表官网侧的构建或支付任务。
 */
enum PluginTaskTypeEnum
{
    public const DOWNLOAD = 'download';
    public const INSTALL = 'install';
    public const UPGRADE = 'upgrade';
    public const DISABLE = 'disable';
    public const UNINSTALL = 'uninstall';
    public const ROLLBACK = 'rollback';
    public const BUILD_SYNC = 'build_sync';
    public const MP_UPLOAD_SYNC = 'mp_upload_sync';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DOWNLOAD,
            self::INSTALL,
            self::UPGRADE,
            self::DISABLE,
            self::UNINSTALL,
            self::ROLLBACK,
            self::BUILD_SYNC,
            self::MP_UPLOAD_SYNC,
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
