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
 * 创建插件请求加密密钥和 nonce 防重放表。
 */
class CreatePluginSecurityTables extends Migrator
{
    public function up(): void
    {
        if (!$this->hasTable('plugin_security_key')) {
            $table = $this->table('plugin_security_key', [
                'comment' => '插件安全密钥元数据',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('key_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('owner_type', 'string', ['limit' => 20, 'default' => 'instance']);
            $table->addColumn('owner_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('key_use', 'string', ['limit' => 20, 'default' => 'signing']);
            $table->addColumn('algorithm', 'string', ['limit' => 32, 'default' => 'ES256']);
            $table->addColumn('public_key_pem', 'text', ['null' => false]);
            $table->addColumn('private_key_ciphertext', 'text', ['null' => true, 'default' => null]);
            $table->addColumn('wrapping_key_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('status', 'string', ['limit' => 20, 'default' => 'active']);
            $table->addColumn('not_before', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('expires_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('revoked_at', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addColumn('update_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex('key_id', ['name' => 'uniq_plugin_security_key', 'unique' => true]);
            $table->addIndex(['owner_type', 'owner_id', 'key_use', 'status'], ['name' => 'idx_plugin_security_owner']);
            $table->addIndex('expires_at', ['name' => 'idx_plugin_security_expires']);
            $table->create();
        }

        if (!$this->hasTable('plugin_request_nonce')) {
            $table = $this->table('plugin_request_nonce', [
                'id' => false,
                'primary_key' => ['id'],
                'comment' => '插件请求 nonce 防重放记录',
                'collation' => 'utf8mb4_general_ci',
                'engine' => 'InnoDB',
            ]);
            $table->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true]);
            $table->addColumn('client_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('nonce_hash', 'char', ['limit' => 64, 'default' => '']);
            $table->addColumn('request_id', 'string', ['limit' => 64, 'default' => '']);
            $table->addColumn('expires_at', 'timestamp', ['null' => false]);
            $table->addColumn('create_time', 'timestamp', ['null' => true, 'default' => null]);
            $table->addIndex(['client_id', 'nonce_hash'], ['name' => 'uniq_plugin_nonce', 'unique' => true]);
            $table->addIndex('expires_at', ['name' => 'idx_plugin_nonce_expires']);
            $table->create();
        }
    }

    public function down(): void
    {
        foreach (['plugin_request_nonce', 'plugin_security_key'] as $name) {
            if ($this->hasTable($name)) {
                $this->table($name)->drop()->save();
            }
        }
    }
}
