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

namespace app\controller\admin\setting;

use app\common\service\setting\PaymentConfigService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 支付配置控制器。
 */
class PaymentConfig extends AuthController
{
    /**
     * 支付配置服务。
     */
    #[Inject]
    protected PaymentConfigService $service;

    /**
     * 获取支付配置详情。
     */
    public function index()
    {
        return $this->success($this->service->detail());
    }

    /**
     * 保存支付配置。
     */
    public function save()
    {
        $method = $this->request->post('method/a', []);
        if ($method) {
            return $this->success('保存成功', $this->service->saveDisplayMethod($method));
        }

        $methods = $this->request->post('methods/a', []);
        return $this->success('保存成功', $this->service->saveDisplayConfig($methods));
    }

    /**
     * 获取支付配置选项。
     */
    public function options()
    {
        return $this->success($this->service->options());
    }
}
