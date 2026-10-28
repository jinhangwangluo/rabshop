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

use think\migration\Migrator;

/**
 * 商品模块基础种子数据
 *
 * 落库内容：
 * - 商品服务 3 条（平台公共：7天无理由/正品保障/极速发货）
 * - 平台规格示例 2 个（颜色：红色/蓝色；尺寸：S/M/L）
 * - "全国包邮"运费模板 1 条（平台商户 0，is_free=1，全部地区免运费）
 */
class SeedProductBaseData extends Migrator
{
    /**
     * 写入商品模块基础种子数据
     *
     * 注意：phinx 的 Table::insert() 仅排队数据，必须调用 saveData() 才会真正落库。
     *
     * @return void
     */
    public function change()
    {
        // 幂等保护：表已有数据（回滚后重跑/重复执行）则跳过，避免重复插入种子
        $existing = (int) ($this->getAdapter()->fetchRow('SELECT COUNT(*) AS c FROM ll_product_service')['c'] ?? 0);
        if ($existing > 0) {
            return;
        }

        // 商品服务（平台公共）
        $this->table('product_service')->insert([
            ['merchant_id' => 0, 'name' => '7天无理由', 'icon' => '', 'description' => '支持7天无理由退换货', 'sort' => 100, 'status' => 1, 'create_time' => date('Y-m-d H:i:s'), 'update_time' => date('Y-m-d H:i:s')],
            ['merchant_id' => 0, 'name' => '正品保障', 'icon' => '', 'description' => '平台正品保障，假一赔十', 'sort' => 90, 'status' => 1, 'create_time' => date('Y-m-d H:i:s'), 'update_time' => date('Y-m-d H:i:s')],
            ['merchant_id' => 0, 'name' => '极速发货', 'icon' => '', 'description' => '付款后24小时内发货', 'sort' => 80, 'status' => 1, 'create_time' => date('Y-m-d H:i:s'), 'update_time' => date('Y-m-d H:i:s')],
        ])->saveData();

        // 平台规格示例：颜色（红色/蓝色）
        $this->table('product_spec')->insert([
            [
                'merchant_id' => 0,
                'name' => '颜色',
                'spec_template' => json_encode([[
                    'id' => 0,
                    'name' => '颜色',
                    'sort' => 100,
                    'status' => 1,
                    'values' => [
                        ['id' => 0, 'value' => '红色', 'sort' => 100, 'status' => 1],
                        ['id' => 0, 'value' => '蓝色', 'sort' => 99, 'status' => 1],
                    ],
                ]], JSON_UNESCAPED_UNICODE),
                'sort' => 100,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ],
        ])->saveData();

        // 平台规格示例：尺寸（S/M/L）
        $this->table('product_spec')->insert([
            [
                'merchant_id' => 0,
                'name' => '尺寸',
                'spec_template' => json_encode([[
                    'id' => 0,
                    'name' => '尺寸',
                    'sort' => 100,
                    'status' => 1,
                    'values' => [
                        ['id' => 0, 'value' => 'S', 'sort' => 100, 'status' => 1],
                        ['id' => 0, 'value' => 'M', 'sort' => 99, 'status' => 1],
                        ['id' => 0, 'value' => 'L', 'sort' => 98, 'status' => 1],
                    ],
                ]], JSON_UNESCAPED_UNICODE),
                'sort' => 90,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ],
        ])->saveData();

        // "全国包邮"运费模板（平台公共，is_free=1 全部地区免运费）
        $this->table('freight_template')->insert([
            [
                'merchant_id' => 0,
                'name' => '全国包邮',
                'delivery_time' => '24小时内',
                'is_default' => 1,
                'is_free' => 1,
                'charge_type' => 1,
                'default_first_quantity' => 1,
                'default_first_amount' => 0,
                'default_continue_quantity' => 0,
                'default_continue_amount' => 0,
                'free_amount' => 0,
                'sort' => 100,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ],
        ])->saveData();
    }
}
