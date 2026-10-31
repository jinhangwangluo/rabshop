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

namespace app\controller\mobile\order;

use app\BaseController;
use app\common\service\order\OrderService;
use app\common\service\order\OrderRefundService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端订单退款控制器。
 */
class OrderRefund extends BaseController
{
    /**
     * 订单退款记录服务。
     *
     * @var OrderRefundService
     */
    #[Inject]
    protected OrderRefundService $orderRefundService;

    /**
     * 订单业务服务。
     *
     * @var OrderService
     */
    #[Inject]
    protected OrderService $orderService;

    /**
     * 获取当前用户退款列表。
     *
     * GET 参数：
     * - page int 页码。
     * - limit int 每页数量。
     * - order_sn string 订单号筛选；兼容 keyword。
     * - status string 退款筛选：pending 待处理；refunded 已退款；record 申请记录。
     *
     * @return Response 返回退款分页列表。
     */
    public function index(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->get('order_sn', ''));
        $keyword = trim((string)$this->request->get('keyword', ''));
        $status = trim((string)$this->request->get('status', 'record'));

        $data = $this->orderRefundService->getMobileRefundList($userId, [
            'order_sn' => $orderSn ?: $keyword,
            'status' => $status,
        ]);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取当前用户退款详情。
     *
     * GET 参数：
     * - refund_sn string 退款单号。
     * - order_sn string 订单号；未传 refund_sn 时使用。
     * - order_id int 订单 ID；未传 refund_sn/order_sn 时使用。
     *
     * @return Response 返回退款详情。
     */
    public function detail(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $refundSn = trim((string)$this->request->get('refund_sn', ''));
        $orderSn = trim((string)$this->request->get('order_sn', ''));
        $orderId = (int)$this->request->get('order_id/d', 0);

        if ($refundSn === '' && $orderSn === '' && $orderId <= 0) {
            return $this->fail('请传入退款单号或订单号');
        }

        $data = $this->orderRefundService->getMobileRefundDetail($userId, [
            'refund_sn' => $refundSn,
            'order_sn' => $orderSn,
            'order_id' => $orderId,
        ]);

        return $this->success('获取成功', $data);
    }

    /**
     * 申请订单退款，进入审核流程。
     *
     * POST 参数：
     * - order_sn string 订单号。
     * - reason string 退款原因。
     * - items array 退款商品明细；不传时按整单退款。
     *
     * @return Response 返回退款申请结果。
     */
    public function apply(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        $reason = trim((string)$this->request->post('reason', ''));
        $items = $this->request->post('items/a', []);
        if (!is_array($items)) {
            $items = [];
        }

        if ($orderSn === '') {
            return $this->fail('订单号不能为空');
        }

        $data = $this->orderService->userApplyRefund($userId, $orderSn, $reason, $items);

        return $this->success('退款申请成功', $data);
    }

    /**
     * 取消当前用户退款申请。
     *
     * POST 参数：
     * - order_sn string 订单号。
     * - refund_sn string 退款单号；未传 order_sn 时使用。
     *
     * @return Response 返回取消后的退款详情。
     */
    public function cancel(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $orderSn = trim((string)$this->request->post('order_sn', ''));
        $refundSn = trim((string)$this->request->post('refund_sn', ''));
        if ($orderSn === '' && $refundSn === '') {
            return $this->fail('订单号不能为空');
        }

        $data = $this->orderRefundService->cancelMobileRefund($userId, $orderSn, $refundSn);

        return $this->success('取消退款申请成功', $data);
    }
}
