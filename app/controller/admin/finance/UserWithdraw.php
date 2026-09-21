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
use app\common\service\user\UserWithdrawService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 用户提现申请控制器
 */
class UserWithdraw extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected UserWithdrawService $service;

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
        return [
            ['keyword', ''],
            ['source', ''],
            ['status', ''],
            ['start_time', ''],
            ['end_time', ''],
        ];
    }

    protected function getRequestFields(): array
    {
        return [];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());

        return $this->success($this->service->getAdminList($params));
    }

    public function audit(int $id): Response
    {
        $status = (int)$this->request->post('status', 0);
        $remark = trim((string)$this->request->post('remark', ''));

        $this->service->audit($id, $status, $remark);

        return $this->success('处理成功');
    }
}
