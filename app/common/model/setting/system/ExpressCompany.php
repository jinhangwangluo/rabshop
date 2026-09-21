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
 * 快递公司模型
 */
class ExpressCompany extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'express_company';

    /**
     * 搜索器：名称
     */
    public function searchNameAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('name', 'like', '%' . trim((string)$value) . '%');
        }
    }

    /**
     * 搜索器：编码
     */
    public function searchCodeAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('code', 'like', '%' . trim((string)$value) . '%');
        }
    }

    /**
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }
}
