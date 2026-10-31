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

declare(strict_types=1);

namespace app\common\model\about;

use app\common\model\Base;

/**
 * 关于我们模型。
 */
class About extends Base
{
    /**
     * 数据表名。
     */
    protected $name = 'about_us';

    /**
     * 主键。
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳。
     */
    protected $autoWriteTimestamp = 'datetime';
}
