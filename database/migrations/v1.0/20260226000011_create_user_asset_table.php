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
 * 用户资产表
 */
class CreateUserAssetTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('user_asset', ['comment' => '用户资产表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '用户ID']);
        $table->addColumn('balance', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '余额']);
        $table->addColumn('points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '积分']);
        $table->addColumn('coins', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '金币']);
        $table->addColumn('exp', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '经验值']);
        $table->addColumn('commission', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'comment' => '可用佣金余额']);
        $table->addTimestamps();
        $table->addIndex('user_id', ['unique' => true]);
        $table->create();
    }
}
