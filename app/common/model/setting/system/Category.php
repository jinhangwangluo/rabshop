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

namespace app\common\model\setting\system;


use app\common\model\Base;

/**
 * Class Category
 * @author 橙猫
 * @email 17349395805@163.com
 * @date 2022/11/12
 * @package app\common\model\setting
 */
class Category extends Base
{

    /**
     * @var string
     */
    protected $name = 'category';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2023/10/18
     * @param $value
     * @return mixed|null
     */
    public function getOtherAttr($value)
    {
        return $value ? json_decode($value) : null;
    }

}
