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
 * 商品SKU模型 ★ SKU 五项规则核心
 *
 * - uniqid = 来源库商品 product_code，不可修改、软删不释放
 * - 软删（delete_time）即删除 SKU
 * - status=0 隐藏（前台规格弹窗不展示）
 * - sort 排序，查询统一 order('sort desc, id asc')
 * - spec_value_ids 为规格值ID升序逗号串，前台规格数据源
 */
class StoreProductSku extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'store_product_sku';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     * @var array
     */
    protected $type = [
        'price' => 'float',
        'ot_price' => 'float',
        'store_price' => 'float',
        'weight' => 'float',
        'stock_version' => 'integer',
    ];

    /**
     * 搜索器：店铺商品
     *
     * @param mixed $query 查询对象
     * @param mixed $value 店铺商品ID
     * @return void 无返回值。
     */
    public function searchStoreProductIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('store_product_id', $value);
        }
    }

    /**
     * 搜索器：来源库商品
     *
     * @param mixed $query 查询对象
     * @param mixed $value 库商品ID
     * @return void 无返回值。
     */
    public function searchLibraryProductIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('library_product_id', $value);
        }
    }

    /**
     * 搜索器：SKU唯一值
     *
     * @param mixed $query 查询对象
     * @param mixed $value SKU唯一值
     * @return void 无返回值。
     */
    public function searchUniqidAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('uniqid', $value);
        }
    }

    /**
     * 搜索器：状态
     *
     * @param mixed $query 查询对象
     * @param mixed $value 状态
     * @return void 无返回值。
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }
}
