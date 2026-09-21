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

namespace app\controller\common;

use app\BaseController;
use app\common\service\plugin\PluginRuntimeService;
use think\annotation\Inject;
use think\Response;

/**
 * 提供公开插件安装与启用身份查询，不暴露插件配置或授权资料。
 */
class PluginRuntime extends BaseController
{
    #[Inject]
    protected PluginRuntimeService $service;

    /**
     * 返回编译后的插件入口使用的公开运行状态。
     *
     * @return Response 包含安全运行状态的成功响应。
     */
    public function read(): Response
    {
        return $this->success($this->service->read());
    }
}
