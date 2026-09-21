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
 * 为商品、购物车和订单增加插件归属及载荷快照字段。
 *
 * 本迁移只准备结构，不在开发任务中自动执行；执行前需要确认现有表和历史数据影响。
 */
class AddPluginOrderMetadata extends Migrator
{
    public function up(): void
    {
        foreach (['product_library', 'store_product'] as $tableName) {
            $table = $this->table($tableName);
            if (!$table->hasColumn('plugin_code')) {
                $table->addColumn('plugin_code', 'string', [
                    'limit' => 64,
                    'default' => '',
                    'comment' => '接管商品的插件编码，空值表示宿主普通商品',
                ]);
                $table->addIndex('plugin_code', ['name' => 'idx_' . $tableName . '_plugin_code']);
            }
            if (!$table->hasColumn('plugin_payload_json')) {
                $table->addColumn('plugin_payload_json', 'json', [
                    'null' => true,
                    'comment' => '插件商品配置快照(JSON对象，按插件命名空间隔离)',
                ]);
            }
            $table->update();
        }

        foreach (['cart', 'order'] as $tableName) {
            $table = $this->table($tableName);
            if (!$table->hasColumn('plugin_payload_json')) {
                $table->addColumn('plugin_payload_json', 'json', [
                    'null' => true,
                    'comment' => '插件下单载荷快照(JSON对象，按插件命名空间隔离)',
                ]);
            }
            $table->update();
        }
    }

    public function down(): void
    {
        foreach (['product_library', 'store_product', 'cart', 'order'] as $tableName) {
            $table = $this->table($tableName);
            if ($tableName === 'product_library' && $table->hasIndexByName('idx_product_library_plugin_code')) {
                $table->removeIndexByName('idx_product_library_plugin_code');
            }
            if ($tableName === 'store_product' && $table->hasIndexByName('idx_store_product_plugin_code')) {
                $table->removeIndexByName('idx_store_product_plugin_code');
            }
            if ($table->hasColumn('plugin_payload_json')) {
                $table->removeColumn('plugin_payload_json');
            }
            if (in_array($tableName, ['product_library', 'store_product'], true) && $table->hasColumn('plugin_code')) {
                $table->removeColumn('plugin_code');
            }
            $table->update();
        }
    }
}
