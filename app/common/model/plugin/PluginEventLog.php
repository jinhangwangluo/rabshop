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

/**
 * Per-handler plugin event execution log.
 */
class PluginEventLog extends Base
{
    protected $name = 'plugin_event_log';

    protected $pk = 'id';

    /**
     * 日志表只有 create_time，没有 update_time；状态更新由 Service 显式写入。
     *
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id' => 'integer',
        'attempt' => 'integer',
        'duration_ms' => 'integer',
    ];
}
