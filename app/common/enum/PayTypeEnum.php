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

enum PayTypeEnum
{
    // 微信
    const PAY_TYPE_WECHAT = 'wechat';
    // 支付宝
    const PAY_TYPE_ALIPAY = 'alipay';
    // 线下支付
    const PAY_TYPE_OFFLINE = 'offline';
    // 余额
    const PAY_TYPE_YUE = 'yue';
    // 免支付
    const PAY_TYPE_FREE = 'free';

    // 所有支付方式
    const PAY_TYPE_ALL = [
        self::PAY_TYPE_WECHAT => '微信支付',
        self::PAY_TYPE_ALIPAY => '支付宝',
        self::PAY_TYPE_OFFLINE => '线下支付',
        self::PAY_TYPE_YUE => '余额支付',
        self::PAY_TYPE_FREE => '免支付'
    ];

    // 后台可配置展示的支付方式
    const DISPLAY_PAY_TYPE_ALL = [
        self::PAY_TYPE_YUE,
        self::PAY_TYPE_WECHAT,
        self::PAY_TYPE_ALIPAY,
        self::PAY_TYPE_OFFLINE,
    ];

    /**
     * 获取支付方式选项。
     */
    public static function options(bool $displayOnly = false): array
    {
        $types = $displayOnly ? self::DISPLAY_PAY_TYPE_ALL : array_keys(self::PAY_TYPE_ALL);
        $options = [];
        foreach ($types as $value) {
            $options[] = [
                'value' => $value,
                'label' => self::PAY_TYPE_ALL[$value] ?? $value,
            ];
        }
        return $options;
    }

    /**
     * 获取支付方式默认展示配置。
     */
    public static function defaultDisplayMethods(): array
    {
        return [
            [
                'pay_type' => self::PAY_TYPE_YUE,
                'image' => '',
                'text' => '余额',
                'title' => '余额支付',
                'subtitle' => '使用账户余额完成支付。',
                'status' => 1,
                'terminals' => ['h5', 'wechat_h5', 'mp_weixin'],
                'sort' => 10,
            ],
            [
                'pay_type' => self::PAY_TYPE_WECHAT,
                'image' => '',
                'text' => '微信',
                'title' => '微信支付',
                'subtitle' => '支持微信环境、小程序及移动端微信支付。',
                'status' => 1,
                'terminals' => ['wechat_h5', 'mp_weixin'],
                'sort' => 20,
            ],
            [
                'pay_type' => self::PAY_TYPE_ALIPAY,
                'image' => '',
                'text' => '支付宝',
                'title' => '支付宝支付',
                'subtitle' => '支持 PC 与 H5 场景下的支付宝支付。',
                'status' => 0,
                'terminals' => ['h5', 'wechat_h5'],
                'sort' => 30,
            ],
            [
                'pay_type' => self::PAY_TYPE_OFFLINE,
                'image' => '',
                'text' => '线下',
                'title' => '线下支付',
                'subtitle' => '通过线下转账、到店付款等方式完成支付。',
                'status' => 0,
                'terminals' => ['h5', 'wechat_h5', 'mp_weixin'],
                'sort' => 40,
            ],
        ];
    }
}
