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
 * 消息推送模板配置模型。
 */
class MessagePushTemplate extends Base
{
    use SoftDelete;

    /**
     * 表名。
     *
     * @var string
     */
    protected $name = 'message_push_template';

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
    protected $json = ['field_map', 'jump_config', 'sync_config'];

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
        'enabled' => 'integer',
        'field_map' => 'array',
        'jump_config' => 'array',
        'sync_config' => 'array',
        'sync_status' => 'integer',
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
     * 搜索器：渠道。
     */
    public function searchChannelAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('channel', (string)$value);
        }
    }

    /**
     * 搜索器：启用状态。
     */
    public function searchEnabledAttr($query, mixed $value): void
    {
        if ($value !== '') {
            $query->where('enabled', (int)$value);
        }
    }
}
