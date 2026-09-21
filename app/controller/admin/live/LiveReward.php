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

use app\common\service\live\LiveGiftRecordService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 直播打赏记录控制器
 */
class LiveReward extends AuthController
{
    #[Inject]
    protected LiveGiftRecordService $service;

    /**
     * 获取打赏记录列表
     */
    public function index(): mixed
    {
        $params = [
            'live_room_id' => (int)$this->request->get('live_room_id', 0),
            'page' => (int)$this->request->get('page', 1),
            'limit' => (int)$this->request->get('limit', 20),
            'nickname' => trim((string)$this->request->get('nickname', '')),
            'gift_name' => trim((string)$this->request->get('gift_name', '')),
            'start_date' => trim((string)$this->request->get('start_date', '')),
            'end_date' => trim((string)$this->request->get('end_date', '')),
        ];

        return $this->success('获取成功', $this->service->getAdminRewardPage($params));
    }
}
