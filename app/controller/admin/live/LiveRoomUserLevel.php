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

use app\common\service\live\LiveRoomUserLevelService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 用户直播间等级管理控制器
 */
class LiveRoomUserLevel extends AuthController
{
    #[Inject]
    protected LiveRoomUserLevelService $service;

    /**
     * 获取直播间贡献排行榜
     * @param int $id 直播间ID
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomRank(int $id): mixed
    {
        $page = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 10);

        $data = $this->service->getRoomRankList($id, $page, $limit);
        return $this->success('获取成功', $data);
    }

    /**
     * 查看指定用户在直播间的等级
     * @param int $roomId 直播间 ID
     * @param int $userId 用户 ID
     */
    public function getUserLevel(int $roomId, int $userId): mixed
    {
        $data = $this->service->getUserLevel($userId, $roomId);
        if (!$data) {
            return $this->fail('该用户在此直播间暂无等级记录');
        }
        return $this->success('获取成功', $data);
    }
}
