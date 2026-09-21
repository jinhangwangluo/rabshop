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
 * 新增直播礼物表
 */
class CreateLiveGiftTable extends Migrator
{
    public function change()
    {
        $table = $this->table('live_gift', ['comment' => '直播礼物表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '礼物名称']);
        $table->addColumn('icon', 'string', ['limit' => 500, 'default' => '', 'comment' => '礼物图标']);
        $table->addColumn('effect_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '礼物特效视频地址（仅WebM）']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '礼物价格（金币）']);
        $table->addColumn('exchange_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '主播可兑换金额（元）']);
        $table->addColumn('score', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '打赏积分']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('status');
        $table->create();
    }
}
