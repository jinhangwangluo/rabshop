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

namespace app\common\model\websocket;

use think\Model;

/**
 * WebSocket推送统计模型
 */
class WebSocketPushStats extends Model
{
    /**
     * 表名
     */
    protected $table = 'websocket_push_stats';
    
    /**
     * 字段类型转换
     */
    protected $type = [
        'id'              => 'integer',
        'push_id'         => 'string',
        'event_type'      => 'string',
        'target_type'     => 'string',
        'target_id'       => 'integer',
        'message_data'    => 'string',
        'recipient_count' => 'integer',
        'success_count'   => 'integer',
        'failed_count'    => 'integer',
        'success_rate'    => 'decimal:2',
        'avg_latency_ms'  => 'integer',
        'max_latency_ms'  => 'integer',
        'min_latency_ms'  => 'integer',
        'is_important'    => 'boolean',
        'persisted'       => 'boolean',
        'error_message'   => 'string',
        'push_time'       => 'datetime',
        'create_time'     => 'datetime',
    ];
    
    /**
     * 自动时间戳
     */
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;
}
