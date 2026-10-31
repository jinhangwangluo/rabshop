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

namespace app\controller\merchant\order;

use app\common\service\order\OrderService;
use app\common\service\setting\ExpressCompanyService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 管理当前认证商户的订单、发货与退款操作，归属和交易规则由订单服务校验。
 *
 * @package app\controller\merchant
 */
class Order extends AuthController
{
    /**
     * 当前商户订单管理及交易归属校验服务。
     *
     * @var OrderService
     */
    #[Inject]
    protected OrderService $service;

    /**
     * 按筛选条件分页查询当前商户订单。
     *
     * @return Response 包含当前商户订单列表与分页信息的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(): Response
    {
        $data = $this->service->getMerchantOrderList($this->merchantId, $this->getSearchParams());

        return $this->success($data);
    }

    /**
     * 按筛选条件分页查询当前商户退款记录。
     *
     * @return Response 包含当前商户退款列表与分页信息的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function refundList(): Response
    {
        $data = $this->service->getMerchantRefundList($this->merchantId, $this->getSearchParams());

        return $this->success($data);
    }

    /**
     * 读取当前商户可查看的订单详情。
     *
     * @param int $id 当前商户订单 ID。
     * @return Response 包含订单详情的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function detail(int $id): Response
    {
        $info = $this->service->getMerchantOrderDetail($this->merchantId, $id);

        return $this->success($info);
    }

    /**
     * 读取当前商户订单的操作与状态记录。
     *
     * @param int $id 当前商户订单 ID。
     * @return Response 包含订单记录列表的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function record(int $id): Response
    {
        $list = $this->service->getMerchantOrderRecord($this->merchantId, $id);

        return $this->success(['list' => $list]);
    }

    /**
     * 读取物流公司与单号字段，提交当前商户订单发货。
     *
     * @param int $id 待发货的当前商户订单 ID。
     * @return Response 发货结果响应。
     */
    public function delivery(int $id): Response
    {
        $data = $this->request->postMore([
            ['express_name', ''],
            ['express_sn', ''],
            ['express_code', ''],
        ]);

        $this->service->delivery($this->merchantId, $id, $data);

        return $this->success('发货成功');
    }

    /**
     * 按关键词查询发货可选择的快递公司。
     *
     * @return Response 包含快递公司选项的响应。
     */
    public function expressOptions(): Response
    {
        $params = $this->request->getMore([
            ['keyword', ''],
        ]);

        return $this->success('获取成功', app()->make(ExpressCompanyService::class)->options($params));
    }

    /**
     * 确认当前商户退货实际收货累计数量，由库存服务幂等回补真实库存。
     *
     * @param int $id 待确认退货收货的当前商户订单 ID。
     * @return Response 包含累计收货及库存回补结果的响应。
     */
    public function receiveReturn(int $id): Response
    {
        $lines = $this->request->post('items/a', []);
        return $this->success($this->service->receiveReturnedInventory($id, $lines, $this->merchantId));
    }

    /**
     * 读取订单、原因、金额和商品项，提交商户发起退款并等待平台审核。
     *
     * @return Response 退款申请受理结果响应。
     */
    public function refundApply(): Response
    {
        $data = $this->request->postMore([
            ['order_id', 0],
            ['refund_reason', ''],
            ['refund_amount', 0],
        ]);
        $items = $this->request->post('items/a', []);
        $data['items'] = is_array($items) ? $items : [];

        $this->service->refundApply($this->merchantId, (int) $data['order_id'], $data);

        return $this->success('退款申请已提交，等待平台审核');
    }

    /**
     * 对当前商户订单的退款申请执行审核通过。
     *
     * @param int $id 待审核退款的当前商户订单 ID。
     * @return Response 退款审核通过结果响应。
     */
    public function refundApprove(int $id): Response
    {
        $this->service->merchantApproveRefund($this->merchantId, $id);

        return $this->success('退款已通过');
    }

    /**
     * 读取拒绝原因并拒绝当前商户订单的退款申请。
     *
     * @param int $id 待拒绝退款的当前商户订单 ID。
     * @return Response 退款拒绝结果响应。
     */
    public function refundReject(int $id): Response
    {
        $reason = trim((string)$this->request->post('reason', ''));

        $this->service->merchantRejectRefund($this->merchantId, $id, $reason);

        return $this->success('已拒绝退款申请');
    }

    /**
     * 按当前筛选条件读取商户订单统计。
     *
     * @return Response 包含当前商户订单统计的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function statistics(): Response
    {
        $data = $this->service->getMerchantOrderStatistics($this->merchantId, $this->getSearchParams());

        return $this->success($data);
    }

    /**
     * 按当前筛选条件读取商户退款统计。
     *
     * @return Response 包含当前商户退款统计的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function refundStatistics(): Response
    {
        $data = $this->service->getMerchantRefundStatistics($this->merchantId, $this->getSearchParams());

        return $this->success($data);
    }

    /**
     * 统一读取订单与退款查询允许的筛选和分页字段。
     *
     * @return array 订单、用户、商品、状态和时间筛选参数。
     */
    protected function getSearchParams(): array
    {
        return $this->request->getMore([
            ['order_sn', ''],
            ['user_keyword', ''],
            ['goods_name', ''],
            ['order_type', ''],
            ['status', ''],
            ['pay_type', ''],
            ['refund_status', ''],
            ['start_time', ''],
            ['end_time', ''],
            ['page', 1],
            ['limit', 20],
        ]);
    }
}
