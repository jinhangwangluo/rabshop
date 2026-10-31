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
 * 商品参数模板模型
 *
 * 商品参数模板（如：产地/保质期），用于按分类展示与商品参数筛选。
 */
class ProductAttribute extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'product_attribute';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     *
     * @var array
     */
    protected $type = [
        'params_json' => 'array',
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
     * 搜索器：属性名称（模糊）
     *
     * @param mixed $query 查询对象
     * @param mixed $value 属性名称
     * @return void
     */
    public function searchNameAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('name', 'like', '%' . $value . '%');
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
     * 搜索器：是否必选
     *
     * @param mixed $query 查询对象
     * @param mixed $value 是否必选
     * @return void
     */
    public function searchIsRequiredAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_required', $value);
        }
    }
}
