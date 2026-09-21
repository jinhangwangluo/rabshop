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
 * 内容审核状态枚举
 */
enum AuditStatusEnum
{
    /** 待审核 */
    public const PENDING = 0;

    /** 已通过 */
    public const APPROVED = 1;

    /** 已驳回 */
    public const REJECTED = 2;

    /** 已下架 */
    public const OFFLINE = 3;

    /**
     * 状态列表
     */
    public static function list(): array
    {
        return [
            self::PENDING  => '待审核',
            self::APPROVED => '已通过',
            self::REJECTED => '已驳回',
            self::OFFLINE  => '已下架',
        ];
    }

    /**
     * 获取状态名称
     */
    public static function getName(int $status): string
    {
        return self::list()[$status] ?? '未知';
    }

    /**
     * 是否为违规状态（驳回或下架）
     */
    public static function isViolation(int $status): bool
    {
        return in_array($status, [self::REJECTED, self::OFFLINE]);
    }

    /**
     * 是否为终态（通过/驳回/下架，不再流转到待审核）
     */
    public static function isFinal(int $status): bool
    {
        return in_array($status, [self::APPROVED, self::REJECTED, self::OFFLINE]);
    }
}
