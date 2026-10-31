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

namespace app\controller\admin\live;

use app\common\service\live\LiveRoomRecordService;
use app\common\service\live\LiveRoomService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 直播回放归档控制器
 */
class LiveRoomRecord extends AuthController
{
    #[Inject]
    protected LiveRoomRecordService $service;

    #[Inject]
    protected LiveRoomService $liveRoomService;

    /**
     * 获取直播间回放列表
     */
    public function records($roomId)
    {
        $data = $this->service->getRoomRecords((int)$roomId);
        return $this->success('获取成功', $data);
    }

    /**
     * 删除回放（逻辑删除：DB SoftDelete；云端物理删除）
     */
    public function deleteRecord(int $roomId, int $recordId): mixed
    {
        $data = $this->service->deleteRoomRecord($roomId, $recordId);
        return $this->success('删除成功', $data);
    }

    /**
     * 设置某条回放为当前播放
     */
    public function setPlayback(int $roomId, int $recordId): mixed
    {
        $data = $this->service->setPlaybackRecord($roomId, $recordId);
        $streamInfo = $this->liveRoomService->getStreamInfo($roomId);
        return $this->success('设置播放回放成功', [
            'result' => $data,
            'stream_info' => $streamInfo,
        ]);
    }
}

