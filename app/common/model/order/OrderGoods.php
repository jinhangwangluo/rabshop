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

namespace app\common\model\order;

use app\common\model\Base;

/**
 * 订单商品模型
 */
class OrderGoods extends Base
{
    /**
     * 表名
     */
    protected $name = 'order_goods';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     */
    protected $type = [
        'goods_amount' => 'float',
        'price' => 'float',
        'total_price' => 'float',
        'commission_base_amount' => 'float',
        'first_commission_rate' => 'float',
        'second_commission_rate' => 'float',
        'commission_level' => 'integer',
        'first_commission' => 'float',
        'second_commission' => 'float',
        'points_deduct_amount' => 'float',
        'platform_coupon_amount' => 'float',
        'merchant_coupon_amount' => 'float',
        'member_discount_amount' => 'float',
        'member_discount_rate' => 'float',
        'goods_info' => 'json',
        'stock_reserved_num' => 'integer',
        'stock_restored_num' => 'integer',
    ];

    /**
     * 订单类型常量
     */
    const ORDER_TYPE_CONTENT = 1;    // 内容订单
    const ORDER_TYPE_GOODS = 2;      // 商品订单
    const ORDER_TYPE_SPECIAL = 3;    // 专题订单

    /**
     * 商品类型常量
     */
    const GOODS_TYPE_PHYSICAL = 1;   // 实物商品
    const GOODS_TYPE_VIRTUAL = 2;    // 虚拟商品
    const GOODS_TYPE_CONTENT = 3;    // 内容商品

    /**
     * 搜索器：订单ID
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param mixed $value 搜索器需要匹配的订单商品字段值；空字符串表示不筛选。
     * @return void 无返回值；仅追加订单编号筛选条件。
     */
    public function searchOrderIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('order_id', $value);
        }
    }

    /**
     * 搜索器：商品ID
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param mixed $value 搜索器需要匹配的订单商品字段值；空字符串表示不筛选。
     * @return void 无返回值；仅追加原商品编号筛选条件。
     */
    public function searchGoodsIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('goods_id', $value);
        }
    }

    /**
     * 搜索器：商户ID
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param mixed $value 搜索器需要匹配的订单商品字段值；空字符串表示不筛选。
     * @return void 无返回值；仅追加商户归属筛选条件。
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：订单类型
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param mixed $value 搜索器需要匹配的订单商品字段值；空字符串表示不筛选。
     * @return void 无返回值；仅追加订单资源类型筛选条件。
     */
    public function searchOrderTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('order_type', $value);
        }
    }

    /**
     * 搜索器：商品类型
     *
     * @param mixed $query 需要追加筛选条件的查询对象。
     * @param mixed $value 搜索器需要匹配的订单商品字段值；空字符串表示不筛选。
     * @return void 无返回值；仅追加商品类型筛选条件。
     */
    public function searchGoodsTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('goods_type', $value);
        }
    }

    /**
     * 获取器：订单类型文本
     *
     * @param mixed $value 当前计算属性原值，类型名称以原始字段为准。
     * @param mixed $data 当前订单商品原始字段，用于解析类型名称。
     * @return string 当前订单资源类型的中文名称；未知编码显示未知。
     */
    public function getOrderTypeTextAttr($value, $data)
    {
        $types = [
            self::ORDER_TYPE_CONTENT => '内容订单',
            self::ORDER_TYPE_GOODS => '商品订单',
            self::ORDER_TYPE_SPECIAL => '专题订单',
        ];
        return $types[$data['order_type'] ?? 1] ?? '未知';
    }

    /**
     * 获取器：商品类型文本
     *
     * @param mixed $value 当前计算属性原值，类型名称以原始字段为准。
     * @param mixed $data 当前订单商品原始字段，用于解析类型名称。
     * @return string 当前成交商品类型的中文名称；未知编码显示未知。
     */
    public function getGoodsTypeTextAttr($value, $data)
    {
        $types = [
            self::GOODS_TYPE_PHYSICAL => '实物商品',
            self::GOODS_TYPE_VIRTUAL => '虚拟商品',
            self::GOODS_TYPE_CONTENT => '内容商品',
        ];
        return $types[$data['goods_type'] ?? 1] ?? '未知';
    }

    /**
     * 关联：订单
     *
     * @return \think\model\relation\BelongsTo 当前商品明细所属业务订单的关联对象。
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
