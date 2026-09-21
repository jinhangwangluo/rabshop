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
 * 专题内容表
 */
class CreateSpecialContentTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('special_content', ['comment' => '专题内容表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('special_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '专题ID']);
        $table->addColumn('type', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '内容类型：0-文章，1-商品，2-直播']);
        $table->addColumn('article_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '内容ID']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('is_trial', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否支持试看/试读：0-否，1-是']);
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('special_id');
        $table->addIndex('type');
        $table->create();
    }
}
