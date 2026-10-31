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
 * 商户提现申请验证器
 */
class MerchantWithdrawValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'merchant_id' => 'number',
        'amount' => 'float|egt:0',
        'withdraw_type' => 'in:bank,wechat,alipay',
        'bank_name' => 'length:0,50',
        'bank_account' => 'length:0,50',
        'account_name' => 'length:0,30',
        'payment_qrcode' => 'length:0,255',
        'status' => 'in:0,1,2',
        'remark' => 'length:0,255',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'merchant_id.number' => '商户ID格式错误',
        'amount.float' => '提现金额格式错误',
        'amount.egt' => '提现金额不能小于0',
        'withdraw_type.in' => '提现方式错误',
        'bank_name.length' => '银行名称不能超过50个字符',
        'bank_account.length' => '银行账号不能超过50个字符',
        'account_name.length' => '账户姓名不能超过30个字符',
        'payment_qrcode.length' => '收款码不能超过255个字符',
        'status.in' => '状态值错误',
        'remark.length' => '备注长度不能超过255个字符',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['merchant_id', 'amount', 'withdraw_type', 'bank_name', 'bank_account', 'account_name', 'payment_qrcode'],
        'handle' => ['status', 'remark'],
    ];
}
