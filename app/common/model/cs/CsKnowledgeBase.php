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
 * 知识库实体模型
 */
class CsKnowledgeBase extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_knowledge_base';

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
        'threshold'    => 'float',
        'search_limit' => 'integer',
    ];

    /**
     * 搜索器：商户ID
     */
    public function searchMerchantIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：名称模糊
     */
    public function searchNameAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 关联：绑定的AI模型
     */
    public function aiModel()
    {
        return $this->belongsTo(CsAiModel::class, 'ai_model_id', 'id');
    }
}
