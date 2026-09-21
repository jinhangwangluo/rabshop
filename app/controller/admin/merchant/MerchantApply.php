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
use app\common\service\merchant\MerchantApplyService;
use think\annotation\Inject;

/**
 * Class MerchantApply
 * @package app\controller\admin\merchant
 */
class MerchantApply extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var MerchantApplyService
     */
    #[Inject]
    protected MerchantApplyService $service;

    /**
     * 状态字段
     * @return string
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     * @return string|null
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 搜索字段
     * @return array[]
     */
    protected function getIndexFields(): array
    {
        return [
            ['mer_name', ''],
            ['status', ''],
        ];
    }

    /**
     * post请求获取的字段
     * @return array
     */
    protected function getRequestFields(): array
    {
        return [
            ['mer_name', ''],
            ['mer_phone', ''],
            ['real_name', ''],
            ['status', 0],
            ['fail_msg', ''],
        ];
    }

    /**
     * 获取表单的前置字段
     * @return array
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 审核
     * @param $id
     * @return mixed
     */
    public function audit($id)
    {
        $status = $this->request->post('status');
        $fail_msg = $this->request->post('fail_msg', '');

        if (!in_array($status, [1, 2])) return $this->fail('状态错误');

        try {
            $this->service->audit((int)$id, (int)$status, $fail_msg);
            return $this->success('审核成功');
        } catch (\Exception $e) {
            return $this->fail('审核失败:' . $e->getMessage());
        }
    }
}
