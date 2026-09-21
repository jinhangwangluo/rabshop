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
 * 财务记录验证器
 */
class FinanceRecordValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'pid' => 'number',
        'user_id' => 'number',
        'merchant_id' => 'number',
        'type' => 'require|length:1,20',
        'amount' => 'require|float|gt:0',
        'change_type' => 'require|in:in,out',
        'fund_status' => 'in:frozen,available,reversed',
        'remark' => 'length:0,255',
        'order_sn' => 'length:0,32',
        'live_room_id' => 'number',
        'live_gift_record_id' => 'number',
        'live_anchor_id' => 'number',
    ];

    /**
     * 错误提示
     */
    protected $message = [
        'pid.number' => '父流水ID格式错误',
        'user_id.number' => '用户ID格式错误',
        'merchant_id.number' => '商户ID格式错误',
        'type.require' => '类型不能为空',
        'type.length' => '类型长度为1-20个字符',
        'amount.require' => '金额不能为空',
        'amount.float' => '金额格式错误',
        'amount.gt' => '金额必须大于0',
        'change_type.require' => '变动类型不能为空',
        'change_type.in' => '变动类型错误',
        'fund_status.in' => '资金状态错误',
        'remark.length' => '备注长度不能超过255个字符',
        'order_sn.length' => '订单号长度不能超过32个字符',
        'live_room_id.number' => '直播间ID格式错误',
        'live_gift_record_id.number' => '直播礼物流水ID格式错误',
        'live_anchor_id.number' => '主播ID格式错误',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['pid', 'user_id', 'merchant_id', 'type', 'amount', 'change_type', 'fund_status', 'remark', 'order_sn', 'live_room_id', 'live_gift_record_id', 'live_anchor_id'],
        'edit' => ['pid', 'user_id', 'merchant_id', 'type', 'amount', 'change_type', 'fund_status', 'remark', 'order_sn', 'live_room_id', 'live_gift_record_id', 'live_anchor_id'],
    ];
}
