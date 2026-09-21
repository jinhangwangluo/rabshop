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
 * 创建插件事件 Outbox 和处理日志表。
 */
class CreatePluginEventTables extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('plugin_event_outbox')) {
            $table = $this->table('plugin_event_outbox', [
                'id' => false,
                'primary_key' => ['id'],
                'comment' => '插件事件 Outbox',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true, 'comment' => '主键']);
            $table->addColumn('event_id', 'string', ['limit' => 36, 'default' => '']);
            $table->addColumn('name', 'string', ['limit' => 100, 'default' => '']);
            $table->addColumn('event_version', 'string', ['limit' => 20, 'default' => '1.0']);
            $table->addColumn('trace_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('actor_json', 'json', ['null' => true, 'default' => null]);
            $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0]);
            $table->addColumn('subject_type', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('subject_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('payload_json', 'json', ['null' => false]);
            $table->addColumn('subscriber_snapshot_json', 'json', ['null' => true, 'default' => null, 'comment' => '发布时订阅处理器快照']);
            $table->addColumn('idempotency_key', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending']);
            $table->addColumn('attempts', 'integer', ['limit' => 11, 'default' => 0]);
            $table->addColumn('available_at', 'timestamp', ['null' => false]);
            $table->addColumn('locked_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('locked_by', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('dispatched_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('last_error', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex('event_id', ['name' => 'uniq_plugin_event_id', 'unique' => true]);
            $table->addIndex('idempotency_key', ['name' => 'uniq_plugin_event_idempotency', 'unique' => true]);
            $table->addIndex(['status', 'available_at'], ['name' => 'idx_plugin_event_claim']);
            $table->addIndex('locked_at', ['name' => 'idx_plugin_event_locked']);
            $table->create();
        }

        if (!$this->hasTable('plugin_event_log')) {
            $table = $this->table('plugin_event_log', [
                'id' => false,
                'primary_key' => ['id'],
                'comment' => '插件事件处理日志',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true, 'comment' => '主键']);
            $table->addColumn('event_id', 'string', ['limit' => 36, 'default' => '']);
            $table->addColumn('delivery_key', 'string', ['limit' => 190, 'default' => '']);
            $table->addColumn('plugin_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('handler', 'string', ['limit' => 190, 'default' => '']);
            $table->addColumn('attempt', 'integer', ['limit' => 11, 'default' => 1]);
            $table->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending']);
            $table->addColumn('duration_ms', 'integer', ['limit' => 11, 'default' => 0]);
            $table->addColumn('idempotency_key', 'string', ['limit' => 128, 'default' => '']);
            $table->addColumn('payload_hash', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('error_code', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('error_message', 'string', ['limit' => 500, 'default' => '']);
            $table->addColumn('started_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('finished_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['delivery_key', 'attempt'], ['name' => 'uniq_plugin_event_delivery_attempt', 'unique' => true]);
            $table->addIndex(['event_id', 'status'], ['name' => 'idx_plugin_event_log_event_status']);
            $table->addIndex(['plugin_code', 'status'], ['name' => 'idx_plugin_event_log_plugin_status']);
            $table->addIndex('create_time', ['name' => 'idx_plugin_event_log_created']);
            $table->create();
        }
    }

    public function down(): void
    {
        foreach (['plugin_event_log', 'plugin_event_outbox'] as $name) {
            if ($this->hasTable($name)) {
                $this->table($name)->drop()->save();
            }
        }
    }
}
