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

namespace app;

use app\common\exception\ApiException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        $requestUri = (string)$request->server('REQUEST_URI');
        $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '';
        $isPluginGateway = str_starts_with($requestPath, '/api/admin/plugins/gateway');
        $error = env('APP_DEBUG') && !$isPluginGateway ? [
            'trace' => $e->getTrace(),
            'line'  => $e->getLine(),
            'file'  => $e->getFile()
        ] : [];

        // 添加自定义异常处理机制
        $status = $e->getCode() !== 0 ? $e->getCode() : 400;
        //api接口特殊返回
        if ($e instanceof ApiException && $e->getCode()) {
            return d_json()->make($status, $e->getMessage(), $error);
        }

        if ($isPluginGateway) {
            return d_json()->code(400)->fail($e->getMessage(), [], 400);
        }

        return d_json()->fail($e->getMessage(), $error, $status);
    }
}
