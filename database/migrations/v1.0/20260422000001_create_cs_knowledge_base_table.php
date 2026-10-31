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
 * 知识库实体表
 * 一个知识库（base）下可包含多篇文档（cs_knowledge）
 */
class CreateCsKnowledgeBaseTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('cs_knowledge_base', [
            'comment'   => '知识库实体表',
            'collation' => 'utf8mb4_general_ci',
        ]);

        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID，0=平台']);
        $table->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '知识库名称']);
        $table->addColumn('description', 'string', ['limit' => 500, 'default' => '', 'comment' => '知识库描述']);
        $table->addColumn('cover', 'string', ['limit' => 500, 'default' => '', 'comment' => '封面图URL']);
        $table->addColumn('ai_model_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '绑定AI模型ID（用于测试回复）']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用,1-启用']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序（升序）']);
        $table->addColumn('threshold', 'decimal', ['precision'  => 4, 'scale'      => 2, 'default'    => '0.60', 'comment'    => '相似度阈值（0~1），低于此值的命中不纳入检索结果']);
        $table->addColumn('search_limit', 'integer', ['limit'   => 3, 'default' => 5, 'comment' => '每次检索该知识库最多贡献的结果条数',]);

        $table->addTimestamps();
        $table->addSoftDelete();

        $table->addIndex('merchant_id');
        $table->addIndex('status');
        $table->addIndex('sort');

        $table->create();
    }
}
