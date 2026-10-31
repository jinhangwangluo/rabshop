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

namespace app\controller\admin;

use app\common\service\statistics\AdminDashboardService;
use think\annotation\Inject;
use think\Response;

/**
 * 首页控制器
 * Class Index
 * @package app\admin\controller
 *
 */
class Index extends AuthController
{
    #[Inject]
    protected AdminDashboardService $service;

    public function index(): void
    {
    }

    /**
     * 获取首页统计数据。
     */
    public function statistics(): Response
    {
        $params = $this->request->getMore([
            ['range', '30day'],
            ['start_time', ''],
            ['end_time', ''],
        ]);

        return $this->success('获取成功', $this->service->overview($params));
    }

    /**
     * 获取首页交易趋势。
     */
    public function turnover(): Response
    {
        $params = $this->request->getMore([
            ['range', '30day'],
            ['start_time', ''],
            ['end_time', ''],
        ]);
        $overview = $this->service->overview($params);

        return $this->success('获取成功', $overview['trend'] ?? []);
    }
}

