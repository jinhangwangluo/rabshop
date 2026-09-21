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
 * 知识库问答模型
 */
class CsKnowledgeQa extends Base
{
    protected $name = 'cs_knowledge_qa';

    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    /** 向量化状态 */
    const VECTOR_STATUS_PENDING = 0;
    const VECTOR_STATUS_PROCESSING = 1;
    const VECTOR_STATUS_COMPLETED = 2;

    /**
     * 搜索器：段落ID
     */
    public function searchSegmentIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('segment_id', $value);
        }
    }

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
     * 关联：所属段落
     */
    public function segment()
    {
        return $this->belongsTo(CsKnowledgeSegment::class, 'segment_id', 'id');
    }

    /**
     * 关联：所属知识库
     */
    public function knowledge()
    {
        return $this->belongsTo(CsKnowledge::class, 'knowledge_id', 'id');
    }
}
