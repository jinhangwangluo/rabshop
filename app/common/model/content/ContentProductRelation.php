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

namespace app\common\model\content;

use app\common\model\Base;
use app\common\model\store\StoreProduct;

/**
 * 内容商品关联模型（中间表：关联文章/专题与店铺商品）
 */
class ContentProductRelation extends Base
{
    /**
     * 表名
     */
    protected $name = 'content_product_relation';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     */
    protected $type = [
        'relation_id' => 'integer',
        'product_id'  => 'integer',
        'sort'        => 'integer',
    ];

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
     * 搜索器：关联业务ID
     */
    public function searchRelationIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('relation_id', $value);
        }
    }

    /**
     * 关联：店铺商品
     */
    public function product()
    {
        return $this->belongsTo(StoreProduct::class, 'product_id', 'id');
    }
}
