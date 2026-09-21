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
 * 全局浏览记录表
 */
class CreateUserPreviewRecordTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('user_browse_record', ['comment' => '用户浏览记录表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('target_type', 'string', ['limit' => 30, 'default' => '', 'comment' => '浏览对象类型：special/content/product等']);
        $table->addColumn('target_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '浏览对象ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('browse_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '浏览次数']);
        $table->addColumn('last_browse_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最近浏览时间']);
        $table->addTimestamps();
        $table->addIndex('user_id');
        $table->addIndex('target_type');
        $table->addIndex('target_id');
        $table->addIndex('merchant_id');
        $table->addIndex(['target_type', 'target_id']);
        $table->addIndex(['user_id', 'last_browse_time'], ['name' => 'idx_user_last_browse_time']);
        $table->addIndex(['user_id', 'target_type', 'target_id'], ['name' => 'uk_user_target_browse', 'unique' => true]);
        $table->create();
    }
}
