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

namespace app\common\enum;

/**
 * 操作类型枚举
 */
enum ActionEnum
{
    // 创建
    const ADD_TYPE = 'create';
    // 修改
    const UPDATE_TYPE = 'update';
    // 删除
    const DELETE_TYPE = 'delete';
    // 批量添加
    const BATCH_ADD_TYPE = 'batch_add';
    // 批量添加
    const BATCH_ADD_NOT_STORE_TYPE = 'batch_add_not_store';
    // 批量下线
    const BATCH_OFFLINE_TYPE = 'batch_offline';
    // 批量上线
    const BATCH_ONLINE_TYPE = 'batch_online';
    // 批量添加分类
    const BATCH_CATE_ADD_TYPE = 'batch_cate_add';
    // 批量删除
    const BATCH_DELL_TYPE = 'batch_delete';
    // 获取商品
    const GET_PRODUCT_LIST_TYPE = 'get_product_list';
    // 获取分类
    const CATEGORY_TWO_TYPE = 'category_two';
    //一级分类
    const CATEGORY_ONE_TYPE = 'category_one';
}
