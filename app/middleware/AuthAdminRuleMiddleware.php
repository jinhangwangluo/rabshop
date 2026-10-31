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

namespace app\middleware;

use app\common\service\setting\AdminService;
use app\Request;

/**
 * 管理端接口授权
 */
class AuthAdminRuleMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $adminService = app()->make(AdminService::class);

        if ($request->adminInfo && $request->adminInfo['pid']) {
            $adminService->checkRole($request, $request->adminInfo);
        }

        return $next($request);
    }
}