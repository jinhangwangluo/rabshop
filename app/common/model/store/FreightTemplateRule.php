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
 * 运费模板规则模型
 *
 * 指定地区运费规则（rule_type=1）：命中 city_ids 中任一区域按该行阶梯计费，
 * 未命中任何规则回落默认运费。city_ids 为 city_area.id 逗号串（省/市/区县/末级区域），
 * 同模板内地区不可重复/交叉覆盖（Service 校验）。
 */
class FreightTemplateRule extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'freight_template_rule';

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
        'first_quantity' => 'float',
        'first_amount' => 'float',
        'continue_quantity' => 'float',
        'continue_amount' => 'float',
        'free_amount' => 'float',
    ];

    /**
     * 搜索器：模板ID
     *
     * @param mixed $query 查询对象
     * @param mixed $value 模板ID
     * @return void
     */
    public function searchTemplateIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('template_id', $value);
        }
    }

    /**
     * 搜索器：规则类型
     *
     * @param mixed $query 查询对象
     * @param mixed $value 规则类型
     * @return void
     */
    public function searchRuleTypeAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('rule_type', $value);
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
