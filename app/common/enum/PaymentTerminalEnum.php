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
 * 支付方式展示终端枚举。
 */
enum PaymentTerminalEnum
{
    /** @var string PC 端 */
    const TERMINAL_PC = 'pc';

    /** @var string H5 端 */
    const TERMINAL_H5 = 'h5';

    /** @var string 微信浏览器 H5 */
    const TERMINAL_WECHAT_H5 = 'wechat_h5';

    /** @var string 微信小程序 */
    const TERMINAL_MP_WEIXIN = 'mp_weixin';

    /** @var array 终端名称 */
    const TERMINAL_ALL = [
        self::TERMINAL_PC => 'PC端',
        self::TERMINAL_H5 => 'H5端',
        self::TERMINAL_WECHAT_H5 => '微信H5',
        self::TERMINAL_MP_WEIXIN => '微信小程序',
    ];

    /**
     * 获取终端选项。
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::TERMINAL_ALL as $value => $label) {
            $options[] = compact('value', 'label');
        }
        return $options;
    }

    /**
     * 获取有效终端值。
     */
    public static function values(): array
    {
        return array_keys(self::TERMINAL_ALL);
    }
}
