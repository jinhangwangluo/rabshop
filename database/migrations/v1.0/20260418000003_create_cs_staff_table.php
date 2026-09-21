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
 * 客服人员表
 */
class CreateCsStaffTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cs_staff', ['comment' => '客服人员表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID 0=平台']);
        $table->addColumn('admin_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '关联管理员ID']);
        $table->addColumn('staff_no', 'string', ['limit' => 32, 'default' => '', 'comment' => '客服工号']);
        $table->addColumn('nickname', 'string', ['limit' => 64, 'default' => '', 'comment' => '客服昵称']);
        $table->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'comment' => '客服头像']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-离线,1-在线,2-忙碌']);
        $table->addColumn('is_online', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否在线：0-离线,1-在线']);
        $table->addColumn('current_sessions', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '当前服务会话数']);
        $table->addColumn('max_sessions', 'integer', ['limit' => 11, 'default' => 10, 'comment' => '最大同时服务会话数']);
        $table->addColumn('total_sessions', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '累计服务会话数']);
        $table->addColumn('total_messages', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '累计发送消息数']);
        $table->addColumn('last_online_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最后上线时间']);
        $table->addColumn('last_offline_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最后离线时间']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('merchant_id');
        $table->addIndex('admin_id');
        $table->addIndex('staff_no', ['unique' => true]);
        $table->addIndex('status');
        $table->addIndex('is_online');
        $table->create();
    }
}
