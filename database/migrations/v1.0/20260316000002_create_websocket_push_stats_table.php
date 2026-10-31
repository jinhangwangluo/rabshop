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

use think\migration\Migrator;
use think\migration\db\Column;

/**
 * WebSocket推送统计表迁移
 * 
 * 用于记录所有 WebSocket推送的详细信息，包括：
 * - 推送成功率统计
 * - 推送延迟统计
 * - 消息类型分布
 * - 错误日志分析
 */
class CreateWebSocketPushStatsTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('websocket_push_stats', ['comment' => 'WebSocket推送统计表','engine' => 'InnoDB','charset' => 'utf8mb4','collation' => 'utf8mb4_unicode_ci']);
        
        $table->addColumn('push_id', 'string', ['limit' => 64,'default' => '','comment' => '推送 ID（唯一标识）'])
            ->addColumn('event_type', 'string', ['limit' => 50,'default' => '','comment' => '事件类型：level_upgrade/room_rank_update/chat_message等'])
            ->addColumn('target_type', 'string', [
                'limit' => 20,
                'default' => '',
                'comment' => '目标类型：room/user/client_type/broadcast'
            ])
            ->addColumn('target_id', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '目标 ID（房间 ID/用户 ID 等）'
            ])
            ->addColumn('message_data', 'text', [
                'comment' => '推送消息数据（JSON）'
            ])
            ->addColumn('recipient_count', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '接收者数量'
            ])
            ->addColumn('success_count', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '成功推送数量'
            ])
            ->addColumn('failed_count', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '失败推送数量'
            ])
            ->addColumn('success_rate', 'decimal', [
                'precision' => 5,
                'scale' => 2,
                'default' => 0.00,
                'comment' => '成功率（百分比：0-100）'
            ])
            ->addColumn('avg_latency_ms', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '平均延迟（毫秒）'
            ])
            ->addColumn('max_latency_ms', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '最大延迟（毫秒）'
            ])
            ->addColumn('min_latency_ms', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '最小延迟（毫秒）'
            ])
            ->addColumn('is_important', 'boolean', [
                'default' => false,
                'comment' => '是否重要消息（需要持久化）'
            ])
            ->addColumn('persisted', 'boolean', [
                'default' => false,
                'comment' => '是否已持久化到消息历史表'
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
                'comment' => '错误信息（失败时）'
            ])
            ->addColumn('push_time', 'datetime', ['null' => true,'comment' => '推送时间' ])
            ->addColumn('create_time', 'datetime', ['null' => true,'comment' => '创建时间'])
            ->addIndex(['push_id'], ['unique' => true])
            ->addIndex(['event_type'])
            ->addIndex(['target_type', 'target_id'])
            ->addIndex(['push_time'])
            ->addIndex(['create_time'])
            
            ->setPrimaryKey('id');
        
        $table->save();
        
        // 创建消息历史表（重要消息持久化）
        $historyTable = $this->table('websocket_message_history', [
            'comment' => 'WebSocket 消息历史记录表',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ]);
        
        $historyTable
            ->addColumn('push_id', 'string', [
                'limit' => 64,
                'default' => '',
                'comment' => '推送 ID（关联 stats 表）'
            ])
            ->addColumn('event_type', 'string', [
                'limit' => 50,
                'default' => '',
                'comment' => '事件类型'
            ])
            ->addColumn('user_id', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '接收用户 ID（0 表示群体消息）'
            ])
            ->addColumn('room_id', 'integer', [
                'limit' => 11,
                'default' => 0,
                'comment' => '直播间 ID（0 表示非房间消息）'
            ])
            ->addColumn('message_content', 'text', [
                'comment' => '消息内容（JSON）'
            ])
            ->addColumn('read_status', 'boolean', [
                'default' => false,
                'comment' => '阅读状态：0=未读，1=已读'
            ])
            ->addColumn('read_time', 'datetime', [
                'null' => true,
                'comment' => '阅读时间'
            ])
            ->addColumn('expire_time', 'datetime', [
                'null' => true,
                'comment' => '过期时间（null 表示永久有效）'
            ])
            ->addColumn('create_time', 'datetime', [
                'null' => true,
                'comment' => '创建时间'
            ])
            
            ->addIndex(['push_id'])
            ->addIndex(['user_id'])
            ->addIndex(['room_id'])
            ->addIndex(['read_status'])
            ->addIndex(['create_time'])
            
            ->setPrimaryKey('id');
        
        $historyTable->save();
    }
}
