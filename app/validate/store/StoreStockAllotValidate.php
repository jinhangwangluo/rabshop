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

class  StoreStockAllotValidate extends Validate
{
    /**
     * @var string[]
     */
    protected $rule = [
        'before_store_manage_id'=> 'require',
        'after_store_manage_id'=> 'require',
        'type'=> 'require|in:0,1',
        'product'=> 'require|array',
//        'product.*.after_product_id'=> 'require',
//        'product.*.after_id'=> 'require',
    ];

    /**
     * @var string[]
     */
    protected $message = [
//        'product.*.after_product_id.require'=>'请选择调入商品',
//        'product.*.after_id.require'=>'请选择调入商品SKU',
    ];
}
