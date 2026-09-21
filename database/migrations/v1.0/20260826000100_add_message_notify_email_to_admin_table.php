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
 * 给管理员增加消息提醒邮箱字段。
 */
class AddMessageNotifyEmailToAdminTable extends Migrator
{
    /**
     * 增加消息提醒邮箱。
     */
    public function change(): void
    {
        $table = $this->table('admin');

        if (!$table->hasColumn('message_notify_email')) {
            $table->addColumn('message_notify_email', 'string', [
                'limit' => 160,
                'default' => '',
                'comment' => '消息提醒邮箱',
                'after' => 'message_notify_phone',
            ]);
        }

        $table->update();
    }
}
