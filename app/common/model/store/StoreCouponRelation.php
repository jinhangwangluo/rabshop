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

namespace app\common\model\store;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 优惠券关联模型（内容/专题/品类）
 */
class StoreCouponRelation extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'store_coupon_relation';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 关联类型（商品券 scope_type=1 子类型）
     */
    const RELATION_TYPE_ARTICLE = 1;            // 内容（文章）
    const RELATION_TYPE_SPECIAL = 2;            // 专题
    const RELATION_TYPE_PRODUCT = 3;            // 商品（预留）

    /**
     * 关联类型（平台品类券 scope_type=11 子类型）
     */
    const RELATION_TYPE_CATEGORY_ARTICLE = 4;   // 品类-内容
    const RELATION_TYPE_CATEGORY_SPECIAL = 5;   // 品类-专题
    const RELATION_TYPE_CATEGORY_PRODUCT = 6;   // 品类-商品（预留）

    /**
     * 搜索器：优惠券ID
     */
    public function searchCouponIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('coupon_id', $value);
        }
    }

    /**
     * 搜索器：关联类型
     */
    public function searchRelationTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('relation_type', $value);
        }
    }

    /**
     * 搜索器：关联ID
     */
    public function searchRelationIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('relation_id', $value);
        }
    }

    /**
     * 获取器：关联类型文本
     */
    public function getRelationTypeTextAttr($value, $data)
    {
        $types = [
            self::RELATION_TYPE_ARTICLE => '内容',
            self::RELATION_TYPE_SPECIAL => '专题',
            self::RELATION_TYPE_PRODUCT => '商品（预留）',
            self::RELATION_TYPE_CATEGORY_ARTICLE => '品类-内容',
            self::RELATION_TYPE_CATEGORY_SPECIAL => '品类-专题',
            self::RELATION_TYPE_CATEGORY_PRODUCT => '品类-商品（预留）',
        ];
        return $types[$data['relation_type'] ?? 1] ?? '内容';
    }

    /**
     * 关联：优惠券
     */
    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'coupon_id', 'id');
    }
}
