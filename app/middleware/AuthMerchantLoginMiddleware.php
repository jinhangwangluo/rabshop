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
use app\common\service\merchant\MerchantAuthService;
use app\Request;
use think\facade\Config;

/**
 * 商户端登录验证中间件
 */
class AuthMerchantLoginMiddleware
{
    /**
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     * @throws AdminAuthException
     */
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

        // 使用MerchantAuthService验证Token
        $authService = app()->make(MerchantAuthService::class);
        $authResult = $authService->auth($token);

        // 设置请求属性
        // adminInfo 包含管理员信息和商户信息（merchant字段）
        $request->adminInfo = $authResult['adminInfo'];
        $request->adminId = $authResult['adminInfo']['id'];
        $request->merchantId = $authResult['adminInfo']['merchant_id'];
        $request->merchantInfo = $authResult['adminInfo']['merchant'] ?? [];
        $request->token = $token;

        return $next($request);
    }
}
