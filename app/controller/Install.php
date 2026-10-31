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

namespace app\controller;

use app\BaseController;
use app\common\service\install\InstallService;
use think\annotation\Inject;
use think\Response;

/**
 * 系统安装控制器。
 */
class Install extends BaseController
{
    #[Inject]
    protected InstallService $service;

    /**
     * 安装页面。
     */
    public function page(): Response
    {
        $path = public_path() . 'install/index.html';
        if (!is_file($path)) {
            return $this->fail('安装页面不存在');
        }

        return Response::create((string)file_get_contents($path), 'html');
    }

    /**
     * 安装状态。
     */
    public function status(): Response
    {
        return $this->success('获取成功', $this->service->getStatus($this->request->domain()));
    }

    /**
     * 环境检测。
     */
    public function check(): Response
    {
        return $this->success('检测完成', $this->service->checkEnvironment());
    }

    /**
     * 测试数据库或 Redis 配置。
     */
    public function test(): Response
    {
        $result = $this->service->testConfig((array)$this->request->param());
        if ($result['passed']) {
            return $this->success('测试通过', $result);
        }

        return $this->fail((string)$result['message'], $result);
    }

    /**
     * 执行安装。
     */
    public function run(): Response
    {
        $result = $this->service->startInstall((array)$this->request->param(), $this->request->domain());
        if ($result['passed']) {
            return $this->success('安装任务已启动', $result['data'] ?? []);
        }

        return $this->fail((string)$result['message'], $result['data'] ?? []);
    }

    /**
     * 安装进度。
     */
    public function progress(): Response
    {
        return $this->success('获取成功', $this->service->getProgress());
    }
}
