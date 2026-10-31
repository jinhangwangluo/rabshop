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
 * 插件安装生命周期状态。
 */
enum PluginLifecycleStatusEnum
{
    public const UNINSTALLED = 'uninstalled';
    public const INSTALLING = 'installing';
    public const STAGING = 'staging';
    public const MIGRATING = 'migrating';
    public const PUBLISHING = 'publishing';
    public const ACTIVATING = 'activating';
    public const ACTIVE = 'active';
    public const UPGRADING = 'upgrading';
    public const DISABLING = 'disabling';
    public const DISABLED = 'disabled';
    public const UNINSTALLING = 'uninstalling';
    public const ROLLING_BACK = 'rolling_back';
    public const FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::UNINSTALLED,
            self::INSTALLING,
            self::STAGING,
            self::MIGRATING,
            self::PUBLISHING,
            self::ACTIVATING,
            self::ACTIVE,
            self::UPGRADING,
            self::DISABLING,
            self::DISABLED,
            self::UNINSTALLING,
            self::ROLLING_BACK,
            self::FAILED,
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
