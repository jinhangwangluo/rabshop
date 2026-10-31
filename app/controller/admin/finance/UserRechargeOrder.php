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

namespace app\controller\admin\finance;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\user\UserRechargeService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 用户充值订单控制器
 */
class UserRechargeOrder extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 用户充值服务
     */
    #[Inject]
    protected UserRechargeService $service;

    protected function getStatusField(): string
    {
        return 'status';
    }

    protected function getValidateClassName(): string|null
    {
        return null;
    }

    protected function getIndexFields(): array
    {
        return [];
    }

    protected function getRequestFields(): array
    {
        return [];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取充值订单详情
     */
    public function read($id): Response
    {
        return $this->success($this->service->getAdminRechargeOrderDetail((int)$id));
    }
}
