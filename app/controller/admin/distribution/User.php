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

namespace app\controller\admin\distribution;

use app\common\service\distribution\DistributionUserService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 后台分销员管理控制器。
 */
class User extends AuthController
{
    /**
     * 分销员服务。
     */
    #[Inject]
    protected DistributionUserService $service;

    /**
     * 分销员列表。
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['page', 1],
            ['limit', 15],
            ['keyword', ''],
            ['nickname', ''],
            ['phone', ''],
            ['uid', 0],
            ['status', ''],
            ['qualification', ''],
        ]);

        return $this->success($this->service->getList($params));
    }

    /**
     * 增加或取消分销资格。
     */
    public function setPermission(): Response
    {
        $data = $this->request->postMore([
            ['user_id', 0],
            ['distribution_permission', 0],
        ]);

        $result = $this->service->setPermission(
            (int)$data['user_id'],
            (int)$data['distribution_permission']
        );
        $message = (int)$data['distribution_permission'] === 1 ? '分销资格已增加' : '分销资格已取消';

        return $this->success($message, $result);
    }

    /**
     * 分销订单明细。
     */
    public function orders(): Response
    {
        $params = $this->request->getMore([
            ['user_id', 0],
            ['level', 1],
            ['page', 1],
            ['limit', 10],
            ['order_sn', ''],
            ['buyer_keyword', ''],
            ['start_time', ''],
            ['end_time', ''],
        ]);

        return $this->success($this->service->getCommissionOrders((int)$params['user_id'], $params));
    }
}
