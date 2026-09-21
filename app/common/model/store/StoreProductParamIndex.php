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
use think\model\concern\SoftDelete;

/**
 * 店铺商品参数索引模型。
 */
class StoreProductParamIndex extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'store_product_param_index';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    public function searchStoreProductIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('store_product_id', $value);
        }
    }

    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('merchant_id', $value);
        }
    }

    public function searchCategoryIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('category_id', $value);
        }
    }
}
