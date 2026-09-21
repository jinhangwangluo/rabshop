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

/**
 * Class AdminRole
 * @package app\common\model
 */
class AdminRole extends Base
{
    /**
     * 平台类型常量：平台端
     */
    const PLATFORM_TYPE_PLATFORM = 1;

    /**
     * 平台类型常量：商户端
     */
    const PLATFORM_TYPE_MERCHANT = 2;

    /**
     * 表名
     * @var string
     */
    protected $name = 'admin_role';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * @param $value
     * @return false|string
     */
    public function setRuleAttr($value)
    {
        return json_encode($value);
    }

    /**
     * @param $value
     * @return array|mixed
     */
    public function getRuleAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * @param $value
     * @return false|string
     */
    public function setStoreManageIdsAttr($value)
    {
        return json_encode($value);
    }

    /**
     * @param $value
     * @return array|mixed
     */
    public function getStoreManageIdsAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * 获取权限
     * @param array $id
     * @return array
     */
    public static function getRules(array $id)
    {
        $rulesIds = self::whereIn('id', $id)->column('rule', 'id');
        $data = [];
        foreach ($rulesIds as $rule) {
            $rule = $rule ? json_decode($rule, true) : [];
            $data = array_merge($data, $rule);
        }
        return array_merge(array_unique($data));
    }

    /**
     * 搜索器：平台类型
     * @param $query
     * @param $value
     */
    public function searchPlatformTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('platform_type', $value);
        }
    }

    /**
     * 搜索器：商户ID
     * @param $query
     * @param $value
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * @param array $where
     * @return AdminRole
     */
    public function search(array $where)
    {
        return $this->when(isset($where['status']) && '' !== $where['status'], function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['role_name']) && '' !== $where['role_name'], function ($query) use ($where) {
            $query->where('role_name', 'like', '%' . $where['role_name'] . '%');
        })->when(isset($where['pid']) && '' !== $where['pid'], function ($query) use ($where) {
            $query->where('pid', $where['pid']);
        })->when(isset($where['platform_type']) && '' !== $where['platform_type'], function ($query) use ($where) {
            $query->where('platform_type', $where['platform_type']);
        })->when(isset($where['merchant_id']) && '' !== $where['merchant_id'], function ($query) use ($where) {
            $query->where('merchant_id', $where['merchant_id']);
        });
    }

    /**
     * 关联商户
     * @return \think\model\relation\BelongsTo
     */
    public function merchant()
    {
        return $this->belongsTo(\app\common\model\merchant\Merchant::class, 'merchant_id', 'id');
    }

    /**
     * 是否为商户角色
     * @return bool
     */
    public function isMerchantRole(): bool
    {
        return $this->platform_type === self::PLATFORM_TYPE_MERCHANT;
    }

    /**
     * 是否为平台角色
     * @return bool
     */
    public function isPlatformRole(): bool
    {
        return $this->platform_type === self::PLATFORM_TYPE_PLATFORM;
    }
}
