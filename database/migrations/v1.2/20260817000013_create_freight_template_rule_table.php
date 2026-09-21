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
 * 运费模板规则表（freight_template_rule）
 *
 * 指定地区运费规则：命中 city_ids 中任一区域的收货地址，按该行阶梯计费；
 * 未命中任何规则的地区回落默认运费（freight_template.default_*）。
 * city_ids 保存 city_area.id 逗号串（支持省/市/区县/末级区域），
 * 展示名称通过 city_area 查询生成，不保存易失效的地区名称。
 * 同一模板内地区不可重复/交叉覆盖，由 Service 校验，避免一个地址命中多条规则。
 */
class CreateFreightTemplateRuleTable extends Migrator
{
    /**
     * 创建运费模板规则表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('freight_template_rule', ['comment' => '运费模板规则表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('template_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '运费模板ID']);
        $table->addColumn('rule_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '规则类型：1-指定地区']);
        $table->addColumn('city_ids', 'string', ['limit' => 1000, 'default' => '', 'comment' => '指定地区ID逗号串（city_area.id，支持省/市/区县/末级区域）']);
        $table->addColumn('first_quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 1, 'comment' => '指定地区首件/首重数量（单位随模板计价方式）']);
        $table->addColumn('first_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '指定地区首件/首重运费（元）']);
        $table->addColumn('continue_quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '指定地区续件/续重数量（0=仅收首费）']);
        $table->addColumn('continue_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '指定地区续件/续重运费（元）']);
        $table->addColumn('free_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '指定地区满额免运费门槛（元），0=不启用']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '规则排序（越大越靠前）']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('template_id', ['name' => 'idx_template_id']);
        $table->addIndex('rule_type', ['name' => 'idx_rule_type']);
        $table->addIndex('status', ['name' => 'idx_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->create();
    }
}
