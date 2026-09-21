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
 * 直播间聊天/弹幕持久化表（与 WebSocket LiveChatHandler、后台评论列表一致）
 */
class CreateLiveChatRecordTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('live_chat_record', ['comment' => '直播间聊天记录表', 'collation' => 'utf8mb4_general_ci']);

        $table->addColumn('msg_id', 'string', ['limit' => 64, 'default' => '', 'comment' => '业务消息ID（如 chat_时间_user_rand）']);
        $table->addColumn('room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '发送用户ID']);
        $table->addColumn('nickname', 'string', ['limit' => 100, 'default' => '', 'comment' => '发送时昵称快照']);
        $table->addColumn('avatar', 'string', ['limit' => 512, 'default' => '', 'comment' => '发送时头像快照']);
        $table->addColumn('content', 'text', ['null' => true, 'comment' => '消息正文']);
        $table->addColumn('msg_type', 'string', ['limit' => 32, 'default' => 'text', 'comment' => '消息类型：text/gift/system 等']);
        $table->addColumn('send_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '业务发送时间']);

        $table->addTimestamps();

        $table->addIndex('room_id', ['name' => 'idx_room_id']);
        $table->addIndex('user_id', ['name' => 'idx_user_id']);
        $table->addIndex('msg_id', ['name' => 'idx_msg_id']);
        $table->addIndex(['room_id', 'id'], ['name' => 'idx_room_id_id']);

        $table->create();
    }
}
