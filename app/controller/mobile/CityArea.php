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
use app\common\service\setting\CityAreaService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端省市区地址数据控制器。
 */
class CityArea extends BaseController
{
    /**
     * 城市区域服务.
     *
     * @var CityAreaService
     */
    #[Inject]
    protected CityAreaService $service;

    /**
     * 获取 bubble-address 组件需要的省市区数据。
     *
     * @return Response 返回省市区树形数据。
     */
    public function bubble(): Response
    {
        return json($this->service->getBubbleAddressData());
    }
}
