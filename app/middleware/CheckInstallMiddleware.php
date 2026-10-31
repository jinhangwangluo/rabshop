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

use app\Request;
use think\Response;

/**
 * 系统安装状态检查中间件。
 */
class CheckInstallMiddleware
{
    private const INSTALL_PATH = 'install';
    private const INSTALL_API_PATH = 'api/install';
    private const INSTALL_LOCK = 'install/install.lock';

    /**
     * 未安装时跳转安装页面。
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->shouldSkip($request) || $this->isInstalled()) {
            return $next($request);
        }

        return redirect('/install');
    }

    /**
     * 安装相关入口需要放行，避免安装页和安装接口被重定向。
     *
     * @param Request $request
     * @return bool
     */
    private function shouldSkip(Request $request): bool
    {
        if ($request->method(true) === 'OPTIONS') {
            return true;
        }

        $path = trim($request->pathinfo(), '/');

        return $path === self::INSTALL_PATH
            || strpos($path, self::INSTALL_PATH . '/') === 0
            || $path === self::INSTALL_API_PATH
            || strpos($path, self::INSTALL_API_PATH . '/') === 0;
    }

    /**
     * 是否已安装。
     *
     * @return bool
     */
    private function isInstalled(): bool
    {
        return is_file(public_path() . self::INSTALL_LOCK);
    }
}
