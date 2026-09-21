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
 * 用户直播间等级记录表
 */
class CreateLiveRoomUserLevelTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('live_room_user_level', ['comment' => '用户直播间等级记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('live_room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID']);
        $table->addColumn('level', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '当前等级（0=无等级）']);
        $table->addColumn('contribution', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'comment' => '累计贡献值（礼物 price 总和，元）']);
        $table->addColumn('gift_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '累计送礼次数']);
        $table->addTimestamps();
        $table->addIndex(['user_id', 'live_room_id'], ['unique' => true]);
        $table->addIndex('live_room_id');
        $table->addIndex('level');
        $table->create();
    }
}
