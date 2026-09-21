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

namespace app\controller\merchant;

use app\common\service\merchant\MerchantDashboardService;
use think\annotation\Inject;
use think\Response;

/**
 * 商户首页控制器。
 */
class Dashboard extends AuthController
{
    /**
     * @var MerchantDashboardService
     */
    #[Inject]
    protected MerchantDashboardService $service;

    /**
     * 首页聚合数据。
     */
    public function home(): Response
    {
        $params = $this->request->getMore([
            ['range', 7],
            ['start_time', ''],
            ['end_time', ''],
        ]);

        $data = $this->service->home((int)$this->merchantId, $params);

        return $this->success($data);
    }
}
