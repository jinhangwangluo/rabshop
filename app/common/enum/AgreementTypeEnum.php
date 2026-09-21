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
 * 协议类型枚举。
 */
enum AgreementTypeEnum
{
    /** @var string 用户协议 */
    public const USER = 'user';

    /** @var string 隐私政策 */
    public const PRIVACY = 'privacy';

    /** @var string 会员服务协议 */
    public const MEMBER = 'member';

    /** @var string 商家服务协议 */
    public const MERCHANT_SERVICE = 'service';

    /** @var array 协议类型与固定标题配置 */
    public const OPTIONS = [
        ['type' => self::USER, 'title' => '用户协议'],
        ['type' => self::PRIVACY, 'title' => '隐私政策'],
        ['type' => self::MEMBER, 'title' => '会员服务协议'],
        ['type' => self::MERCHANT_SERVICE, 'title' => '商家服务协议'],
    ];

    /**
     * 获取协议类型和标题列表。
     */
    public static function options(): array
    {
        return self::OPTIONS;
    }

    /**
     * 获取全部协议类型。
     */
    public static function types(): array
    {
        return array_column(self::OPTIONS, 'type');
    }

    /**
     * 判断协议类型是否存在。
     */
    public static function has(string $type): bool
    {
        return in_array($type, self::types(), true);
    }

    /**
     * 获取协议标题。
     */
    public static function title(string $type): string
    {
        foreach (self::OPTIONS as $item) {
            if ($item['type'] === $type) {
                return $item['title'];
            }
        }

        return '';
    }

    /**
     * 获取协议排序值。
     */
    public static function sort(string $type): int
    {
        foreach (self::OPTIONS as $index => $item) {
            if ($item['type'] === $type) {
                return ($index + 1) * 10;
            }
        }

        return 0;
    }
}
