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
 * 官网授权租约的本地镜像模型。
 */
class PluginLicenseLease extends Base
{
    protected $name = 'plugin_license_lease';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id' => 'integer',
    ];

    public function searchPluginCodeAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('plugin_code', (string) $value);
        }
    }

    public function searchInstanceIdAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('instance_id', (string) $value);
        }
    }

    public function searchStatusAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('status', (string) $value);
        }
    }
}
