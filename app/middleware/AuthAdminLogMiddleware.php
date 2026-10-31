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

use app\common\service\setting\AdminLogService;
use app\Request;

/**
 * 记录日志
 */
class AuthAdminLogMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        try {
            app()->make(AdminLogService::class)->saveAdminLog(
                adminId: $request->adminId,
                adminName: $request->adminInfo['account'],
                method: $request->method(),
                rule: trim(strtolower($request->rule()->getRule())),
                ip: $request->ip());
        } catch (\Throwable) {
        }

        return $next($request);
    }
}