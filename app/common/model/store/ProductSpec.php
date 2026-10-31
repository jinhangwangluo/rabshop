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
 * 商品规格模板模型。
 *
 * name 保存模板标题，spec_template 保存完整的规格维度和值。
 */
class ProductSpec extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'product_spec';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * JSON 字段。
     *
     * @var array
     */
    protected $json = ['spec_template'];

    /**
     * JSON 字段返回关联数组。
     *
     * @var bool
     */
    protected $jsonAssoc = true;

    /**
     * 字段类型。
     *
     * @var array
     */
    protected $type = [
        'merchant_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'spec_template' => 'json',
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
     * 搜索器：模板名称（模糊）
     *
     * @param mixed $query 查询对象
     * @param mixed $value 模板名称
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
}
