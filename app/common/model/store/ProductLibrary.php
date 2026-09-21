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
 * 商品库模型
 *
 * 商品库 = 单规格商品池（平台/商户共建），上架时复制为店铺商品副本。
 * owner_merchant_id=0 平台自建（默认共享），>0 商户自建（默认私有可设共享）。
 */
class ProductLibrary extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'product_library';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     * @var array
     */
    protected $type = [
        'price' => 'float',
        'ot_price' => 'float',
        'store_price' => 'float',
        'pics' => 'array',
        'attr_values' => 'array',
        'plugin_payload_json' => 'array',
    ];

    /**
     * 搜索器：归属商户
     *
     * @param mixed $query 查询对象
     * @param mixed $value 商户ID
     * @return void
     */
    public function searchOwnerMerchantIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('owner_merchant_id', $value);
        }
    }

    /**
     * 搜索器：商品名称（模糊）
     *
     * @param mixed $query 查询对象
     * @param mixed $value 商品名称关键字
     * @return void
     */
    public function searchNameAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：库编码
     *
     * @param mixed $query 查询对象
     * @param mixed $value 库编码
     * @return void
     */
    public function searchProductCodeAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('product_code', $value);
        }
    }

    /**
     * 搜索器：分类
     *
     * @param mixed $query 查询对象
     * @param mixed $value 分类ID
     * @return void
     */
    public function searchCategoryIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('category_id', $value);
        }
    }

    /**
     * 搜索器：品牌
     *
     * @param mixed $query 查询对象
     * @param mixed $value 品牌ID
     * @return void
     */
    public function searchBrandIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('brand_id', $value);
        }
    }

    /**
     * 搜索器：是否共享
     *
     * @param mixed $query 查询对象
     * @param mixed $value 共享状态
     * @return void
     */
    public function searchIsShareAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_share', $value);
        }
    }

    /**
     * 搜索器：状态
     *
     * @param mixed $query 查询对象
     * @param mixed $value 状态
     * @return void
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：创建时间区间
     *
     * @param mixed $query 查询对象
     * @param mixed $value 时间区间数组
     * @return void
     */
    public function searchCreateTimeAttr($query, $value)
    {
        if (!empty($value) && is_array($value)) {
            $query->whereBetweenTime('create_time', $value[0], $value[1]);
        }
    }
}
