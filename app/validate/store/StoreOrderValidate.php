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

namespace app\validate\store;

use think\Validate;

class  StoreOrderValidate extends Validate
{
    /**
     * @var string[]
     */
    protected $rule = [
        'store_order_pid'=> 'require',
        'order_id'=> 'require',
        'trade_no'=> 'require',
        'store_manage_id'=> 'require',
        'real_name'=> 'require',
        'user_phone'=> 'require',
        'user_city'=> 'require',
        'user_address'=> 'require',
        'total_num'=> 'require',
        'total_price'=> 'require',
        'status'=> 'require',
        'type'=> 'require',
    ];

    /**
     * @var string[]
     */
    protected $message = [
    ];
}