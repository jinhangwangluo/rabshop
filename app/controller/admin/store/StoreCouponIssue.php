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

namespace app\controller\admin\store;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\controller\admin\AuthController;
use app\common\service\store\StoreCouponIssueService;
use think\annotation\Inject;
use think\Response;

/**
 * 优惠券发放记录管理控制器
 */
class StoreCouponIssue extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var StoreCouponIssueService
     */
    #[Inject]
    protected StoreCouponIssueService $service;

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
            ['coupon_id', ''],
            ['merchant_id', ''],
            ['issue_type', ''],
            ['status', ''],
            ['batch_no', ''],
            ['create_time', ''],
        ];
    }

    /**
     * POST请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['coupon_id', 0],
            ['merchant_id', 0],
            ['batch_no', ''],
            ['issue_type', 1],
            ['total_quantity', 0],
            ['issued_quantity', 0],
            ['status', 0],
        ];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取发放统计
     */
    public function statistics(int $id): Response
    {
        $result = $this->service->getIssueStatistics($id);
        return $this->success($result);
    }

    /**
     * 获取发放类型选项
     */
    public function getIssueTypes(): Response
    {
        $types = [
            ['value' => 1, 'label' => '后台发送'],
            ['value' => 2, 'label' => '满赠'],
            ['value' => 3, 'label' => '新人'],
            ['value' => 4, 'label' => '买赠'],
            ['value' => 5, 'label' => '首单赠送'],
        ];
        return $this->success($types);
    }

    /**
     * 获取状态选项
     */
    public function getStatusList(): Response
    {
        $status = [
            ['value' => 0, 'label' => '待发放'],
            ['value' => 1, 'label' => '发放中'],
            ['value' => 2, 'label' => '已发放完成'],
            ['value' => 3, 'label' => '已取消'],
        ];
        return $this->success($status);
    }
}
