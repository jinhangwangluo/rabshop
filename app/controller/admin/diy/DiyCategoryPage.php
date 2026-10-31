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

use app\common\service\diy\DiyCategoryPageService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * DIY 分类页面配置控制器
 */
class DiyCategoryPage extends AuthController
{
    #[Inject]
    protected DiyCategoryPageService $service;

    /**
     * 获取当前分类页面配置。
     */
    public function config(): Response
    {
        return $this->success('获取成功', $this->service->getConfig());
    }

    /**
     * 保存分类页面配置。
     */
    public function save(): Response
    {
        $styleType = (string)$this->request->put('style_type', 'special');
        $config = $this->request->put('config', []);
        if (!is_array($config)) {
            return $this->fail('配置格式错误');
        }

        return $this->success('保存成功', $this->service->saveConfig($styleType, $config));
    }
}
