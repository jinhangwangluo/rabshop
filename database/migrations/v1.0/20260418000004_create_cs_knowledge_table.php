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
 * 知识库表
 */
class CreateCsKnowledgeTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cs_knowledge', ['comment' => '知识库表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'comment' => '知识标题/问题']);
        $table->addColumn('content', 'text', ['null' => true, 'comment' => '知识内容/答案']);
        $table->addColumn('knowledge_base_id', 'integer', ['limit'   => 11, 'default' => 0, 'comment' => '所属知识库ID（cs_knowledge_base.id）']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID 0=平台']);
        $table->addColumn('source', 'string', ['limit' => 20, 'default' => 'manual', 'comment' => '来源：manual/file/url']);
        $table->addColumn('source_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '来源URL']);
        $table->addColumn('vector_status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '向量化状态：0-未向量化,1-向量化中,2-已向量化']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用,1-启用']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('views', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '查看次数']);
        $table->addColumn('last_vector_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最后向量化时间']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('knowledge_base_id');
        $table->addIndex('merchant_id');
        $table->addIndex('source');
        $table->addIndex('vector_status');
        $table->addIndex('status');
        $table->addIndex('create_time');
        $table->create();
    }
}
