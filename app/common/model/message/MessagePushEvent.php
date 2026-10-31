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
 * 消息推送自定义事件模型。
 */
class MessagePushEvent extends Base
{
    use SoftDelete;

    /**
     * 表名。
     *
     * @var string
     */
    protected $name = 'message_push_event';

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
    protected $json = ['variables'];

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
        'variables' => 'array',
        'status' => 'integer',
        'sort' => 'integer',
    ];

    /**
     * 搜索器：事件标识。
     */
    public function searchEventKeyAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('event_key', (string)$value);
        }
    }

    /**
     * 搜索器：通知类型。
     */
    public function searchReceiverTypeAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('receiver_type', (string)$value);
        }
    }

    /**
     * 搜索器：启用状态。
     */
    public function searchStatusAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('status', (int)$value);
        }
    }
}
