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

namespace app\controller\merchant\setting;

use app\controller\merchant\AuthController;
use app\common\service\merchant\MerchantMenuService;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端菜单控制器
 * Class Menu
 * @package app\controller\merchant\setting
 */
class Menu extends AuthController
{
    /**
     * @var MerchantMenuService
     */
    #[Inject]
    protected MerchantMenuService $service;

    /**
     * 获取菜单列表
     * @return Response
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['auth_type', ''],
            ['status', ''],
        ]);

        $where = [];
        if ($params['auth_type'] !== '') {
            $where['auth_type'] = $params['auth_type'];
        }
        if ($params['status'] !== '') {
            $where['status'] = $params['status'];
        }

        $list = $this->service->getMenusList($where);
        return $this->success($list);
    }
}
