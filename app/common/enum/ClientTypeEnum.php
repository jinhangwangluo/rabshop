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
 * 客户端类型枚举
 */
enum ClientTypeEnum
{
    /**
     * 平台端
     */
    const PLATFORM = 1;

    /**
     * 商户端
     */
    const MERCHANT = 2;

    /**
     * 用户端
     */
    const USER = 3;

    /**
     * 直播间（用户观看直播时的独立连接）
     */
    const LIVE_ROOM = 4;

    /**
     * 获取客户端类型名称
     * @param int $clientType
     * @return string
     */
    public static function getName(int $clientType): string
    {
        $types = [
            self::PLATFORM  => '平台端',
            self::MERCHANT  => '商户端',
            self::USER      => '用户端',
            self::LIVE_ROOM => '直播间'
        ];

        return $types[$clientType] ?? '未知';
    }

    /**
     * 验证客户端类型是否有效
     * @param int $clientType
     * @return bool
     */
    public static function isValid(int $clientType): bool
    {
        return isset([
            self::PLATFORM,
            self::MERCHANT,
            self::USER,
            self::LIVE_ROOM
        ][$clientType - 1]);
    }
}
