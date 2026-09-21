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
 * 知识库模型
 */
class CsKnowledge extends Base
{
    /**
     * 表名
     */
    protected $name = 'cs_knowledge';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 向量化状态常量
     */
    const VECTOR_STATUS_PENDING = 0;    // 未向量化
    const VECTOR_STATUS_PROCESSING = 1;  // 向量化中
    const VECTOR_STATUS_COMPLETED = 2;  // 已向量化

    /**
     * 来源常量
     */
    const SOURCE_MANUAL = 'manual';  // 手动录入
    const SOURCE_FILE = 'file';      // 文件导入
    const SOURCE_URL = 'url';        // URL采集

    /**
     * 搜索器：知识库ID
     */
    public function searchKnowledgeBaseIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('knowledge_base_id', $value);
        }
    }

    /**
     * 搜索器：分类ID（保留兼容旧数据）
     */
    public function searchCategoryIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('category_id', $value);
        }
    }

    /**
     * 搜索器：商户ID
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：来源
     */
    public function searchSourceAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('source', $value);
        }
    }

    /**
     * 搜索器：向量化状态
     */
    public function searchVectorStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('vector_status', $value);
        }
    }

    /**
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：标题
     */
    public function searchTitleAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('title', 'like', '%' . $value . '%');
        }
    }

    /**
     * 获取器：向量化状态文本
     */
    public function getVectorStatusTextAttr($value, $data)
    {
        $status = [
            self::VECTOR_STATUS_PENDING => '未向量化',
            self::VECTOR_STATUS_PROCESSING => '向量化中',
            self::VECTOR_STATUS_COMPLETED => '已向量化',
        ];
        return $status[$data['vector_status'] ?? 0] ?? '未知';
    }

    /**
     * 获取器：来源文本
     */
    public function getSourceTextAttr($value, $data)
    {
        $sources = [
            self::SOURCE_MANUAL => '手动录入',
            self::SOURCE_FILE => '文件导入',
            self::SOURCE_URL => 'URL采集',
        ];
        return $sources[$data['source'] ?? 'manual'] ?? '未知';
    }

    /**
     * 关联：向量
     */
    public function vector()
    {
        return $this->hasOne(CsKnowledgeVector::class, 'knowledge_id', 'id');
    }

    /**
     * 关联：分类
     */
    public function category()
    {
        return $this->belongsTo(\app\common\model\setting\system\Category::class, 'category_id', 'id');
    }
}
