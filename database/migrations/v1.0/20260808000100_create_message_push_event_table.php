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
 * 创建自定义消息推送事件表。
 */
class CreateMessagePushEventTable extends Migrator
{
    /**
     * 创建消息推送事件表。
     */
    public function change(): void
    {
        $table = $this->table('message_push_event', [
            'comment' => '消息推送自定义事件表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('event_key', 'string', [
            'limit' => 60,
            'default' => '',
            'comment' => '消息事件标识',
        ]);
        $table->addColumn('event_name', 'string', [
            'limit' => 120,
            'default' => '',
            'comment' => '消息事件名称',
        ]);
        $table->addColumn('receiver_type', 'string', [
            'limit' => 30,
            'default' => 'user',
            'comment' => '通知类型：user/merchant/admin',
        ]);
        $table->addColumn('relation_type', 'string', [
            'limit' => 60,
            'default' => '',
            'comment' => '关联业务类型',
        ]);
        $table->addColumn('variables', 'json', [
            'null' => true,
            'comment' => '可用变量JSON',
        ]);
        $table->addColumn('status', 'boolean', [
            'limit' => 1,
            'default' => 1,
            'comment' => '状态：0=禁用，1=启用',
        ]);
        $table->addColumn('sort', 'integer', [
            'limit' => 11,
            'default' => 1000,
            'comment' => '排序',
        ]);
        $table->addColumn('remark', 'string', [
            'limit' => 500,
            'default' => '',
            'comment' => '备注',
        ]);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('event_key', ['name' => 'uniq_event_key', 'unique' => true]);
        $table->addIndex(['receiver_type', 'status'], ['name' => 'idx_receiver_status']);
        $table->addIndex('sort', ['name' => 'idx_sort']);
        $table->create();
    }
}
