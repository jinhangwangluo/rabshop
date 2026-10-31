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
 * 商品分类关联模型
 *
 * 店铺商品多分类关联（category type=10），store_product_id + category_id 唯一。
 * 用于商户后台及移动端商户店铺分类筛选；平台商品分类仍使用 store_product.category_id。
 */
class ProductCategoryRelation extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'product_category_relation';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 搜索器：店铺商品ID
     *
     * @param mixed $query 查询对象
     * @param mixed $value 店铺商品ID
     * @return void 无返回值。
     */
    public function searchStoreProductIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('store_product_id', $value);
        }
    }

    /**
     * 搜索器：分类ID
     *
     * @param mixed $query 查询对象
     * @param mixed $value 分类ID
     * @return void 无返回值。
     */
    public function searchCategoryIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('category_id', $value);
        }
    }
}
