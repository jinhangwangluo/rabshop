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


use app\common\service\user\UserService;
use app\Request;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class AuthLoginMiddleware
 * @package app\middleware
 */
class AuthLoginMiddleware
{
    /**
     * @param Request $request
     * @param \Closure $next
     * @param bool $force
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function handle(Request $request, \Closure $next, bool $force = true)
    {
        /** @var UserService $service */
        $service = app()->make(UserService::class);


        try {
            $tokenData = $service->auth();
            $request->uid = $tokenData['userInfo']['uid'];
            $request->userInfo = $tokenData['userInfo'];
            $request->userInfoFn = fn(string $key = null) => $key ? ($tokenData['userInfo'][$key] ?? null) : $tokenData['userInfo'];
        } catch (\Throwable $e) {
            if ($force) {
                throw $e;
            }
        }

        return $next($request);
    }
}
