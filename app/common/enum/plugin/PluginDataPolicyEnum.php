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
 * 卸载时插件业务数据处理策略。
 */
enum PluginDataPolicyEnum
{
    public const RETAIN = 'retain';
    public const DELETE = 'delete';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::RETAIN, self::DELETE];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
