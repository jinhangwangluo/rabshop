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
 * 直播间表
 */
class CreateLiveRoomTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('live_room', ['comment' => '直播间表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID']);
        $table->addColumn('audit_status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '审核状态：0-待审核 1-审核通过 2-审核驳回']);
        $table->addColumn('audit_remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '审核备注/驳回原因']);
        $table->addColumn('violation_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '违规次数']);
        $table->addColumn('live_anchor_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '主播ID']);
        $table->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '直播间标题']);
        $table->addColumn('cover', 'string', ['limit' => 255, 'default' => '', 'comment' => '直播间封面']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '直播间描述']);
        $table->addColumn('start_time', 'time', ['comment' => '开始时间']);
        $table->addColumn('end_time', 'time', ['null' => true, 'comment' => '结束时间']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '状态：0-未开始，1-直播中，2-已结束']);
        $table->addColumn('view_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '观看人数']);
        $table->addColumn('like_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '点赞数']);
        $table->addColumn('keywords', 'string', ['limit'   => 200, 'default' => '', 'comment' => '关键词']);
        $table->addColumn('sort', 'integer', ['limit'   => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('is_hot', 'boolean', ['limit'   => 1, 'default' => 0, 'comment' => '是否热门：0-否，1-是']);
        $table->addColumn('stream_name', 'string', ['limit' => 128, 'default' => '', 'comment' => '直播流名称（全局唯一）']);
        $table->addColumn('cloud_provider', 'string', ['limit' => 20, 'default' => '', 'comment' => '当前生效云服务商：aliyun/qiniu']);
        $table->addColumn('ali_push_url', 'string', ['limit' => 512, 'default' => '', 'comment' => '阿里云推流地址']);
        $table->addColumn('ali_pull_url', 'string', ['limit' => 512, 'default' => '', 'comment' => '阿里云拉流地址']);
        $table->addColumn('qiniu_push_url', 'string', ['limit' => 512, 'default' => '', 'comment' => '七牛推流地址']);
        $table->addColumn('qiniu_pull_url', 'string', ['limit' => 512, 'default' => '', 'comment' => '七牛拉流地址']);
        $table->addColumn('stream_created_at', 'timestamp', ['null' => true, 'default' => null, 'comment' => '推流地址生成时间']);
        $table->addColumn('ali_push_base', 'string', ['limit'   => 512, 'default' => '', 'comment' => '阿里云推流基础地址（不含鉴权参数）']);
        $table->addColumn('ali_push_key', 'string', ['limit'   => 512, 'default' => '', 'comment' => '阿里云推流鉴权串（auth_key=...）']);
        $table->addColumn('qiniu_push_base', 'string', ['limit'   => 512, 'default' => '', 'comment' => '七牛云推流基础地址（不含鉴权参数）']);
        $table->addColumn('qiniu_push_key', 'string', ['limit'   => 512, 'default' => '', 'comment' => '七牛云推流鉴权串（e=...&token=...）']);
        $table->addColumn('is_playback_auth', 'boolean', ['limit' => 1,'default' => 0, 'comment' => '是否启用播流鉴权：0-否，1-是',]);
        $table->addColumn('allow_landscape', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否允许移动端横屏：0-否，1-是']);
        $table->addColumn('stream_type', 'string', ['limit' => 20, 'default' => 'obs', 'comment' => '直播类型：obs-OBS直播，external-外链直播']);
        $table->addColumn('external_play_url', 'string', ['limit' => 1024, 'default' => '', 'comment' => '外链直播播放地址']);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播分类ID']);
        $table->addColumn('notice', 'text', ['null' => true, 'comment' => '直播说明', 'after' => 'description']);
        $table->addColumn('welcome_msg', 'string', ['limit' => 255, 'default' => '', 'comment' => '进场欢迎语']);
        $table->addColumn('tags', 'string', ['limit' => 255, 'default' => '', 'comment' => '直播标签（逗号分隔）']);
        $table->addColumn('banner', 'string', ['limit' => 512, 'default' => '', 'comment' => '直播Banner图']);
        $table->addColumn('poster', 'string', ['limit' => 512, 'default' => '', 'comment' => '直播海报图']);
        $table->addColumn('intro', 'text', ['null' => true, 'comment' => '直播简介（富文本）']);
        $table->addColumn('is_record', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否录制：0-否，1-是']);
        $table->addTimestamps();
        $table->addIndex('id');
        $table->addIndex('category_id');
        $table->addIndex('stream_name');
        $table->addIndex('merchant_id');
        $table->addIndex('live_anchor_id');
        $table->addIndex('status');
        $table->create();
    }
}
