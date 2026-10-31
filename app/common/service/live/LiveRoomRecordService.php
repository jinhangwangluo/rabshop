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

namespace app\common\service\live;

use app\common\model\live\LiveRoom;
use app\common\model\live\LiveRoomRecord;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\exception\AdminResponseException;
use Rabbit\service\live\LiveService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 直播回放归档服务
 */
class LiveRoomRecordService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return LiveRoomRecord::class;
    }

    /**
     * 判断指定直播间的云端回放片段是否已归档。
     */
    public function hasCloudRecord(int $roomId, string $recordId): bool
    {
        if ($roomId <= 0 || $recordId === '') {
            return false;
        }

        return $this->getModel()
            ->where('room_id', $roomId)
            ->where('record_id', $recordId)
            ->find() !== null;
    }

    /**
     * 创建直播回放归档记录。
     */
    public function createArchiveRecord(array $data)
    {
        return $this->create($data);
    }

    /**
     * 获取某直播间回放列表
     *
     * @param int $roomId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomRecords(int $roomId): array
    {
        [$page, $limit] = $this->getPageValue();

        return $this->getModel()
            ->where('room_id', $roomId)
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
    }

    /**
     * 删除某条回放归档
     *
     * 云端删除 + 数据库逻辑删除（SoftDelete）
     *
     * @param int $roomId
     * @param int $recordId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteRoomRecord(int $roomId, int $recordId): array
    {
        $record = $this->getModel()
            ->where('room_id', $roomId)
            ->where('id', $recordId)
            ->find();

        if (!$record) {
            throw new AdminResponseException('回放记录不存在');
        }

        $cloudProvider = (string)($record->cloud_provider ?? '');
        $bucket = (string)($record->record_bucket ?? '');
        $fileKey = (string)($record->record_file_key ?? '');

        if ($bucket === '' || $fileKey === '') {
            // DB 里缺少归档定位信息，仍然允许逻辑删除
            $record->delete();
            return ['deleted' => true, 'warn' => '归档定位信息缺失，已跳过云端删除'];
        }

        // 删除云端文件
        try {
            app()->make(LiveService::class)->deleteRecord($bucket, $fileKey, $cloudProvider ?: null);
        } catch (\Throwable $e) {
            // 云端删除失败也不阻断软删除
            return ['deleted' => false, 'warn' => '云端删除失败：' . $e->getMessage()];
        }

        $record->delete();
        return ['deleted' => true];
    }

    /**
     * 设置某条回放为当前播放
     *
     * @param int $roomId
     * @param int $recordId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function setPlaybackRecord(int $roomId, int $recordId): array
    {
        $record = $this->getModel()
            ->where('room_id', $roomId)
            ->where('id', $recordId)
            ->find();

        if (!$record) {
            throw new AdminResponseException('回放记录不存在');
        }

        $room = app()->make(LiveRoom::class)->find($roomId);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }

        $provider = (string)($record->cloud_provider ?? '');
        $playUrl = (string)($record->playback_url ?? '');

        if ($playUrl === '') {
            throw new AdminResponseException('该回放播放地址为空');
        }

        $room->status = 1;
        $room->stream_type = LiveRoomService::STREAM_TYPE_OBS;
        $room->external_play_url = '';
        $room->cloud_provider = $provider;
        $room->start_time = $record->start_time;
        $room->end_time = $record->end_time;

        if ($provider === 'qiniu') {
            $room->qiniu_pull_url = $playUrl;
            $room->ali_pull_url = '';
        } else {
            $room->ali_pull_url = $playUrl;
            $room->qiniu_pull_url = '';
        }

        $room->save();

        return ['room_id' => $roomId, 'record_id' => $recordId, 'status' => $room->status];
    }
}
