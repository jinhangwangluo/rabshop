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

use app\common\service\finance\FinanceService;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;
use think\response\Json;

/**
 * 商户财务管理控制器
 * Class Finance
 * @package app\controller\merchant
 */
class Finance extends AuthController
{
    /**
     * @var FinanceService
     */
    #[Inject]
    protected FinanceService $service;

    /**
     * 财务概览统计
     * @return Response
     */
    public function overview(): Response
    {
        $data = $this->service->getMerchantFinanceStatistics($this->merchantId);
        return $this->success($data);
    }

    /**
     * 财务流水记录
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function records(): Response
    {
        $params = $this->request->getMore([
            ['type', ''],
            ['change_type', ''],
            ['fund_status', ''],
            ['start_time', ''],
            ['end_time', ''],
            ['page', 1],
            ['limit', 20],
        ]);

        $data = $this->service->getMerchantFinanceList($this->merchantId, $params);
        return $this->success($data);
    }

    /**
     * 提现申请
     * @return Response
     */
    public function withdraw(): Response
    {
        $data = $this->request->postMore([
            ['withdraw_type', 'bank'],
            ['amount', 0],
            ['bank_account', ''],
            ['bank_name', ''],
            ['account_name', ''],
            ['payment_qrcode', ''],
        ]);

        $result = $this->service->applyWithdraw($this->merchantId, $data);
        return $this->success('提现申请已提交，等待平台审核', $result);
    }

    /**
     * 提现记录
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function withdrawRecords(): Response
    {
        $params = $this->request->getMore([
            ['status', ''],
            ['withdraw_type', ''],
            ['start_time', ''],
            ['end_time', ''],
            ['page', 1],
            ['limit', 20],
        ]);

        $data = $this->service->getMerchantWithdrawList($this->merchantId, $params);
        return $this->success($data);
    }
}
