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

use app\common\service\live\LiveRoomService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;

/**
 * 商户端直播间推流配置控制器
 */
class LiveStreamConfig extends AuthController
{
    #[Inject]
    protected LiveRoomService $service;

    /**
     * 获取推拉流地址信息。
     */
    public function streamInfo(int $id): mixed
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '直播间');

        $data = $this->service->getStreamInfo($id);
        return $this->success('获取成功', $data);
    }

    /**
     * 生成/刷新推流地址。
     */
    public function createStream(int $id): mixed
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '直播间');

        $force = (bool)$this->request->post('force', false);
        $data  = $this->service->createStream($id, $force);
        return $this->success('推流地址生成成功', $data);
    }

    /**
     * 关闭推流（同步结束直播间）。
     */
    public function closeStream(int $id): mixed
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '直播间');

        $result = $this->service->switchStatus($id, LiveRoomService::STATUS_ENDED);
        return $this->success($result['msg'] ?? '操作成功', $result);
    }
}
