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

declare(strict_types=1);

namespace app\middleware;

use app\Request;
use app\ExceptionHandle;
use app\common\service\plugin\PluginRuntimeService;
use app\common\exception\AdminResponseException;
use Closure;
use think\Response;
use think\exception\HttpResponseException;
use Throwable;

/**
 * 复查持久化启用状态，防止常驻工作进程继续访问已卸载或停用的插件路由。
 */
class PluginEnabledMiddleware
{
    /**
     * 验证插件路由归属与启用状态，并为插件客户端统一响应结构。
     *
     * @param Request $request 当前原生插件路由请求。
     * @param Closure $next 下一处理器回调。
     * @return mixed 后续处理器或统一异常处理生成的响应。
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (!preg_match('#^api/(?:admin|merchant|mobile)/plugins/([a-z][a-z0-9_]{0,63})/#', trim($request->pathinfo(), '/'), $matches)) {
            throw new AdminResponseException('插件路由不合法');
        }
        try {
            $runtime = app()->make(PluginRuntimeService::class);
            $evidenceRequest = $matches[1] === 'hotel' && $this->isHotelEvidenceRequest($request);
            if ($evidenceRequest && !$runtime->isEnabled('hotel')) {
                $runtime->assertInstalledHotelEvidence('hotel');
            } elseif (!$evidenceRequest) {
                $runtime->assertEnabled($matches[1]);
            }
            $response = $next($request);
        } catch (HttpResponseException $error) {
            $response = $error->getResponse();
        } catch (Throwable $error) {
            $handler = app()->make(ExceptionHandle::class);
            $handler->report($error);
            $response = $handler->render($request, $error);
        }
        if ($response instanceof Response && is_array($data = $response->getData())
            && (isset($data['status']) || isset($data['code']))) {
            // 插件客户端使用统一响应结构，同时保留宿主旧客户端依赖的字段别名。
            $data['code'] = (int) ($data['code'] ?? $data['status']);
            $data['msg'] = (string) ($data['msg'] ?? $data['message'] ?? '');
            $data['data'] = $data['data'] ?? [];
            $response->data($data);
        }
        return $response;
    }

    /** @return bool 固定存量路由白名单，任何酒店查询/报价/新预订均不放行。 */
    private function isHotelEvidenceRequest(Request $request): bool
    {
        $path = trim($request->pathinfo(), '/');
        $method = strtoupper($request->method());
        return (bool)preg_match('#^api/(admin|merchant|mobile)/plugins/hotel/bookings(?:/(\d+)(?:/(cancel|cancel-review|stay|stay-review))?)?$#D', $path, $parts)
            && match (true) {
                count($parts) === 2 => $method === 'GET',
                count($parts) === 3 => $method === 'GET',
                $parts[1] === 'mobile' && ($parts[3] ?? '') === 'cancel' => $method === 'POST',
                $parts[1] === 'admin' && in_array($parts[3] ?? '', ['cancel-review', 'stay-review'], true) => $method === 'PUT',
                $parts[1] === 'merchant' && ($parts[3] ?? '') === 'stay' => $method === 'PUT',
                default => false,
            };
    }
}
