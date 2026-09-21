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

namespace app\common\model\content;

use app\common\enum\ContentTypeEnum;
use app\common\model\Base;

/**
 * 专题内容模型（中间表：关联专题与内容）
 */
class SpecialContent extends Base
{
    /**
     * 表名
     */
    protected $name = 'special_content';

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
        'type'       => 'integer',
        'article_id' => 'integer',
        'sort'       => 'integer',
    ];

    /**
     * 搜索器：专题ID
     */
    public function searchSpecialIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('special_id', $value);
        }
    }

    /**
     * 搜索器：内容类型
     */
    public function searchTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('type', $value);
        }
    }

    /**
     * 获取器：内容类型文本
     */
    public function getTypeTextAttr($value, $data): string
    {
        return ContentTypeEnum::getName((int)($data['type'] ?? 1));
    }

    /**
     * 关联：内容（article表）
     */
    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id', 'id');
    }

    /**
     * 关联：专题
     */
    public function special()
    {
        return $this->belongsTo(Special::class, 'special_id', 'id');
    }
}
