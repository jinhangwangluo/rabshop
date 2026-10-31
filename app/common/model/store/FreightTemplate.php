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
 * 运费模板模型
 *
 * “基础信息 + 默认运费 + 指定地区运费”三段式配置：
 * - is_default=1 时同商户唯一（Service 事务保证互斥）
 * - is_free=1 时所有地区直接免运费，保留阶梯配置便于恢复
 * - 默认运费覆盖未配置指定地区的收货地址；指定地区规则在 freight_template_rule
 */
class FreightTemplate extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'freight_template';

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
        'default_first_quantity' => 'float',
        'default_first_amount' => 'float',
        'default_continue_quantity' => 'float',
        'default_continue_amount' => 'float',
        'free_amount' => 'float',
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
     * 搜索器：是否默认
     *
     * @param mixed $query 查询对象
     * @param mixed $value 是否默认
     * @return void
     */
    public function searchIsDefaultAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_default', $value);
        }
    }

    /**
     * 搜索器：计价方式
     *
     * @param mixed $query 查询对象
     * @param mixed $value 计价方式
     * @return void
     */
    public function searchChargeTypeAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('charge_type', $value);
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
