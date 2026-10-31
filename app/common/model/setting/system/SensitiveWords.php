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

namespace app\common\model\setting\system;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 敏感词模型
 */
class SensitiveWords extends Base
{
    use SoftDelete;

    /**
     * 表名
     */
    protected $name = 'sensitive_words';

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
        'status'   => 'integer',
        'admin_id' => 'integer',
    ];

    /**
     * 搜索器：敏感词关键词
     */
    public function searchWordAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('word', 'like', '%' . $value . '%');
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
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        return ($data['status'] ?? 1) == 1 ? '启用' : '禁用';
    }
}
