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

namespace app\controller\admin\merchant;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\controller\admin\AuthController;
use app\common\service\merchant\MerchantWithdrawService;
use think\annotation\Inject;
use think\Response;

/**
 * 商户提现申请控制器
 */
class MerchantWithdraw extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 商户提现申请服务
     */
    #[Inject]
    protected MerchantWithdrawService $service;

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
        return 'app\common\validate\MerchantWithdrawValidate';
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['keyword', ''],
            ['mer_name', ''],
            ['merchant_id', ''],
            ['withdraw_type', ''],
            ['status', ''],
            ['start_time', ''],
            ['end_time', ''],
        ];
    }

    /**
     * post请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['merchant_id', 0],
            ['amount', 0],
            ['withdraw_type', 'bank'],
            ['bank_name', ''],
            ['bank_account', ''],
            ['account_name', ''],
            ['payment_qrcode', ''],
            ['status', 0],
            ['remark', ''],
        ];
    }

    /**
     * 获取表单的前置字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 处理提现申请
     */
    public function handle(int $id): Response
    {
        $status = (int)$this->request->post('status', 0);
        $remark = trim((string)$this->request->post('remark', ''));

        $this->service->handle($id, $status, $remark);

        return $this->success('处理成功');
    }
}
