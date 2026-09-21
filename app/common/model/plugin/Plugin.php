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
 * 本地插件安装状态模型。
 */
class Plugin extends Base
{
    use SoftDelete;

    protected $name = 'plugin';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $json = ['manifest_json'];

    protected $jsonAssoc = true;

    protected $type = [
        'enabled' => 'integer',
        'manifest_json' => 'array',
    ];

    public function searchCodeAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('code', (string) $value);
        }
    }

    public function searchLifecycleStatusAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('lifecycle_status', (string) $value);
        }
    }

    public function searchEnabledAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('enabled', (int) $value);
        }
    }
}
