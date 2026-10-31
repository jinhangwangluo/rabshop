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
 * 店铺商品模型
 *
 * 店铺运营副本：单规格（spec_type=0）直接引用库商品，多规格（spec_type=1）由多个
 * 单规格库商品组合而来。价格/库存/销量等与 SKU 行对齐：
 * - stock 为 SKU 库存汇总，扣减以 SKU 为准
 * - sales/view_count 为订单链路与详情访问累计的真实值
 * - virtual_sales/virtual_view_count 为可配置的展示基数，展示值 = 真实值 + 虚拟值
 */
class StoreProduct extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'store_product';

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
     * 搜索器：商户ID
     *
     * @param mixed $query 查询对象
     * @param mixed $value 商户ID
     * @return void
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('merchant_id', $value);
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
     * 搜索器：关联编码
     *
     * @param mixed $query 查询对象
     * @param mixed $value 商品编码
     * @return void
     */
    public function searchProductCodeAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('product_code', $value);
        }
    }

    /**
     * 搜索器：主分类
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
     * 搜索器：规格类型
     *
     * @param mixed $query 查询对象
     * @param mixed $value 规格类型
     * @return void
     */
    public function searchSpecTypeAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('spec_type', $value);
        }
    }

    /**
     * 搜索器：商品类型
     *
     * @param mixed $query 查询对象
     * @param mixed $value 商品类型
     * @return void
     */
    public function searchGoodsTypeAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('goods_type', $value);
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

    /**
     * 获取器：展示销量（真实销量 + 虚拟销量基数，仅前台展示）
     *
     * @param mixed $value 字段原始值
     * @param mixed $data 整行数据
     * @return int 展示销量
     */
    public function getDisplaySalesAttr($value, $data)
    {
        return (int) ($data['sales'] ?? 0) + (int) ($data['virtual_sales'] ?? 0);
    }

    /**
     * 获取器：展示浏览量（真实浏览量 + 虚拟浏览量基数，仅前台展示）
     *
     * @param mixed $value 字段原始值
     * @param mixed $data 整行数据
     * @return int 展示浏览量
     */
    public function getDisplayViewCountAttr($value, $data)
    {
        return (int) ($data['view_count'] ?? 0) + (int) ($data['virtual_view_count'] ?? 0);
    }
}
