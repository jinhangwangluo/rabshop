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
 * 用户搜索历史模型.
 */
class UserSearchHistory extends Base
{
    /**
     * 表名.
     *
     * @var string
     */
    protected $name = 'user_search_history';

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
        'user_id' => 'integer',
        'search_count' => 'integer',
    ];

    /**
     * 搜索器：用户ID.
     */
    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('user_id', (int)$value);
        }
    }

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
