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

/**
 * 创建消息推送配置与日志表。
 */
class CreateMessagePushTables extends Migrator
{
    /**
     * 创建消息推送相关表。
     */
    public function change(): void
    {
        $template = $this->table('message_push_template', [
            'comment' => '消息推送模板配置表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $template->addColumn('event_key', 'string', [
            'limit' => 60,
            'default' => '',
            'comment' => '内置消息事件标识',
        ]);
        $template->addColumn('channel', 'string', [
            'limit' => 30,
            'default' => '',
            'comment' => '渠道：sms/official_account/miniapp',
        ]);
        $template->addColumn('enabled', 'boolean', [
            'limit' => 1,
            'default' => 0,
            'comment' => '是否启用',
        ]);
        $template->addColumn('content_template', 'text', [
            'null' => true,
            'default' => null,
            'comment' => '内容模板，支持 {{变量}}',
        ]);
        $template->addColumn('template_code', 'string', [
            'limit' => 128,
            'default' => '',
            'comment' => '短信模板Code/公众号短ID/小程序模板tid',
        ]);
        $template->addColumn('external_template_id', 'string', [
            'limit' => 160,
            'default' => '',
            'comment' => '外部平台真实模板ID',
        ]);
        $template->addColumn('field_map', 'text', [
            'null' => true,
            'default' => null,
            'comment' => '模板字段映射JSON',
        ]);
        $template->addColumn('jump_config', 'text', [
            'null' => true,
            'default' => null,
            'comment' => '跳转配置JSON',
        ]);
        $template->addColumn('sync_config', 'text', [
            'null' => true,
            'default' => null,
            'comment' => '同步配置JSON',
        ]);
        $template->addColumn('sync_status', 'integer', [
            'limit' => 1,
            'default' => 0,
            'comment' => '同步状态：0=未同步，1=成功，2=失败',
        ]);
        $template->addColumn('last_sync_time', 'timestamp', [
            'null' => true,
            'default' => null,
            'comment' => '最后同步时间',
        ]);
        $template->addColumn('last_sync_error', 'string', [
            'limit' => 500,
            'default' => '',
            'comment' => '最后同步错误',
        ]);
        $template->addColumn('sort', 'integer', [
            'limit' => 11,
            'default' => 0,
            'comment' => '排序',
        ]);
        $template->addTimestamps();
        $template->addSoftDelete();
        $template->addIndex(['event_key', 'channel'], ['name' => 'uniq_event_channel', 'unique' => true]);
        $template->addIndex(['channel', 'enabled'], ['name' => 'idx_channel_enabled']);
        $template->create();

        $log = $this->table('message_push_send_log', [
            'comment' => '消息推送发送日志表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $log->addColumn('event_key', 'string', [
            'limit' => 60,
            'default' => '',
            'comment' => '消息事件标识',
        ]);
        $log->addColumn('channel', 'string', [
            'limit' => 30,
            'default' => '',
            'comment' => '发送渠道',
        ]);
        $log->addColumn('template_id', 'integer', [
            'limit' => 11,
            'default' => 0,
            'comment' => '本地模板配置ID',
        ]);
        $log->addColumn('receiver_type', 'string', [
            'limit' => 30,
            'default' => '',
            'comment' => '接收人类型：user/admin/merchant',
        ]);
        $log->addColumn('receiver_id', 'integer', [
            'limit' => 11,
            'default' => 0,
            'comment' => '接收人ID',
        ]);
        $log->addColumn('receiver_account', 'string', [
            'limit' => 160,
            'default' => '',
            'comment' => '手机号或openid',
        ]);
        $log->addColumn('relation_type', 'string', [
            'limit' => 60,
            'default' => '',
            'comment' => '关联业务类型',
        ]);
        $log->addColumn('relation_id', 'integer', [
            'limit' => 11,
            'default' => 0,
            'comment' => '关联业务ID',
        ]);
        $log->addColumn('rendered_content', 'text', [
            'null' => true,
            'default' => null,
            'comment' => '渲染后的内容',
        ]);
        $log->addColumn('request_payload', 'text', [
            'null' => true,
            'default' => null,
            'comment' => '请求载荷JSON',
        ]);
        $log->addColumn('response_payload', 'text', [
            'null' => true,
            'default' => null,
            'comment' => '响应载荷JSON',
        ]);
        $log->addColumn('status', 'integer', [
            'limit' => 1,
            'default' => 0,
            'comment' => '状态：0=待发送，1=成功，2=失败，3=跳过',
        ]);
        $log->addColumn('error_message', 'string', [
            'limit' => 1000,
            'default' => '',
            'comment' => '错误信息',
        ]);
        $log->addColumn('send_time', 'timestamp', [
            'null' => true,
            'default' => null,
            'comment' => '发送时间',
        ]);
        $log->addTimestamps();
        $log->addSoftDelete();
        $log->addIndex(['event_key', 'channel'], ['name' => 'idx_event_channel']);
        $log->addIndex(['channel', 'status'], ['name' => 'idx_channel_status']);
        $log->addIndex(['receiver_type', 'receiver_id'], ['name' => 'idx_receiver']);
        $log->addIndex(['relation_type', 'relation_id'], ['name' => 'idx_relation']);
        $log->addIndex('create_time', ['name' => 'idx_create_time']);
        $log->create();

        $receiver = $this->table('message_push_receiver', [
            'comment' => '消息推送手动接收人表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $receiver->addColumn('event_key', 'string', [
            'limit' => 60,
            'default' => '',
            'comment' => '消息事件标识',
        ]);
        $receiver->addColumn('receiver_name', 'string', [
            'limit' => 80,
            'default' => '',
            'comment' => '接收人名称',
        ]);
        $receiver->addColumn('phone', 'string', [
            'limit' => 20,
            'default' => '',
            'comment' => '手机号',
        ]);
        $receiver->addColumn('official_openid', 'string', [
            'limit' => 160,
            'default' => '',
            'comment' => '公众号openid',
        ]);
        $receiver->addColumn('mini_openid', 'string', [
            'limit' => 160,
            'default' => '',
            'comment' => '小程序openid',
        ]);
        $receiver->addColumn('status', 'boolean', [
            'limit' => 1,
            'default' => 1,
            'comment' => '状态：0=禁用，1=启用',
        ]);
        $receiver->addColumn('sort', 'integer', [
            'limit' => 11,
            'default' => 0,
            'comment' => '排序',
        ]);
        $receiver->addColumn('remark', 'string', [
            'limit' => 500,
            'default' => '',
            'comment' => '备注',
        ]);
        $receiver->addTimestamps();
        $receiver->addSoftDelete();
        $receiver->addIndex(['event_key', 'status'], ['name' => 'idx_event_status']);
        $receiver->addIndex('sort', ['name' => 'idx_sort']);
        $receiver->create();
    }
}
