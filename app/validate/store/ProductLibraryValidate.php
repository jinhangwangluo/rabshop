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

namespace app\validate\store;

use think\Validate;

/**
 * 库商品验证器
 *
 * 校验库商品名称必填、库编码只读、三价与库存数值、规格值绑定等。
 */
class ProductLibraryValidate extends Validate
{
    /**
     * 校验规则
     * @var array
     */
    protected $rule = [
        'name' => 'require|max:100',
        'product_code' => 'max:64',
        'category_id' => 'integer|egt:0',
        'brand_id' => 'integer|egt:0',
        'group_id' => 'integer|egt:0',
        'spec_value_id' => 'integer|egt:0',
        'price' => 'float|egt:0',
        'ot_price' => 'float|egt:0',
        'store_price' => 'float|egt:0',
        'stock' => 'integer|egt:0',
        'goods_type' => 'integer|in:1,2,3',
        'is_share' => 'integer|in:0,1',
        'status' => 'integer|in:0,1',
        'audit_status' => 'integer|in:0,1,2',
        'sort' => 'integer|egt:0',
    ];

    /**
     * 错误提示
     * @var array
     */
    protected $message = [
        'name.require' => '请输入库商品名称',
        'name.max' => '库商品名称最多100个字符',
        'goods_type.in' => '商品类型不正确',
        'is_share.in' => '共享状态不正确',
        'status.in' => '状态不正确',
        'audit_status.in' => '审核状态不正确',
    ];
}
