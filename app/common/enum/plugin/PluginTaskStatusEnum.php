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
 * 插件任务状态。
 */
enum PluginTaskStatusEnum
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    /** 兼容部分旧调用方使用 processing 的命名。 */
    public const PROCESSING = self::RUNNING;
    public const SUCCESS = self::SUCCEEDED;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::RUNNING,
            self::SUCCEEDED,
            self::FAILED,
            self::CANCELLED,
        ];
    }

    public static function isTerminal(string $value): bool
    {
        return in_array($value, [self::SUCCEEDED, self::FAILED, self::CANCELLED], true);
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
