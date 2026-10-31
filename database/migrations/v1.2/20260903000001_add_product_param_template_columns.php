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
 * 商品参数模板补充字段。
 */
class AddProductParamTemplateColumns extends Migrator
{
    public function up(): void
    {
        $templateTable = $this->table('product_attribute');
        $templateTable->addColumn('is_required', 'boolean', [
            'limit' => 1,
            'default' => 0,
            'comment' => '模板是否必选：0-否，1-是',
        ]);
        $templateTable->addColumn('params_json', 'json', [
            'null' => true,
            'comment' => '参数项(JSON数组)',
        ]);
        $templateTable->update();

        $specConfigTable = $this->table('store_product_spec_config');
        $specConfigTable->addColumn('param_attribute_id', 'integer', [
            'limit' => 11,
            'default' => 0,
            'comment' => '商品参数模板ID',
        ]);
        $specConfigTable->addColumn('param_snapshot', 'json', [
            'null' => true,
            'comment' => '商品参数快照(JSON数组)',
        ]);
        $specConfigTable->update();
    }

    public function down(): void
    {
        $templateTable = $this->table('product_attribute');
        if ($templateTable->hasColumn('params_json')) {
            $templateTable->removeColumn('params_json');
        }
        if ($templateTable->hasColumn('is_required')) {
            $templateTable->removeColumn('is_required');
        }
        $templateTable->update();

        $specConfigTable = $this->table('store_product_spec_config');
        if ($specConfigTable->hasColumn('param_snapshot')) {
            $specConfigTable->removeColumn('param_snapshot');
        }
        if ($specConfigTable->hasColumn('param_attribute_id')) {
            $specConfigTable->removeColumn('param_attribute_id');
        }
        $specConfigTable->update();
    }
}
