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

class  StoreProductStockRecordSkuValidate extends Validate
{
    /**
     * @var string[]
     */
    protected $rule = [
        'store_product_stock_record_id'=> 'require',
        'store_product_sku_id'=> 'require',
        'price'=> 'require',
        'sum_price'=> 'require',
        'stock'=> 'require',
    ];

    /**
     * @var string[]
     */
    protected $message = [
    ];
}