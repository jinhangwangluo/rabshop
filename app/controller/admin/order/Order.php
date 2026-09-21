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

namespace app\controller\admin\order;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\controller\admin\AuthController;
use app\common\service\order\OrderService;
use think\annotation\Inject;

/**
 * 管理平台订单查询、状态、退款审核与发货，实际交易规则由订单服务执行。
 */
class Order extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 订单服务
     */
    #[Inject]
    protected OrderService $service;

    /**
     * 指定资源化订单状态更新使用的数据库字段。
     *
     * @return string 订单状态字段名。
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 指定资源化订单保存请求的验证器。
     *
     * @return string|null 订单验证器完整类名。
     */
    protected function getValidateClassName(): string|null
    {
        return 'app\common\validate\OrderValidate';
    }

    /**
     * 声明订单列表允许的订单、用户、商户及时间筛选字段。
     *
     * @return array 列表筛选字段和默认值配置。
     */
    protected function getIndexFields(): array
    {
        return [
            ['order_sn', ''],
            ['user_id', ''],
            ['merchant_id', ''],
            ['user_keyword', ''],
            ['goods_name', ''],
            ['order_type', ''],
            ['status', ''],
            ['pay_type', ''],
            ['refund_status', ''],
            ['start_time', ''],
            ['end_time', ''],
            ['create_time', ''],
        ];
    }

    /**
     * 声明资源化新增编辑订单时允许接收的字段。
     *
     * @return array 订单请求字段及默认值白名单。
     */
    protected function getRequestFields(): array
    {
        return [
            ['order_sn', ''],
            ['user_id', 0],
            ['merchant_id', 0],
            ['total_amount', 0],
            ['pay_amount', 0],
            ['pay_type', ''],
            ['status', 0],
            ['remark', ''],
        ];
    }

    /**
     * 声明订单表单构建所需的前置字段。
     *
     * @return array 当前不需要前置字段，返回空数组。
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 确认退回促销商品实际收货的累计数量，由库存服务幂等回补真实库存。
     *
     * @param int $id 待确认退货收货的订单 ID。
     * @return \think\Response 包含累计收货与库存回补结果的响应。
     */
    public function receiveReturn(int $id): \think\Response
    {
        $lines = $this->request->post('items/a', []);
        return $this->success($this->service->receiveReturnedInventory($id, $lines));
    }

    /**
     * 读取订单 ID 和目标状态，并调用订单服务校验状态转换。
     *
     * @return \think\Response 状态更新成功、失败或参数错误的响应。
     */
    public function updateStatus()
    {
        $id = $this->request->param('id/d');
        $status = $this->request->param('status/d');

        if (empty($id)) {
            return $this->fail('参数错误');
        }

        $result = $this->service->updateStatus($id, $status);
        if ($result) {
            return $this->success('更新成功');
        }

        return $this->fail('更新失败');
    }

    /**
     * 统计排除拆单主单后的订单数、支付数、退款中数及已支付金额。
     *
     * @return \think\Response 包含订单徽章统计字段的响应。
     */
    public function getBadge(): \think\Response
    {
        $model = app()->make(\app\common\model\order\Order::class);

        $total = $model->where('order_role', '<>', \app\common\model\order\Order::ROLE_MAIN)->count();
        $paidCount = $model->where('order_role', '<>', \app\common\model\order\Order::ROLE_MAIN)
            ->where('status', \app\common\model\order\Order::STATUS_PAID)
            ->count();
        $refundingCount = $model->where('order_role', '<>', \app\common\model\order\Order::ROLE_MAIN)
            ->where('status', \app\common\model\order\Order::STATUS_REFUNDING)
            ->count();
        $paidAmount = (float)$model->where('order_role', '<>', \app\common\model\order\Order::ROLE_MAIN)
            ->where('status', \app\common\model\order\Order::STATUS_PAID)
            ->sum('pay_amount');

        return $this->success([
            'total'          => $total,
            'paidCount'      => $paidCount,
            'refundingCount' => $refundingCount,
            'paidAmount'     => $paidAmount,
        ]);
    }

    /**
     * 按订单 ID 提交平台退款审核通过。
     *
     * @return \think\Response 退款审核通过或参数错误的响应。
     */
    public function approveRefund()
    {
        $id = $this->request->param('id/d');

        if (empty($id)) {
            return $this->fail('参数错误');
        }

        $this->service->adminApproveRefund($id);
        return $this->success('退款审核通过');
    }

    /**
     * 读取订单 ID 和拒绝原因，提交平台退款拒绝处理。
     *
     * @return \think\Response 退款拒绝结果或参数错误的响应。
     */
    public function rejectRefund()
    {
        $id = $this->request->param('id/d');
        $reason = $this->request->param('reason', '');

        if (empty($id)) {
            return $this->fail('参数错误');
        }

        $this->service->adminRejectRefund($id, $reason);
        return $this->success('已拒绝退款申请');
    }

    /**
     * 读取订单 ID、退款金额、原因及商品项，提交平台发起退款。
     *
     * @return \think\Response 包含退款申请结果或参数错误的响应。
     */
    public function applyRefund()
    {
        $id = $this->request->param('id/d');
        $refundAmount = (float)$this->request->param('refund_amount/f', 0);
        $refundReason = trim((string)$this->request->param('refund_reason', ''));
        $items = $this->request->param('items/a', []);
        if (!is_array($items)) {
            $items = [];
        }

        if (empty($id)) {
            return $this->fail('参数错误');
        }

        $data = $this->service->adminApplyRefund($id, $refundAmount, $refundReason, $items);
        return $this->success('退款申请已提交', $data);
    }

    /**
     * 读取订单 ID 和物流公司、单号字段，提交平台订单发货。
     *
     * @return \think\Response 发货成功或参数错误的响应。
     */
    public function delivery()
    {
        $id = $this->request->param('id/d');
        if (empty($id)) {
            return $this->fail('参数错误');
        }

        $this->service->adminDelivery($id, [
            'express_name' => trim((string)$this->request->param('express_name', '')),
            'express_sn' => trim((string)$this->request->param('express_sn', '')),
            'express_code' => trim((string)$this->request->param('express_code', '')),
        ]);

        return $this->success('发货成功');
    }

    /**
     * 按订单 ID 查询平台可查看的物流轨迹。
     *
     * @return \think\Response 包含物流轨迹或参数错误的响应。
     */
    public function express(): \think\Response
    {
        $id = $this->request->param('id/d');
        if (empty($id)) {
            return $this->fail('参数错误');
        }

        return $this->success('获取成功', $this->service->adminExpressTrace($id));
    }

    /**
     * 读取订单 ID 和备注文本，提交平台备注更新。
     *
     * @return \think\Response 包含保存结果或参数错误的响应。
     */
    public function updateRemark()
    {
        $id = $this->request->param('id/d');
        $remark = trim((string)$this->request->param('remark', ''));

        if (empty($id)) {
            return $this->fail('参数错误');
        }

        $data = $this->service->adminUpdateRemark($id, $remark);
        return $this->success('备注已保存', $data);
    }
}
