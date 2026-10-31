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
use app\common\service\live\LiveRoomUserLevelService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;

/**
 * 商户端直播间贡献榜控制器（只读）
 */
class LiveRoomRank extends AuthController
{
    #[Inject]
    protected LiveRoomUserLevelService $service;

    #[Inject]
    protected LiveRoomService $liveRoomService;

    /**
     * 获取直播间贡献排行榜。
     */
    public function getRoomRank(int $id): mixed
    {
        $this->liveRoomService->checkMerchantOwner($this->merchantId, $id, '直播间');

        $page  = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 10);
        $data  = $this->service->getRoomRankList($id, $page, $limit);
        return $this->success('获取成功', $data);
    }
}
