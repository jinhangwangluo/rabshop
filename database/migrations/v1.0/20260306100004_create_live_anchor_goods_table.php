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
 * 新增主播可挂载商品分配表
 */
class CreateLiveAnchorGoodsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('live_anchor_goods', ['comment' => '主播可挂载商品分配表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('live_anchor_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '主播ID']);
        $table->addColumn('goods_type', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '商品类型：1-专题，2-单篇内容']);
        $table->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商品ID（special_id 或 article_id）']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('live_anchor_id');
        $table->addIndex('merchant_id');
        $table->addIndex(['live_anchor_id', 'goods_id', 'goods_type'], ['unique' => true]);
        $table->create();
    }
}
