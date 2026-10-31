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

namespace app\common\model\search;

use app\common\model\Base;

/**
 * 搜索关键词统计模型.
 */
class SearchKeywordStat extends Base
{
    /**
     * 表名.
     *
     * @var string
     */
    protected $name = 'search_keyword_stat';

    /**
     * 主键.
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳.
     *
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换.
     *
     * @var array
     */
    protected $type = [
        'search_count' => 'integer',
        'result_count' => 'integer',
    ];

    /**
     * 搜索器：关键词.
     */
    public function searchKeywordAttr($query, $value): void
    {
        $keyword = trim((string)$value);
        if ($keyword !== '') {
            $query->where('keyword', 'like', '%' . $keyword . '%');
        }
    }
}
