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
 * 直播间用户记录表
 * 职责：
 *  1. 记录谁进入过该直播间（用户列表）
 *  2. 实时在线状态维护
 *  3. 禁言状态管理
 */
class CreateLiveRoomUserTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('live_room_user', ['comment' => '直播间用户记录表', 'collation' => 'utf8mb4_general_ci']);

        $table->addColumn('room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('nickname', 'string', ['limit' => 100, 'default' => '', 'comment' => '进入时昵称快照']);
        $table->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '进入时头像快照']);

        // 在线状态
        $table->addColumn('is_online', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否在线：0-否，1-是']);
        $table->addColumn('online_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '本场进入次数']);
        $table->addColumn('last_enter_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最后进入时间']);
        $table->addColumn('last_leave_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最后离开时间']);

        // 禁言状态
        $table->addColumn('is_muted', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否被禁言：0-否，1-是']);
        $table->addColumn('mute_end_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '禁言到期时间，null 表示未禁言']);
        $table->addColumn('mute_admin_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '禁言操作管理员ID']);
        $table->addColumn('mute_reason', 'string', ['limit' => 255, 'default' => '', 'comment' => '禁言原因']);

        $table->addTimestamps();

        // 同一用户在同一直播间只有一条记录
        $table->addIndex(['room_id', 'user_id'], ['unique' => true, 'name' => 'uniq_room_user']);
        $table->addIndex(['room_id', 'is_online'], ['name' => 'idx_room_online']);
        $table->addIndex(['room_id', 'is_muted'], ['name' => 'idx_room_muted']);
        $table->addIndex('user_id');

        $table->create();
    }
}
