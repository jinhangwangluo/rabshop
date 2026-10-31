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

namespace app\common\model\store;

use app\common\model\Base;

/**
 * 店铺商品规格配置模型
 */
class StoreProductSpecConfig extends Base
{
    protected $name = 'store_product_spec_config';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'library_product_ids' => 'array',
        'custom_specs' => 'array',
        'custom_skus' => 'array',
        'param_snapshot' => 'array',
    ];
}
