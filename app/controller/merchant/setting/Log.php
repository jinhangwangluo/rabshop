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

use app\common\service\merchant\MerchantAdminService;
use app\common\service\setting\AdminLogService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 商户端日志控制器
 * Class Log
 * @package app\controller\merchant\setting
 */
class Log extends AuthController
{
    /**
     * @var AdminLogService
     */
    #[Inject]
    protected AdminLogService $service;

    /**
     * 日志列表
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(): Response
    {
        $where = $this->request->getMore([
            ['user_id', []],
            ['method', ''],
            ['path', ''],
            ['created_at', '']
        ]);

        return $this->success($this->service->getOperationLogList($where, $this->merchantId));
    }

    /**
     * 管理员下拉列表
     * @return Response
     */
    public function adminList(): Response
    {
        $adminService = app()->make(MerchantAdminService::class);
        return $this->success($adminService->getAdminSelect($this->merchantId));
    }
}
