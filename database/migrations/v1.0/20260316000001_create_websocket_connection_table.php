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
 * WebSocket 连接记录表
 */
class CreateWebsocketConnectionTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('websocket_connection', ['comment' => 'WebSocket 连接记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('fd', 'integer', ['limit' => 20, 'default' => 0, 'comment' => 'Swoole 连接描述符（主键）']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户 ID']);
        $table->addColumn('client_type', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '客户端类型：1=平台端，2=商户端，3=用户端，4=直播间']);
        $table->addColumn('room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID（仅 client_type=4 时有值）']);
        $table->addColumn('token', 'string', ['limit' => 512, 'default' => '', 'comment' => '登录 token']);
        $table->addColumn('ip', 'string', ['limit' => 50, 'default' => '', 'comment' => '客户端 IP']);
        $table->addColumn('last_active_time', 'timestamp', ['null' => true, 'comment' => '最后活跃时间']);
        $table->addTimestamps();
        $table->addIndex('fd', ['unique' => true]);
        $table->addIndex('user_id');
        $table->addIndex('client_type');
        $table->addIndex('room_id');
        $table->create();
    }
}
