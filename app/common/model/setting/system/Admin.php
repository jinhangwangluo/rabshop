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
use Rabbit\util\Jwt;
use think\model\concern\SoftDelete;

/**
 * 管理员
 * Class Admin
 * @package app\common\model
 */
class Admin extends Base
{
    use SoftDelete;

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
    protected $name = 'admin';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 时间戳字段
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 字段类型转换。
     *
     * @var array
     */
    protected $type = [
        'message_notify_enabled' => 'integer',
        'message_notify_email' => 'string',
    ];

    /**
     * 设置Token
     * @param int $id
     * @param string $type 类型：admin-平台管理员，merchant-商户管理员
     * @return string
     */
    public function setToken(int $id, string $type = 'admin'): string
    {
        return Jwt::create($id, 0, $type);
    }

    public function setObjIdsAttr($value)
    {
        return is_array($value) ? implode(',', array_unique($value)) : '';
    }

    public function getObjIdsAttr($value)
    {
        return $value ? array_map('intval', explode(',', $value)) : [];
    }

    public function getRolesAttr($value)
    {
        return $value ? array_map('intval', json_decode($value, true)) : [];
    }

    public function setPathAttr($value): string
    {
        return json_encode($value);
    }

    public function getPathAttr($value, $data)
    {
        return json_decode($value, true);
    }

    public function setRolesAttr($value)
    {
        return json_encode($value);
    }

    /**
     * @param $value
     * @return string
     */
    public function setProductIdsAttr($value)
    {
        return implode(',', array_unique($value));
    }

    /**
     * @param $value
     * @return array|string[]
     */
    public function getProductIdsAttr($value)
    {
        return $value ? explode(',', $value) : [];
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
     * @return Admin
     */
    public function search(array $where)
    {
        return $this->when(isset($where['status']) && '' !== $where['status'], function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['account']) && '' !== $where['account'], function ($query) use ($where) {
            $query->where('account', 'like', '%' . $where['account'] . '%');
        })->when(isset($where['pid']) && '' !== $where['pid'], function ($query) use ($where) {
            $query->where('pid|id', $where['pid']);
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
     * 是否为商户管理员
     * @return bool
     */
    public function isMerchantAdmin(): bool
    {
        return $this->platform_type === self::PLATFORM_TYPE_MERCHANT;
    }

    /**
     * 是否为平台管理员
     * @return bool
     */
    public function isPlatformAdmin(): bool
    {
        return $this->platform_type === self::PLATFORM_TYPE_PLATFORM;
    }
}
