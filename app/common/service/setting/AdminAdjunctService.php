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

namespace app\common\service\setting;


use app\common\model\product\AdminAdjunct;
use app\common\service\BaseService;

/**
 * Class AdminAdjunctService
 * @author 橙猫
 * @email 17349395805@163.com
 * @date 2022/11/15
 * @package app\common\service\setting
 */
class AdminAdjunctService extends BaseService
{

    protected function setModel(): string
    {
        return AdminAdjunct::class;
    }
}
