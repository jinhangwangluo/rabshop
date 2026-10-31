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
 * 文章内容表
 */
class CreateArticleContentTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('article_content', ['comment' => '文章内容表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('article_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '文章ID']);
        $table->addColumn('content', 'text', ['comment' => '文章内容']);
        $table->addColumn('trial_content', 'text', ['comment' => '试看内容']);
        $table->addColumn('images', 'text', ['null' => true, 'comment' => '图片集合（JSON数组，图片类型使用）']);
        $table->addColumn('trial_images', 'text', ['null' => true, 'comment' => '试看图片集合（JSON数组，图片类型使用）']);
        $table->addIndex('article_id', ['unique' => true]);
        $table->create();
    }
}
