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
 * 商品模块枚举
 *
 * 覆盖商品库/店铺商品/SKU 的规格类型、商品类型、状态、审核、共享、包邮等维度常量。
 */
enum ProductEnum
{
    /** 规格类型：单规格 */
    public const SPEC_TYPE_SINGLE = 0;

    /** 规格类型：多规格（组合结果） */
    public const SPEC_TYPE_MULTI = 1;

    /** 商品类型：实物 */
    public const GOODS_TYPE_PHYSICAL = 1;

    /** 商品类型：虚拟 */
    public const GOODS_TYPE_VIRTUAL = 2;

    /** 商品类型：内容 */
    public const GOODS_TYPE_CONTENT = 3;

    /** 状态：隐藏/下架/禁用 */
    public const STATUS_HIDDEN = 0;

    /** 状态：正常/上架/启用 */
    public const STATUS_OPEN = 1;

    /** 审核状态：待审核 */
    public const AUDIT_STATUS_PENDING = 0;

    /** 审核状态：通过 */
    public const AUDIT_STATUS_PASSED = 1;

    /** 审核状态：驳回 */
    public const AUDIT_STATUS_REJECTED = 2;

    /** 共享：私有（商户自建默认） */
    public const IS_SHARE_NO = 0;

    /** 共享：共享（平台商品默认） */
    public const IS_SHARE_YES = 1;

    /** 包邮方式：按运费模板计算 */
    public const FREE_SHIPPING_NO = 0;

    /** 包邮方式：商品级直接包邮 */
    public const FREE_SHIPPING_YES = 1;

    /** SKU 状态：隐藏 */
    public const SKU_STATUS_HIDDEN = 0;

    /** SKU 状态：正常 */
    public const SKU_STATUS_OPEN = 1;

    /**
     * 规格类型列表
     *
     * @return array<int,string> 规格类型 => 名称
     */
    public static function specTypeList(): array
    {
        return [
            self::SPEC_TYPE_SINGLE => '单规格',
            self::SPEC_TYPE_MULTI  => '多规格',
        ];
    }

    /**
     * 获取规格类型名称
     *
     * @param int $type 规格类型
     * @return string 名称
     */
    public static function getSpecTypeName(int $type): string
    {
        return self::specTypeList()[$type] ?? '未知';
    }

    /**
     * 商品类型列表
     *
     * @return array<int,string> 商品类型 => 名称
     */
    public static function goodsTypeList(): array
    {
        return [
            self::GOODS_TYPE_PHYSICAL => '实物',
            self::GOODS_TYPE_VIRTUAL  => '虚拟',
            self::GOODS_TYPE_CONTENT  => '内容',
        ];
    }

    /**
     * 获取商品类型名称
     *
     * @param int $type 商品类型
     * @return string 名称
     */
    public static function getGoodsTypeName(int $type): string
    {
        return self::goodsTypeList()[$type] ?? '未知';
    }

    /**
     * 状态列表（店铺商品/库商品共用）
     *
     * @return array<int,string> 状态 => 名称
     */
    public static function statusList(): array
    {
        return [
            self::STATUS_HIDDEN => '隐藏',
            self::STATUS_OPEN   => '正常',
        ];
    }

    /**
     * 获取状态名称
     *
     * @param int $status 状态
     * @return string 名称
     */
    public static function getStatusName(int $status): string
    {
        return self::statusList()[$status] ?? '未知';
    }

    /**
     * 审核状态列表
     *
     * @return array<int,string> 审核状态 => 名称
     */
    public static function auditStatusList(): array
    {
        return [
            self::AUDIT_STATUS_PENDING  => '待审核',
            self::AUDIT_STATUS_PASSED   => '通过',
            self::AUDIT_STATUS_REJECTED => '驳回',
        ];
    }

    /**
     * 获取审核状态名称
     *
     * @param int $status 审核状态
     * @return string 名称
     */
    public static function getAuditStatusName(int $status): string
    {
        return self::auditStatusList()[$status] ?? '未知';
    }

    /**
     * 共享状态列表
     *
     * @return array<int,string> 共享状态 => 名称
     */
    public static function shareList(): array
    {
        return [
            self::IS_SHARE_NO  => '私有',
            self::IS_SHARE_YES => '共享',
        ];
    }

    /**
     * 获取共享状态名称
     *
     * @param int $isShare 共享状态
     * @return string 名称
     */
    public static function getShareName(int $isShare): string
    {
        return self::shareList()[$isShare] ?? '未知';
    }

    /**
     * 包邮方式列表
     *
     * @return array<int,string> 包邮方式 => 名称
     */
    public static function freeShippingList(): array
    {
        return [
            self::FREE_SHIPPING_NO  => '按运费模板计算',
            self::FREE_SHIPPING_YES => '包邮',
        ];
    }

    /**
     * 获取包邮方式名称
     *
     * @param int $isFreeShipping 包邮方式
     * @return string 名称
     */
    public static function getFreeShippingName(int $isFreeShipping): string
    {
        return self::freeShippingList()[$isFreeShipping] ?? '未知';
    }
}
