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

use app\common\service\merchant\MerchantService;
use think\annotation\Inject;
use think\Response;
use think\response\Json;

/**
 * 商户数据统计控制器
 * Class Statistics
 * @package app\controller\merchant
 */
class Statistics extends AuthController
{
    /**
     * @var MerchantService
     */
    #[Inject]
    protected MerchantService $service;

    /**
     * 数据概览
     * @return Response
     */
    public function overview(): Response
    {
        $statistics = $this->service->getMerchantStatistics($this->merchantId);

        $data = [
            'total_sales'    => $statistics['total_sales'] ?? 0,
            'total_orders'   => $statistics['total_orders'] ?? 0,
            'follow_count'   => $statistics['follow_count'] ?? 0,
            'today_sales'    => $statistics['today_sales'] ?? 0,
            'today_orders'   => $statistics['today_orders'] ?? 0,
            'pending_orders' => $statistics['pending_orders'] ?? 0,
            'live_count'     => $statistics['live_count'] ?? 0,
        ];

        return $this->success($data);
    }

    /**
     * 销售统计
     * @return Response
     */
    public function sales(): Response
    {
        $params = $this->request->getMore([
            ['start_time', ''],
            ['end_time', ''],
            ['type', 'day'], // day/week/month
        ]);

        $data = $this->service->getMerchantSalesStatistics($this->merchantId, $params);
        return $this->success($data);
    }

    /**
     * 订单统计
     * @return Response
     */
    public function orders(): Response
    {
        $params = $this->request->getMore([
            ['start_time', ''],
            ['end_time', ''],
        ]);

        $data = $this->service->getMerchantOrderStatistics($this->merchantId, $params);
        return $this->success($data);
    }

    /**
     * 直播统计
     * @return Response
     */
    public function live(): Response
    {
        $params = $this->request->getMore([
            ['start_time', ''],
            ['end_time', ''],
        ]);

        $data = $this->service->getMerchantLiveStatistics($this->merchantId, $params);
        return $this->success($data);
    }

    /**
     * 内容统计
     * @return Response
     */
    public function content(): Response
    {
        $data = $this->service->getMerchantContentStatistics($this->merchantId);
        return $this->success($data);
    }
}
