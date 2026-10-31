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

namespace app\common\model\merchant;

use app\common\model\Base;

/**
 * Class MerchantApply
 * @package app\common\model\merchant
 */
class MerchantApply extends Base
{
    /**
     * @var string
     */
    protected $name = 'merchant_apply';

    /**
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAIL = 2;

    /**
     * 搜索器：商户名称
     * @param $query
     * @param $value
     */
    public function searchMerNameAttr($query, $value)
    {
        $query->where('mer_name', 'like', '%' . $value . '%');
    }

    /**
     * 搜索器：状态
     * @param $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：账号
     * @param $query
     * @param $value
     */
    public function searchAccountAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('account', $value);
        }
    }

    /**
     * 获取器：状态文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '待审核', 1 => '审核通过', 2 => '审核拒绝'];
        return $status[$data['status'] ?? 0] ?? '未知';
    }
}
