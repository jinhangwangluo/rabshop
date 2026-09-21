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
 * 插件配置模型，敏感值只保存密文。
 */
class PluginConfig extends Base
{
    use SoftDelete;

    protected $name = 'plugin_config';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id' => 'integer',
        'is_sensitive' => 'integer',
        'status' => 'integer',
    ];
}
