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

namespace app\controller\merchant\live;

use app\common\service\live\LiveRoomRecordService;
use app\common\service\live\LiveRoomService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端直播回放归档控制器
 */
class LiveRoomRecord extends AuthController
{
    #[Inject]
    protected LiveRoomRecordService $service;

    #[Inject]
    protected LiveRoomService $liveRoomService;

    /**
     * 获取直播间回放列表。
     *
     * @param int $roomId 直播间 ID
     */
    public function records(int $roomId): Response
    {
        $this->liveRoomService->checkMerchantOwner($this->merchantId, $roomId, '直播间');

        $data = $this->service->getRoomRecords($roomId);
        return $this->success('获取成功', $data);
    }

    /**
     * 删除回放。
     *
     * @param int $roomId 直播间 ID
     * @param int $recordId 回放 ID
     */
    public function deleteRecord(int $roomId, int $recordId): Response
    {
        $this->liveRoomService->checkMerchantOwner($this->merchantId, $roomId, '直播间');

        $data = $this->service->deleteRoomRecord($roomId, $recordId);
        return $this->success('删除成功', $data);
    }

    /**
     * 设置某条回放为当前播放。
     *
     * @param int $roomId 直播间 ID
     * @param int $recordId 回放 ID
     */
    public function setPlayback(int $roomId, int $recordId): Response
    {
        $this->liveRoomService->checkMerchantOwner($this->merchantId, $roomId, '直播间');

        $data       = $this->service->setPlaybackRecord($roomId, $recordId);
        $streamInfo = $this->liveRoomService->getStreamInfo($roomId);

        return $this->success('设置播放回放成功', [
            'result'      => $data,
            'stream_info' => $streamInfo,
        ]);
    }
}
