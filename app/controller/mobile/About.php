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

namespace app\controller\mobile;

use app\BaseController;
use app\common\service\about\AboutService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端关于我们控制器。
 */
class About extends BaseController
{
    #[Inject]
    protected AboutService $service;

    /**
     * 获取关于我们详情。
     *
     * 未配置内容时返回成功响应和空 content，移动端自行展示空状态。
     */
    public function detail(): Response
    {
        return $this->success('获取成功', $this->service->detail());
    }
}
