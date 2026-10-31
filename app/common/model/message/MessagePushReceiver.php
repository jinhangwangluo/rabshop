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
 * 消息推送手动接收人模型。
 */
class MessagePushReceiver extends Base
{
    use SoftDelete;

    /**
     * 表名。
     *
     * @var string
     */
    protected $name = 'message_push_receiver';

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
     * 字段类型。
     *
     * @var array
     */
    protected $type = [
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
     * 搜索器：启用状态。
     */
    public function searchStatusAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('status', (int)$value);
        }
    }
}
