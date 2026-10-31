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
 * 直播间等级配置表
 */
class CreateLiveRoomLevelConfigTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('live_room_level_config', ['comment' => '直播间等级配置表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('level', 'integer', ['limit' => 11, 'default' => 1, 'comment' => '等级值（唯一）']);
        $table->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '等级称号']);
        $table->addColumn('icon', 'string', ['limit' => 512, 'default' => '', 'comment' => '等级徽章图标 URL']);
        $table->addColumn('min_contribution', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'comment' => '达到该等级所需最低累计贡献值（元）']);
        $table->addColumn('benefits', 'text', ['null' => true, 'comment' => '权益配置 JSON：gift_discount/upgrade_points']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addIndex('level', ['unique' => true]);
        $table->addIndex('status');
        $table->create();
    }
}
