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

namespace app\common\validate;

use think\Validate;

/**
 * 支付展示配置验证器。
 */
class PaymentConfigValidate extends Validate
{
    /**
     * 验证规则。
     */
    protected $rule = [
        'methods' => 'require|array',
    ];

    /**
     * 错误提示。
     */
    protected $message = [
        'methods.require' => '支付方式配置不能为空',
        'methods.array' => '支付方式配置格式错误',
    ];
}
