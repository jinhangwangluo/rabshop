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

use app\common\service\setting\AdminLogService;
use app\common\service\setting\AdminService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 管理员日志记录
 */
class Log extends AuthController
{

    #[Inject]
    protected AdminLogService $service;

    /**
     * 日志列表
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['user_id', []],
            ['method', ''],
            ['path', ''],
            ['created_at', '']
        ]);

        return $this->success($this->service->getOperationLogList($where));
    }

    /**
     * 管理员列表
     * @param AdminService $service
     * @return Response
     */
    public function adminList(AdminService $service)
    {
        $adminId = $this->adminId;
        if (!$this->adminInfo['pid']) {
            $adminId = '';
        }
        return $this->success($service->getAdminAll($adminId));
    }
}