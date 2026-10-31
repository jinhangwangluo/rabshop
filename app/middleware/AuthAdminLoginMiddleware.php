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
            return d_json()->fail('缺少TOKEN', null, 41001);
        }
        $token = trim(ltrim($authorization, 'Bearer'));
        if (!$token) {
            return d_json()->fail('缺少TOKEN', null, 41001);
        }

        try {
            $adminService = app()->make(AdminService::class);
            $tokenInfo = $adminService->auth($token);
        } catch (AdminAuthException $e) {
            // 登录态业务码（41002/41003/41004）必须原样返回给前端：前端拦截器据此静默刷新 token 或跳转登录页。
            // 若抛给异常渲染层，业务码会按 HTTP 码范围被兜底成 400，前端无法识别登录态失效。
            return d_json()->fail($e->getMessage(), null, $e->getCode() > 0 ? (int)$e->getCode() : 41003);
        }

        $request->adminInfo = $tokenInfo['adminInfo'];
        $request->adminId = $tokenInfo['adminInfo']['id'];
        $request->token = $tokenInfo['token'];

        return $next($request);
    }
}
