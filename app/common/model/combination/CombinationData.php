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

namespace app\common\model\combination;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 组合数据模型.
 *
 * 每条记录关联一个组合数据配置，并把动态表单值保存到 data_json。
 */
class CombinationData extends Base
{
    use SoftDelete;

    /**
     * 数据表名.
     *
     * @var string
     */
    protected $name = 'combination_data';

    /**
     * 主键.
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * 写入动态表单数据.
     *
     * @param mixed $value JSON 字符串或数组
     * @return string
     */
    public function setDataJsonAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 读取动态表单数据.
     *
     * @param mixed $value 数据库原始值
     * @return array
     */
    public function getDataJsonAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }
}
