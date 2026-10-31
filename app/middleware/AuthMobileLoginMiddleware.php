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

use app\common\service\user\UserAuthService;
use think\facade\Request;

class AuthMobileLoginMiddleware
{
    public function handle($request, \Closure $next)
    {
        $token = $request->header('Authorization');
        if ($token) {
            $token = str_replace('Bearer ', '', $token);
        }

        if (!$token) {
            return json(['code' => 401, 'msg' => '未登录或token已过期']);
        }

        $authService = app()->make(UserAuthService::class);
        $userId = $authService->validateToken($token);

        if (!$userId) {
            return json(['code' => 401, 'msg' => 'token无效或已过期']);
        }

        $request->user_id = $userId;
        return $next($request);
    }
}
