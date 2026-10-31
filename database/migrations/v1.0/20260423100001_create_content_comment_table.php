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
 * 内容评论表
 */
class CreateContentCommentTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('content_comment', ['comment' => '内容评论表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('article_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '所属内容ID']);
        $table->addColumn('user_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '评论者用户ID']);
        $table->addColumn('pid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '父评论ID，0表示一级评论']);
        $table->addColumn('root_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '根评论ID，与pid=0的评论ID一致，用于聚合查询']);
        $table->addColumn('reply_to_uid', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '被回复的用户ID']);
        $table->addColumn('reply_to_nickname', 'string', ['limit' => 64, 'default' => '', 'comment' => '被回复用户昵称快照，避免关联查询']);
        $table->addColumn('content', 'text', ['null' => true, 'default' => null, 'comment' => '评论文字内容']);
        $table->addColumn('images', 'text', ['null' => true, 'default' => null, 'comment' => '评论图片，JSON数组，最多5张']);
        $table->addColumn('is_hot', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否热门：0-否，1-是']);
        $table->addIndex('article_id');
        $table->addIndex('user_id');
        $table->addIndex('pid');
        $table->addIndex('root_id');
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->create();
    }
}
