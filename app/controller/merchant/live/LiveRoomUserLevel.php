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

namespace app\controller\merchant\live;

use app\common\service\live\LiveRoomService;
use app\common\service\live\LiveRoomUserLevelService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;

/**
 * 商户端直播间用户等级控制器
 */
class LiveRoomUserLevel extends AuthController
{
    #[Inject]
    protected LiveRoomUserLevelService $service;

    #[Inject]
    protected LiveRoomService $liveRoomService;

    /**
     * 查看指定用户在直播间的等级。
     *
     * @param int $roomId 直播间 ID
     * @param int $userId 用户 ID
     */
    public function getUserLevel(int $roomId, int $userId): mixed
    {
        $this->liveRoomService->checkMerchantOwner($this->merchantId, $roomId, '直播间');

        $data = $this->service->getUserLevel($userId, $roomId);
        if (!$data) {
            return $this->fail('该用户在此直播间暂无等级记录');
        }

        return $this->success('获取成功', $data);
    }
}
