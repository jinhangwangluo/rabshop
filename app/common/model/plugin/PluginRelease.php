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
 * 本地插件集合发布快照模型。
 */
class PluginRelease extends Base
{
    protected $name = 'plugin_release';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $json = ['plugin_versions_json'];

    protected $jsonAssoc = true;

    protected $type = [
        'plugin_versions_json' => 'array',
        'rollback_from_release_id' => 'integer',
    ];

    public function searchReleaseNoAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('release_no', (string) $value);
        }
    }

    public function searchStatusAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('status', (string) $value);
        }
    }
}
