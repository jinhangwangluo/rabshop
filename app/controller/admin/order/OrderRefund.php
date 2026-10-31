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

namespace app\controller\admin\order;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\order\OrderRefundService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 后台订单退款记录控制器
 */
class OrderRefund extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var OrderRefundService
     */
    #[Inject]
    protected OrderRefundService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['order_sn', ''],
            ['refund_sn', ''],
            ['user_id', ''],
            ['merchant_id', ''],
            ['user_keyword', ''],
            ['goods_name', ''],
            ['order_type', ''],
            ['pay_type', ''],
            ['refund_status', ''],
            ['refund_record_status', ''],
            ['start_time', ''],
            ['end_time', ''],
            ['page', 1],
            ['limit', 15],
        ];
    }

    /**
     * POST 请求获取字段，退款列表不支持新增/编辑。
     */
    protected function getRequestFields(): array
    {
        return [];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 退款记录列表
     */
    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());

        return $this->success($this->service->getAdminRefundList($params));
    }
}
