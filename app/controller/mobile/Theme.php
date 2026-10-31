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

namespace app\controller\mobile;

use app\BaseController;
use app\common\service\setting\ThemeConfigService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端主题配置控制器。
 */
class Theme extends BaseController
{
    /**
     * 主题配置服务。
     */
    #[Inject]
    protected ThemeConfigService $service;

    /**
     * 获取移动端公开主题配置。
     */
    public function config(): Response
    {
        return $this->success('获取成功', $this->service->publicConfig());
    }
}
