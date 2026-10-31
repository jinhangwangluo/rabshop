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
 * 给平台管理员增加消息提醒配置字段。
 */
class AddMessageNotifyFieldsToAdminTable extends Migrator
{
    /**
     * 增加消息提醒开关与提醒手机号。
     */
    public function change(): void
    {
        $table = $this->table('admin');

        if (!$table->hasColumn('message_notify_enabled')) {
            $table->addColumn('message_notify_enabled', 'boolean', [
                'limit' => 1,
                'default' => 0,
                'comment' => '消息提醒：0=关闭，1=开启',
                'after' => 'user_id',
            ]);
        }

        if (!$table->hasColumn('message_notify_phone')) {
            $table->addColumn('message_notify_phone', 'string', [
                'limit' => 20,
                'default' => '',
                'comment' => '消息提醒手机号',
                'after' => 'message_notify_enabled',
            ]);
        }

        if (!$table->hasIndexByName('idx_platform_status_notify')) {
            $table->addIndex(['platform_type', 'status', 'message_notify_enabled'], [
                'name' => 'idx_platform_status_notify',
            ]);
        }

        $table->update();
    }
}
