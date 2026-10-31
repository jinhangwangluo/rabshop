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
 * 商品服务模型
 *
 * 商品服务承诺（如：7天无理由/正品保障），店铺商品通过 service_ids 逗号串关联。
 * 注意：本模型与业务服务 ProductServiceService 不同名，同时使用时需 use as 别名。
 */
class ProductService extends Base
{
    use SoftDelete;

    /**
     * 表名
     * @var string
     */
    protected $name = 'product_service';

    /**
     * 自动写入时间戳
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 搜索器：商户ID
     *
     * @param mixed $query 查询对象
     * @param mixed $value 商户ID
     * @return void
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：服务名称（模糊）
     *
     * @param mixed $query 查询对象
     * @param mixed $value 服务名称
     * @return void
     */
    public function searchNameAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：状态
     *
     * @param mixed $query 查询对象
     * @param mixed $value 状态
     * @return void
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }
}
