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

class  StoreStocktakRecordValidate extends Validate
{
    /**
     * @var string[]
     */
    protected $rule = [
        'store_stocktak_id'=> 'require',
        'store_product_id'=> 'require',
        'store_product_sku_id'=> 'require',
        'total_stock'=> 'require',
        'total_price'=> 'require',
        '_stock'=> 'require',
        '_price'=> 'require',
        '_status'=> 'require',
    ];

    /**
     * @var string[]
     */
    protected $message = [
    ];
}