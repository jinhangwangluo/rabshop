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
 * 运费模块枚举
 *
 * 覆盖运费模板的计价方式、发货时间选项、默认模板、包邮、规则类型等维度常量。
 */
enum FreightEnum
{
    /** 计价方式：按件数 */
    public const CHARGE_TYPE_COUNT = 1;

    /** 计价方式：按重量（统一千克） */
    public const CHARGE_TYPE_WEIGHT = 2;

    /** 默认模板：否 */
    public const IS_DEFAULT_NO = 0;

    /** 默认模板：是 */
    public const IS_DEFAULT_YES = 1;

    /** 包邮：否（按阶梯计算） */
    public const IS_FREE_NO = 0;

    /** 包邮：是（所有地区免运费） */
    public const IS_FREE_YES = 1;

    /** 规则类型：指定地区 */
    public const RULE_TYPE_CITY = 1;

    /** 发货时间选项常量：24小时内 */
    public const DELIVERY_TIME_24H = '24小时内';

    /** 发货时间选项常量：48小时内 */
    public const DELIVERY_TIME_48H = '48小时内';

    /** 发货时间选项常量：72小时内 */
    public const DELIVERY_TIME_72H = '72小时内';

    /** 发货时间选项常量：3-5天 */
    public const DELIVERY_TIME_3_5D = '3-5天';

    /**
     * 计价方式列表
     *
     * @return array<int,string> 计价方式 => 名称
     */
    public static function chargeTypeList(): array
    {
        return [
            self::CHARGE_TYPE_COUNT  => '按件数',
            self::CHARGE_TYPE_WEIGHT => '按重量',
        ];
    }

    /**
     * 获取计价方式名称
     *
     * @param int $chargeType 计价方式
     * @return string 名称
     */
    public static function getChargeTypeName(int $chargeType): string
    {
        return self::chargeTypeList()[$chargeType] ?? '未知';
    }

    /**
     * 发货时间选项（模板创建/编辑页下拉框数据源）
     *
     * @return array<int,string> 选项值 => 展示文案
     */
    public static function deliveryTimeOptions(): array
    {
        return [
            self::DELIVERY_TIME_24H  => '24小时内',
            self::DELIVERY_TIME_48H  => '48小时内',
            self::DELIVERY_TIME_72H  => '72小时内',
            self::DELIVERY_TIME_3_5D => '3-5天',
        ];
    }

    /**
     * 获取发货时间文案
     *
     * @param string $value 发货时间选项值
     * @return string 文案
     */
    public static function getDeliveryTimeName(string $value): string
    {
        return self::deliveryTimeOptions()[$value] ?? $value;
    }

    /**
     * 默认模板状态列表
     *
     * @return array<int,string> 状态 => 名称
     */
    public static function isDefaultList(): array
    {
        return [
            self::IS_DEFAULT_NO  => '否',
            self::IS_DEFAULT_YES => '是',
        ];
    }

    /**
     * 包邮状态列表
     *
     * @return array<int,string> 状态 => 名称
     */
    public static function isFreeList(): array
    {
        return [
            self::IS_FREE_NO  => '否',
            self::IS_FREE_YES => '是',
        ];
    }

    /**
     * 规则类型列表
     *
     * @return array<int,string> 规则类型 => 名称
     */
    public static function ruleTypeList(): array
    {
        return [
            self::RULE_TYPE_CITY => '指定地区',
        ];
    }
}
