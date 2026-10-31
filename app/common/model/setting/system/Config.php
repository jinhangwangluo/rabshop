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
use think\model\concern\SoftDelete;

/**
 *
 * Class Config
 * @package app\common\model
 */
class Config extends Base
{
    use SoftDelete;

    /**
     * @var string
     */
    protected $name = 'config';

    protected $pk = 'id';
}
