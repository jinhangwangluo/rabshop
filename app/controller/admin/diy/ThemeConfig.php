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

namespace app\controller\admin\diy;

use app\common\service\setting\ThemeConfigService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * 移动端主题换色配置控制器。
 */
class ThemeConfig extends AuthController
{
    /**
     * 主题配置服务。
     */
    #[Inject]
    protected ThemeConfigService $service;

    /**
     * 获取主题配置详情。
     */
    public function index()
    {
        return $this->success('获取成功', $this->service->detail());
    }

    /**
     * 保存主题配置。
     */
    public function save()
    {
        $data = $this->request->postMore([
            ['theme_key', 'orange'],
            ['primary_color', '#ea580c'],
            ['secondary_color', '#fb923c'],
            ['gradient_color', 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)'],
        ]);

        return $this->success('保存成功', $this->service->save($data));
    }
}
