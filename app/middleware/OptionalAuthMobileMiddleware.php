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

/**
 * 移动端可选登录中间件
 */
class OptionalAuthMobileMiddleware
{
    public function handle($request, \Closure $next)
    {
        $authorization = trim((string)$request->header('Authorization', ''));
        if ($authorization === '') {
            return $next($request);
        }

        $token = trim(str_ireplace('Bearer ', '', $authorization));
        if ($token === '') {
            return $next($request);
        }

        $userId = app()->make(UserAuthService::class)->validateToken($token);
        if ($userId) {
            $request->user_id = $userId;
        }

        return $next($request);
    }
}
