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
 * 知识库段落表：一条知识可拆分为多个段落，每段独立向量化
 */
class CreateCsKnowledgeSegmentTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('cs_knowledge_segment', [
            'comment'   => '知识库段落表',
            'collation' => 'utf8mb4_general_ci',
        ]);

        $table->addColumn('knowledge_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '所属知识库ID']);
        $table->addColumn('content', 'text', ['null' => true, 'comment' => '段落内容']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（升序）']);
        $table->addColumn('vector_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '向量化状态：0-未向量化,1-向量化中,2-已向量化']);

        $table->addTimestamps();
        $table->addSoftDelete();

        $table->addIndex('knowledge_id');
        $table->addIndex('vector_status');

        $table->create();
    }
}
