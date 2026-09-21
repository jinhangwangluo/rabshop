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
 * Plugin event Outbox record.
 */
class PluginEventOutbox extends Base
{
    protected $name = 'plugin_event_outbox';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $json = ['actor_json', 'payload_json', 'subscriber_snapshot_json'];

    protected $jsonAssoc = true;

    protected $type = [
        'id' => 'integer',
        'merchant_id' => 'integer',
        'attempts' => 'integer',
        'actor_json' => 'array',
        'payload_json' => 'array',
        'subscriber_snapshot_json' => 'array',
    ];
}
