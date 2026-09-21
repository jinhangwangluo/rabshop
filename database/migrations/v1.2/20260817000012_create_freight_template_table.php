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
 * 运费模板表（freight_template）
 *
 * “基础信息 + 默认运费 + 指定地区运费”三段式配置：
 * - delivery_time 保存发货时效选项值（如 24小时内/48小时内），选项由 FreightEnum::deliveryTimeOptions() 提供
 * - charge_type：1-按件数，2-按重量（本期不实现体积计价；按重量统一以千克计费）
 * - is_default=1 时同一商户只能有一个默认模板（Service 事务内保证互斥）
 * - is_free=1 时所有地区直接免运费，仍保留默认运费与区域规则便于取消包邮后恢复
 * - 默认运费（default_*）覆盖未配置指定地区的收货地址；指定地区规则存于 freight_template_rule
 * - merchant_id=0 为平台模板，>0 为商户模板
 */
class CreateFreightTemplateTable extends Migrator
{
    /**
     * 创建运费模板表
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('freight_template', ['comment' => '运费模板表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '所属商户ID，平台端为0']);
        $table->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '模板名称']);
        $table->addColumn('delivery_time', 'string', ['limit' => 50, 'default' => '', 'comment' => '发货时间选项值（如 24小时内/48小时内）']);
        $table->addColumn('is_default', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否默认模板：0-否，1-是（同商户唯一，Service 事务保证）']);
        $table->addColumn('is_free', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否包邮：0-否，1-是（为1时所有地区免运费）']);
        $table->addColumn('charge_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '计价方式：1-按件数，2-按重量（本期无体积计价）']);
        $table->addColumn('default_first_quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 1, 'comment' => '默认首件/首重数量（按件数=件，按重量=千克）']);
        $table->addColumn('default_first_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '默认首件/首重运费（元）']);
        $table->addColumn('default_continue_quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '默认续件/续重数量（0表示无续重只收首费）']);
        $table->addColumn('default_continue_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '默认续件/续重运费（元）']);
        $table->addColumn('free_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '默认运费满额免运费门槛（元），0=不启用']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（越大越靠前）']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $table->addIndex('is_default', ['name' => 'idx_is_default']);
        $table->addIndex('charge_type', ['name' => 'idx_charge_type']);
        $table->addIndex('status', ['name' => 'idx_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->create();
    }
}
