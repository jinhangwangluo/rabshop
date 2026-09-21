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
 * WebSocket消息历史记录模型
 */
class WebSocketMessageHistory extends Model
{
    /**
     * 表名
     */
    protected $table = 'websocket_message_history';
    
    /**
     * 字段类型转换
     */
    protected $type = [
        'id'                => 'integer',
        'push_id'           => 'string',
        'event_type'        => 'string',
        'user_id'           => 'integer',
        'room_id'           => 'integer',
        'message_content'   => 'string',
        'read_status'       => 'boolean',
        'read_time'         => 'datetime',
        'expire_time'       => 'datetime',
        'create_time'       => 'datetime',
    ];
    
    /**
     * 自动时间戳
     */
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;
    
    /**
     * 关联推送统计
     */
    public function stats()
    {
        return $this->hasOne(WebSocketPushStats::class, 'push_id', 'push_id');
    }
}
