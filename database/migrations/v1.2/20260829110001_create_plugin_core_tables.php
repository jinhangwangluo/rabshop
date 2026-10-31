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
 * 创建本地插件核心状态、发布快照、任务和授权租约表。
 *
 * 本迁移只定义 v1.2 本地事实来源，不创建官网市场数据。
 */
class CreatePluginCoreTables extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('plugin')) {
            $table = $this->table('plugin', [
                'comment' => '本地插件状态表',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('code', 'string', ['limit' => 64, 'default' => '', 'comment' => '插件唯一编码']);
            $table->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '插件名称']);
            $table->addColumn('version', 'string', ['limit' => 32, 'default' => '', 'comment' => '当前安装版本']);
            $table->addColumn('publisher', 'string', ['limit' => 100, 'default' => '', 'comment' => '发布者快照']);
            $table->addColumn('install_source', 'string', ['limit' => 20, 'default' => 'local', 'comment' => '安装来源：local/market']);
            $table->addColumn('lifecycle_status', 'string', ['limit' => 32, 'default' => 'uninstalled', 'comment' => '生命周期状态']);
            $table->addColumn('enabled', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否启用']);
            $table->addColumn('license_status', 'string', ['limit' => 20, 'default' => 'unknown', 'comment' => '授权镜像状态']);
            $table->addColumn('manifest_json', 'json', ['null' => true, 'default' => null, 'comment' => '已安装清单快照']);
            $table->addColumn('last_error_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '最近脱敏错误码']);
            $table->addColumn('last_error_message', 'string', ['limit' => 500, 'default' => '', 'comment' => '最近脱敏错误信息']);
            $table->addColumn('installed_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('enabled_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('disabled_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('delete_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex('code', ['name' => 'uniq_plugin_code', 'unique' => true]);
            $table->addIndex('lifecycle_status', ['name' => 'idx_plugin_lifecycle']);
            $table->addIndex(['enabled', 'lifecycle_status'], ['name' => 'idx_plugin_enabled_status']);
            $table->create();
        }

        if (!$this->hasTable('plugin_release')) {
            $table = $this->table('plugin_release', [
                'comment' => '插件集合发布快照表',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('release_no', 'string', ['limit' => 64, 'default' => '', 'comment' => '本地发布编号']);
            $table->addColumn('core_version', 'string', ['limit' => 32, 'default' => '', 'comment' => '核心版本']);
            $table->addColumn('plugin_set_hash', 'char', ['limit' => 64, 'default' => '', 'comment' => '插件集合哈希']);
            $table->addColumn('plugin_versions_json', 'json', ['null' => false, 'comment' => '插件版本快照']);
            $table->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending', 'comment' => '发布状态']);
            $table->addColumn('backend_artifact_ref', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('admin_artifact_ref', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('merchant_artifact_ref', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('h5_artifact_ref', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('mp_artifact_ref', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('mp_upload_mode', 'string', ['limit' => 20, 'default' => '', 'comment' => 'hosted_cli/self_upload']);
            $table->addColumn('mp_upload_task_ref', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('rollback_from_release_id', 'integer', ['limit' => 11, 'default' => 0]);
            $table->addColumn('published_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('rolled_back_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex('release_no', ['name' => 'uniq_plugin_release_no', 'unique' => true]);
            $table->addIndex('plugin_set_hash', ['name' => 'idx_plugin_set_hash']);
            $table->addIndex('status', ['name' => 'idx_release_status']);
            $table->create();
        }

        if (!$this->hasTable('plugin_task')) {
            $table = $this->table('plugin_task', [
                'id' => false,
                'primary_key' => ['id'],
                'comment' => '插件本地任务表',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true, 'comment' => '主键']);
            $table->addColumn('task_no', 'string', ['limit' => 64, 'default' => '', 'comment' => '本地任务号']);
            $table->addColumn('task_type', 'string', ['limit' => 32, 'default' => '', 'comment' => '任务类型']);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('release_id', 'integer', ['limit' => 11, 'default' => 0]);
            $table->addColumn('from_version', 'string', ['limit' => 32, 'default' => '']);
            $table->addColumn('to_version', 'string', ['limit' => 32, 'default' => '']);
            $table->addColumn('remote_task_ref', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending']);
            $table->addColumn('progress', 'integer', ['limit' => 3, 'default' => 0]);
            $table->addColumn('idempotency_key', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('artifact_sha256', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('data_policy', 'string', ['limit' => 20, 'default' => '']);
            $table->addColumn('actor_type', 'string', ['limit' => 20, 'default' => 'system']);
            $table->addColumn('actor_id', 'integer', ['limit' => 11, 'default' => 0]);
            $table->addColumn('payload_json', 'json', ['null' => true, 'default' => null]);
            $table->addColumn('result_json', 'json', ['null' => true, 'default' => null]);
            $table->addColumn('error_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('error_message', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('started_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('finished_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex('task_no', ['name' => 'uniq_plugin_task_no', 'unique' => true]);
            $table->addIndex(['plugin_code', 'task_type'], ['name' => 'idx_task_plugin_type']);
            $table->addIndex(['task_type', 'status'], ['name' => 'idx_task_type_status']);
            $table->addIndex('remote_task_ref', ['name' => 'idx_task_remote_ref']);
            $table->addIndex('idempotency_key', ['name' => 'uniq_task_idempotency', 'unique' => true]);
            $table->create();
        }

        if (!$this->hasTable('plugin_license_lease')) {
            $table = $this->table('plugin_license_lease', [
                'comment' => '插件授权租约镜像表',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('instance_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('license_no', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('status', 'string', ['limit' => 20, 'default' => 'unknown']);
            $table->addColumn('lease_payload', 'text', ['null' => true, 'default' => null]);
            $table->addColumn('signature', 'text', ['null' => true, 'default' => null]);
            $table->addColumn('signing_key_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('issued_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('expires_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('grace_ends_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('last_sync_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('last_sync_error', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['plugin_code', 'instance_id'], ['name' => 'uniq_plugin_instance', 'unique' => true]);
            $table->addIndex('license_no', ['name' => 'uniq_plugin_license_no', 'unique' => true]);
            $table->addIndex('expires_at', ['name' => 'idx_license_expires']);
            $table->create();
        }
    }

    public function down(): void
    {
        foreach (['plugin_license_lease', 'plugin_task', 'plugin_release', 'plugin'] as $name) {
            if ($this->hasTable($name)) {
                $this->table($name)->drop()->save();
            }
        }
    }
}
