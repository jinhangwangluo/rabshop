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

use app\common\service\live\LiveGiftService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;

/**
 * 商户端直播打赏/订单统计控制器（只读）
 */
class LiveReward extends AuthController
{
    #[Inject]
    protected LiveGiftService $giftService;

    /**
     * 获取打赏/统计记录列表
     */
    public function index(): mixed
    {
        $liveRoomId = (int)$this->request->get('live_room_id', 0);
        $page       = (int)$this->request->get('page', 1);
        $limit      = (int)$this->request->get('limit', 20);

        $query = $this->giftService->getModel()->where('status', 1);
        $total = $query->count();
        $list  = $this->giftService->getModel()
            ->where('status', 1)
            ->order('sort desc, id asc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->success('获取成功', [
            'list'         => $list,
            'total'        => $total,
            'live_room_id' => $liveRoomId,
        ]);
    }
}
