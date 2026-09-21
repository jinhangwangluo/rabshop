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
 * 创建后台消息记录表。
 */
class CreateBackendMessageTable extends Migrator
{
    /**
     * 创建后台消息记录表。
     */
    public function change(): void
    {
        $table = $this->table('backend_message', [
            'comment' => '后台消息记录表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);

        $table->addColumn('receiver_type', 'string', ['limit' => 20, 'default' => 'platform', 'comment' => '接收端类型：platform=平台端，merchant=商户端']);
        $table->addColumn('receiver_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '接收端ID：平台全体为0，商户端为merchant_id',]);
        $table->addColumn('message_group', 'string', ['limit' => 30, 'default' => '', 'comment' => '消息分组：order/finance/user/audit']);
        $table->addColumn('message_sub_type', 'string', ['limit' => 50, 'default' => '', 'comment' => '消息子类型']);
        $table->addColumn('relation_type', 'string', ['limit' => 50, 'default' => '', 'comment' => '关联业务类型']);
        $table->addColumn('relation_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '关联业务ID']);
        $table->addColumn('title', 'string', ['limit' => 120, 'default' => '', 'comment' => '消息标题']);
        $table->addColumn('content', 'text', ['null' => true, 'default' => null, 'comment' => '消息内容']);
        $table->addColumn('extra', 'text', ['null' => true, 'default' => null, 'comment' => '扩展数据JSON']);
        $table->addColumn('read_status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '已读状态：0=未读，1=已读']);
        $table->addColumn('read_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '读取时间']);
        $table->addColumn('clear_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '清理隐藏时间']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['receiver_type', 'receiver_id', 'read_status'], ['name' => 'idx_receiver_read']);
        $table->addIndex(['receiver_type', 'receiver_id', 'clear_time'], ['name' => 'idx_receiver_clear']);
        $table->addIndex(['message_group', 'message_sub_type'], ['name' => 'idx_message_type']);
        $table->addIndex(['relation_type', 'relation_id'], ['name' => 'idx_relation']);
        $table->addIndex('create_time', ['name' => 'idx_create_time']);
        $table->create();
    }
}
