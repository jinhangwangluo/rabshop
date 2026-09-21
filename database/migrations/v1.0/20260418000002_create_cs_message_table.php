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
 * 聊天消息表
 */
class CreateCsMessageTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cs_message', ['comment' => '聊天消息表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('session_id', 'string', ['limit' => 32, 'default' => '', 'comment' => '会话ID']);
        $table->addColumn('sender_type', 'string', ['limit' => 20, 'default' => 'user', 'comment' => '发送者类型：user/staff/system/ai']);
        $table->addColumn('sender_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '发送者ID']);
        $table->addColumn('content', 'text', ['null' => true, 'comment' => '消息内容']);
        $table->addColumn('message_type', 'string', ['limit' => 20, 'default' => 'text', 'comment' => '消息类型：text/image/file']);
        $table->addColumn('extra', 'text', ['null' => true, 'comment' => '额外信息(JSON)']);
        $table->addColumn('is_read', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否已读：0-未读,1-已读']);
        $table->addColumn('read_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '阅读时间']);
        $table->addColumn('msg_uuid', 'string', ['limit' => 64, 'default' => '', 'comment' => '消息唯一标识(防重复)']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('session_id');
        $table->addIndex('sender_type');
        $table->addIndex('sender_id');
        $table->addIndex('is_read');
        $table->addIndex('msg_uuid');
        $table->addIndex('create_time');
        $table->create();
    }
}
