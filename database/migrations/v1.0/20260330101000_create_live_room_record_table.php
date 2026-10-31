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
 * 直播回放归档表
 */
class CreateLiveRoomRecordTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('live_room_record', ['comment' => '直播回放归档表', 'collation' => 'utf8mb4_general_ci']);

        $table->addColumn('room_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '直播间ID']);
        $table->addColumn('stream_name', 'string', ['limit' => 128, 'default' => '', 'comment' => '直播流名称（stream_name）']);
        $table->addColumn('start_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '录制开始时间']);
        $table->addColumn('end_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '录制结束时间']);
        $table->addColumn('cloud_provider', 'string', ['limit' => 20, 'default' => '', 'comment' => '云服务商：aliyun/qiniu']);
        $table->addColumn('record_id', 'string', ['limit' => 128, 'default' => '', 'comment' => '云端回放片段ID']);

        $table->addColumn('playback_url', 'string', ['limit' => 1024, 'default' => '', 'comment' => '回放播放地址（可直连/私有签名）']);

        $table->addColumn('record_bucket', 'string', ['limit' => 128, 'default' => '', 'comment' => '归档桶名']);
        $table->addColumn('record_file_key', 'string', ['limit' => 512, 'default' => '', 'comment' => '归档文件 Key']);

        $table->addTimestamps();
        $table->addSoftDelete();

        $table->addIndex(['room_id'], ['name' => 'idx_live_room_record_room_id']);
        $table->addIndex(['record_id'], ['name' => 'idx_live_room_record_record_id']);
        $table->addIndex(['room_id', 'record_id'], ['name' => 'idx_room_record_id']);

        $table->create();
    }
}

