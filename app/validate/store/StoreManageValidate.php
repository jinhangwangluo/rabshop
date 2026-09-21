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

class  StoreManageValidate extends Validate
{
    /**
     * @var string[]
     */
    protected $rule = [
        'name'=> 'require',
        'pic'=> 'require',
        'type'=> 'require',
        'status'=> 'require',
        // 'city'=> 'require',
        'address'=> 'require',
        // 'elm_store_id'=> 'require',
        // 'meituan_store_id'=> 'require',
        // 'multi_store_id'=> 'require',
        // 'elm_store_appid'=> 'require',
        // 'meituan_store_appid'=> 'require',
        // 'elm_store_token'=> 'require',
        // 'meituan_store_token'=> 'require',
    ];

    /**
     * @var string[]
     */
    protected $message = [
    ];
}