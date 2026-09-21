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

namespace app\common\model\cs;

use app\common\model\Base;

/**
 * 知识库段落模型
 */
class CsKnowledgeSegment extends Base
{
    protected $name = 'cs_knowledge_segment';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    /** 向量化状态 */
    const VECTOR_STATUS_PENDING = 0;
    const VECTOR_STATUS_PROCESSING = 1;
    const VECTOR_STATUS_COMPLETED = 2;

    /**
     * 搜索器：知识ID
     */
    public function searchKnowledgeIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('knowledge_id', $value);
        }
    }

    /**
     * 搜索器：向量化状态
     */
    public function searchVectorStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('vector_status', $value);
        }
    }

    /**
     * 关联：所属知识库
     */
    public function knowledge()
    {
        return $this->belongsTo(CsKnowledge::class, 'knowledge_id', 'id');
    }

    /**
     * 关联：QA列表
     */
    public function qaList()
    {
        return $this->hasMany(CsKnowledgeQa::class, 'segment_id', 'id');
    }
}
