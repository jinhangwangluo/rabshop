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
 * 客服会话表
 */
class CreateCsSessionTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cs_session', ['comment' => '客服会话表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('session_id', 'string', ['limit' => 32, 'default' => '', 'comment' => '会话唯一标识']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID 0=平台']);
        $table->addColumn('staff_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '客服人员ID']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-待接入,1-服务中,2-已结束']);
        $table->addColumn('service_type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '服务类型：1-AI优先,2-人工']);
        $table->addColumn('unread_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '未读消息数']);
        $table->addColumn('last_message', 'text', ['null' => true, 'comment' => '最后一条消息内容']);
        $table->addColumn('last_message_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最后一条消息时间']);
        $table->addColumn('finish_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '结束时间']);
        $table->addColumn('finish_remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '结束备注']);
        $table->addColumn('user_ip', 'string', ['limit' => 64, 'default' => '', 'comment' => '用户IP']);
        $table->addColumn('user_agent', 'string', ['limit' => 500, 'default' => '', 'comment' => '用户浏览器信息']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('session_id', ['unique' => true]);
        $table->addIndex('user_id');
        $table->addIndex('merchant_id');
        $table->addIndex('staff_id');
        $table->addIndex('status');
        $table->addIndex('create_time');
        $table->create();
    }
}
