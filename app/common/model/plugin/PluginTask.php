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
 * 插件下载、安装、升级和卸载任务模型。
 */
class PluginTask extends Base
{
    protected $name = 'plugin_task';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $json = ['payload_json', 'result_json'];

    protected $jsonAssoc = true;

    protected $type = [
        'id' => 'integer',
        'release_id' => 'integer',
        'progress' => 'integer',
        'actor_id' => 'integer',
        'payload_json' => 'array',
        'result_json' => 'array',
    ];

    public function searchTaskNoAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('task_no', (string) $value);
        }
    }

    public function searchPluginCodeAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('plugin_code', (string) $value);
        }
    }

    public function searchTaskTypeAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('task_type', (string) $value);
        }
    }

    public function searchStatusAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('status', (string) $value);
        }
    }
}
