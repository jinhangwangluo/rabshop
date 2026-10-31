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
 * 总平台首页统计控制器。
 */
class Dashboard extends AuthController
{
    /**
     * 总平台首页统计聚合服务。
     */
    #[Inject]
    protected AdminDashboardService $service;

    /**
     * 首页统计概览。
     * @return Response
     */
    public function overview(): Response
    {
        $params = $this->request->getMore([
            ['range', '30day'],
            ['start_time', ''],
            ['end_time', ''],
        ]);

        return $this->success('获取成功', $this->service->overview($params));
    }

    /**
     * 首页实时预警摘要。
     * @return Response
     */
    public function pending(): Response
    {
        return $this->success('获取成功', $this->service->pending());
    }
}
