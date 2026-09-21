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
 * 店铺商品验证器
 *
 * 校验店铺商品名称必填、三价与库存数值、规格类型、包邮方式、运费模板关联等。
 */
class StoreProductValidate extends Validate
{
    /**
     * 校验规则
     * @var array
     */
    protected $rule = [
        'name' => 'require|max:100',
        'library_product_ids' => 'array',
        'library_skus' => 'array',
        'removed_library_product_ids' => 'array',
        'custom_spec_type' => 'integer|in:0,1',
        'custom_specs' => 'array',
        'custom_skus' => 'array',
        'spec_mode' => 'in:library,custom',
        'spec_type' => 'integer|in:0,1',
        'category_id' => 'require|integer|gt:0',
        'brand_id' => 'require|integer|gt:0',
        'tag_ids' => 'array',
        'group_id' => 'integer|egt:0',
        'goods_type' => 'integer|in:1,2,3',
        'is_free_shipping' => 'integer|in:0,1',
        'freight_template_id' => 'integer|egt:0',
        'virtual_sales' => 'integer|egt:0',
        'virtual_view_count' => 'integer|egt:0',
        'status' => 'integer|in:0,1',
        'audit_status' => 'integer|in:0,1,2',
        'sort' => 'integer|egt:0',
    ];

    /**
     * 错误提示
     * @var array
     */
    protected $message = [
        'name.require' => '请输入商品名称',
        'name.max' => '商品名称最多100个字符',
        'library_product_ids.array' => '商品库商品参数格式不正确',
        'custom_specs.array' => '自定义规格参数格式不正确',
        'custom_skus.array' => '自定义SKU参数格式不正确',
        'spec_mode.in' => '规格来源模式不正确',
        'custom_spec_type.in' => '自定义规格类型不正确',
        'spec_type.in' => '规格类型不正确',
        'category_id.require' => '请选择平台分类',
        'category_id.integer' => '平台分类参数格式不正确',
        'category_id.gt' => '请选择平台分类',
        'brand_id.require' => '请选择商品品牌',
        'brand_id.integer' => '商品品牌参数格式不正确',
        'brand_id.gt' => '请选择商品品牌',
        'tag_ids.array' => '商品标签参数格式不正确',
        'goods_type.in' => '商品类型不正确',
        'is_free_shipping.in' => '包邮方式不正确',
        'status.in' => '状态不正确',
        'audit_status.in' => '审核状态不正确',
    ];

    /**
     * 场景：编辑（部分字段更新时 name 可不传）
     *
     * @var array
     */
    protected $scene = [
        'update' => [
            'library_product_ids' => 'array',
            'library_skus' => 'array',
            'removed_library_product_ids' => 'array',
            'custom_spec_type' => 'integer|in:0,1',
            'custom_specs' => 'array',
            'custom_skus' => 'array',
            'spec_mode' => 'in:library,custom',
            'spec_type' => 'integer|in:0,1',
            'category_id' => 'integer|egt:0',
            'brand_id' => 'integer|egt:0',
            'tag_ids' => 'array',
            'group_id' => 'integer|egt:0',
            'goods_type' => 'integer|in:1,2,3',
            'is_free_shipping' => 'integer|in:0,1',
            'freight_template_id' => 'integer|egt:0',
            'status' => 'integer|in:0,1',
            'sort' => 'integer|egt:0',
        ],
        // 场景：SKU 保存
        'sku' => [
            'uniqid' => 'require|max:64',
            'price' => 'float|egt:0',
            'ot_price' => 'float|egt:0',
            'store_price' => 'float|egt:0',
            'stock' => 'integer|egt:0',
            'weight' => 'float|egt:0',
            'status' => 'integer|in:0,1',
            'sort' => 'integer|egt:0',
            'is_default' => 'integer|in:0,1',
        ],
    ];
}
