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
 * 内容审核类型枚举
 */
enum AuditTypeEnum
{
    /** 人工审核 */
    public const MANUAL = 1;

    /** AI审核 */
    public const AI = 2;

    /**
     * 类型列表
     */
    public static function list(): array
    {
        return [
            self::MANUAL => '人工审核',
            self::AI     => 'AI审核',
        ];
    }

    /**
     * 获取类型名称
     */
    public static function getName(int $type): string
    {
        return self::list()[$type] ?? '未知';
    }
}
