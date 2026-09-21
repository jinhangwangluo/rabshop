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

use app\common\exception\AdminAuthException;
use app\common\service\setting\AdminService;
use app\Request;
use think\facade\Config;

/**
 * 管理段登陆验证权限
 */
class AuthAdminLoginMiddleware
{

    public function handle(Request $request, \Closure $next)
    {
        $authorization = $request->header(Config::get('cookie.token_name', 'Authorization'));
        if (!$authorization || !is_string($authorization)) {
            throw new AdminAuthException('缺少TOKEN', 41001);
        }
        $token = trim(ltrim($authorization, 'Bearer'));
        if (!$token) {
            throw new AdminAuthException('缺少TOKEN', 41001);
        }

        $adminService = app()->make(AdminService::class);
        $tokenInfo = $adminService->auth($token);

        $request->adminInfo = $tokenInfo['adminInfo'];
        $request->adminId = $tokenInfo['adminInfo']['id'];
        $request->token = $tokenInfo['token'];

        return $next($request);
    }
}
