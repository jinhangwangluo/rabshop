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
 * 全局收藏表
 */
class CreateUserFavoriteTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('user_favorite', ['comment' => '用户收藏表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('target_type', 'string', ['limit' => 30, 'default' => '', 'comment' => '收藏对象类型：special/content/product等']);
        $table->addColumn('target_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '收藏对象ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-未收藏，1-已收藏']);
        $table->addTimestamps();
        $table->addIndex('user_id');
        $table->addIndex('target_type');
        $table->addIndex('target_id');
        $table->addIndex('merchant_id');
        $table->addIndex('status');
        $table->addIndex(['target_type', 'target_id']);
        $table->addIndex(['user_id', 'target_type', 'target_id'], ['name' => 'uk_user_target_favorite', 'unique' => true]);
        $table->create();
    }
}
