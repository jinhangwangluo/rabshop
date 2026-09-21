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
 * 知识库向量辅助表
 */
class CreateCsKnowledgeVectorTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('cs_knowledge_vector', ['comment' => '知识库向量辅助表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('knowledge_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '知识库ID']);
        $table->addColumn('vector', 'text', ['null' => true, 'comment' => '向量数据(JSON序列化的1536维浮点数组)']);
        $table->addColumn('content_md5', 'string', ['limit' => 32, 'default' => '', 'comment' => '内容MD5(避免重复计算)']);
        $table->addColumn('model', 'string', ['limit' => 64, 'default' => 'text-embedding-v2', 'comment' => '向量化模型']);
        $table->addColumn('source_type', 'string', ['limit'   => 20, 'default' => 'knowledge', 'comment' => '来源类型：knowledge/segment/qa']);
        $table->addColumn('source_id', 'integer', ['limit'   => 11, 'default' => 0, 'comment' => '来源记录ID（与 source_type 组合唯一）']);
        $table->addIndex(['source_type', 'source_id'], ['unique' => true]);
        $table->addTimestamps();
        $table->addIndex('knowledge_id');
        $table->addIndex('content_md5');
        $table->create();
    }
}
