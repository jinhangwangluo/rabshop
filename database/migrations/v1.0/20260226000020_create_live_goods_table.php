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
 * 直播带货关联表
 */
class CreateLiveGoodsTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('live_goods', ['comment' => '直播带货关联表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('live_room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID']);
        $table->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品ID']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('goods_type', 'string', ['limit'   => 32, 'default' => 'special', 'comment' => '商品类型: article=单篇内容, special=付费专题']);
        $table->addColumn('goods_title', 'string', ['limit'   => 200, 'default' => '', 'comment' => '展示商品名称（可自定义）']);
        $table->addColumn('goods_cover', 'string', ['limit'   => 500, 'default' => '', 'comment' => '展示封面图']);
        $table->addColumn('goods_price', 'decimal', ['precision' => 10, 'scale'     => 2, 'default'   => '0.00', 'comment'   => '展示价格（可自定义）']);
        $table->addColumn('link_no', 'integer', ['limit'   => 4, 'default' => 0, 'comment' => '商品链接编号（同直播间内自增，如1号链接）']);
        $table->addColumn('status', 'tinyinteger', ['limit'   => 1, 'default' => 1, 'comment' => '状态：1=上架，0=下架']);
        $table->addColumn('is_explaining', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否正在讲解：0-否，1-是']);
        $table->addIndex(['live_room_id', 'link_no'], ['name' => 'idx_room_link_no']);
        $table->addIndex(['live_room_id', 'is_explaining'], ['name' => 'idx_room_explaining']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('live_room_id');
        $table->addIndex('goods_id');
        $table->create();
    }
}
