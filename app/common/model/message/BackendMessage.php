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

declare(strict_types=1);

namespace app\common\model\message;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 后台消息记录模型。
 */
class BackendMessage extends Base
{
    use SoftDelete;

    /**
     * 表名。
     *
     * @var string
     */
    protected $name = 'backend_message';

    /**
     * 主键。
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳。
     *
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * JSON 字段。
     *
     * @var array
     */
    protected $json = ['extra'];

    /**
     * JSON 字段返回关联数组。
     *
     * @var bool
     */
    protected $jsonAssoc = true;

    /**
     * 字段类型。
     *
     * @var array
     */
    protected $type = [
        'receiver_id' => 'integer',
        'relation_id' => 'integer',
        'extra' => 'array',
        'read_status' => 'integer',
    ];

    /**
     * 搜索器：接收端类型。
     */
    public function searchReceiverTypeAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('receiver_type', (string)$value);
        }
    }

    /**
     * 搜索器：接收端 ID。
     */
    public function searchReceiverIdAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('receiver_id', (int)$value);
        }
    }

    /**
     * 搜索器：消息分组。
     */
    public function searchMessageGroupAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('message_group', (string)$value);
        }
    }

    /**
     * 搜索器：已读状态。
     */
    public function searchReadStatusAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('read_status', (int)$value);
        }
    }
}
