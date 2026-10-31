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
 * 知识库向量辅助模型
 */
class CsKnowledgeVector extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_knowledge_vector';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     */
    protected $type = [
        'vector' => 'json',
    ];

    /**
     * 搜索器：知识库ID
     */
    public function searchKnowledgeIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('knowledge_id', $value);
        }
    }

    /**
     * 搜索器：内容MD5
     */
    public function searchContentMd5Attr($query, $value)
    {
        if ($value !== '') {
            $query->where('content_md5', $value);
        }
    }

    /**
     * 搜索器：来源类型
     */
    public function searchSourceTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('source_type', $value);
        }
    }

    /**
     * 搜索器：来源ID
     */
    public function searchSourceIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('source_id', $value);
        }
    }

    /**
     * 关联：知识库
     */
    public function knowledge()
    {
        return $this->belongsTo(CsKnowledge::class, 'knowledge_id', 'id');
    }

    /**
     * 关联：段落（source_type=segment 时有效）
     */
    public function segment()
    {
        return $this->belongsTo(CsKnowledgeSegment::class, 'source_id', 'id');
    }

    /**
     * 关联：QA（source_type=qa 时有效）
     */
    public function qa()
    {
        return $this->belongsTo(CsKnowledgeQa::class, 'source_id', 'id');
    }
}
