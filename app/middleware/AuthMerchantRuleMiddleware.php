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

use app\common\service\merchant\MerchantAuthService;
use app\Request;

/**
 * 商户端接口授权中间件。
 */
class AuthMerchantRuleMiddleware
{
    /**
     * 校验当前商户管理员的角色权限。
     *
     * @param Request $request 当前请求
     * @param \Closure $next 下一个中间件
     * @return mixed
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        app()->make(MerchantAuthService::class)->checkRole($request, (array)$request->adminInfo);

        return $next($request);
    }
}
