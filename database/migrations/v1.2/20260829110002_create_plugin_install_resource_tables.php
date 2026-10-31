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
 * 创建插件安装资源、迁移账本、配置和商户范围表。
 */
class CreatePluginInstallResourceTables extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('plugin_file')) {
            $table = $this->table('plugin_file', ['comment' => '插件文件覆盖记录', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB']);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('plugin_version', 'string', ['limit' => 32, 'default' => '']);
            $table->addColumn('target_path', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('source_path', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('base_sha256', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('before_sha256', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('after_sha256', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('backup_ref', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending']);
            $table->addColumn('installed_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('restored_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('delete_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['plugin_code', 'plugin_version', 'target_path'], ['name' => 'uniq_plugin_file_target', 'unique' => true]);
            $table->addIndex(['target_path', 'status'], ['name' => 'idx_plugin_file_target_status']);
            $table->create();
        }

        if (!$this->hasTable('plugin_migration')) {
            $table = $this->table('plugin_migration', ['comment' => '插件迁移执行账本', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB']);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('plugin_version', 'string', ['limit' => 32, 'default' => '']);
            $table->addColumn('migration_name', 'string', ['limit' => 190, 'default' => '']);
            $table->addColumn('checksum', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('batch', 'integer', ['limit' => 11, 'default' => 0]);
            $table->addColumn('direction', 'string', ['limit' => 10, 'default' => 'up']);
            $table->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending']);
            $table->addColumn('executed_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('rolled_back_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('error_message', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['plugin_code', 'migration_name'], ['name' => 'uniq_plugin_migration', 'unique' => true]);
            $table->addIndex(['plugin_code', 'batch'], ['name' => 'idx_plugin_migration_batch']);
            $table->create();
        }

        if (!$this->hasTable('plugin_resource')) {
            $table = $this->table('plugin_resource', ['comment' => '插件注册资源快照', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB']);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('resource_type', 'string', ['limit' => 32, 'default' => '']);
            $table->addColumn('resource_key', 'string', ['limit' => 190, 'default' => '']);
            $table->addColumn('resource_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('snapshot_json', 'json', ['null' => true, 'default' => null]);
            $table->addColumn('status', 'string', ['limit' => 20, 'default' => 'active']);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('delete_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['plugin_code', 'resource_type', 'resource_key'], ['name' => 'uniq_plugin_resource', 'unique' => true]);
            $table->addIndex(['resource_type', 'resource_id'], ['name' => 'idx_plugin_resource_ref']);
            $table->create();
        }

        if (!$this->hasTable('plugin_config')) {
            $table = $this->table('plugin_config', ['comment' => '插件配置表', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB']);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('config_key', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('value_ciphertext', 'text', ['null' => true, 'default' => null]);
            $table->addColumn('encryption_key_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('value_hash', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('is_sensitive', 'boolean', ['limit' => 1, 'default' => 0]);
            $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('delete_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['plugin_code', 'config_key'], ['name' => 'uniq_plugin_config', 'unique' => true]);
            $table->create();
        }

        if (!$this->hasTable('plugin_merchant_scope')) {
            $table = $this->table('plugin_merchant_scope', ['comment' => '插件商户授权范围', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB']);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('scope_type', 'string', ['limit' => 20, 'default' => 'allow']);
            $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '0 表示全商户规则']);
            $table->addColumn('enabled', 'boolean', ['limit' => 1, 'default' => 1]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('delete_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['plugin_code', 'merchant_id'], ['name' => 'uniq_plugin_merchant_scope', 'unique' => true]);
            $table->addIndex(['merchant_id', 'enabled'], ['name' => 'idx_merchant_scope_enabled']);
            $table->create();
        }
    }

    public function down(): void
    {
        foreach (['plugin_merchant_scope', 'plugin_config', 'plugin_resource', 'plugin_migration', 'plugin_file'] as $name) {
            if ($this->hasTable($name)) {
                $this->table($name)->drop()->save();
            }
        }
    }
}
