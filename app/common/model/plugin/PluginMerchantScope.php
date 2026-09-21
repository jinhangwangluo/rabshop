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

namespace app\common\model\plugin;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 插件商户授权范围模型。
 */
class PluginMerchantScope extends Base
{
    use SoftDelete;

    protected $name = 'plugin_merchant_scope';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id' => 'integer',
        'merchant_id' => 'integer',
        'enabled' => 'integer',
    ];
}
