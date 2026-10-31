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

namespace app\controller\admin\plugin;

use app\common\service\plugin\PluginBuildService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\response\File;
use think\Response;

/**
 * 打包服务代理控制器。
 *
 * 本实例后台不保存构建任务与产物，全部以打包服务为准：
 * 上传源码包、创建构建任务、查询进度与日志、下载产物、上传小程序私钥都只是转发，
 * 调用身份复用当前管理员在插件市场绑定的官网账号。
 */
class PluginBuild extends AuthController
{
    #[Inject]
    protected PluginBuildService $buildService;

    /**
     * 可用打包目标与探测结果。
     */
    public function targets(): Response
    {
        return $this->success($this->buildService->targets());
    }

    /**
     * 上传源码包（multipart 字段 file，或原始字节流）。
     */
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if ($file !== null) {
            return $this->success($this->buildService->uploadSource(
                (string)$file->getPathname(),
                (string)$file->getOriginalName()
            ));
        }

        $contentType = strtolower($this->request->contentType());
        if ($contentType === 'application/json' || str_contains($contentType, 'json')) {
            return $this->fail('请上传 zip 源码包（multipart 字段名 file）');
        }

        return $this->success($this->buildService->uploadSource('', '', $this->request->getContent()));
    }

    /**
     * 创建构建任务。
     */
    public function save(): Response
    {
        $payload = $this->request->post();
        if (!is_array($payload)) {
            return $this->fail('请求参数格式不正确');
        }

        return $this->success($this->buildService->create($payload));
    }

    /**
     * 当前官网账号的构建任务列表。
     */
    public function index(): Response
    {
        return $this->success($this->buildService->list([
            'status' => (string)$this->request->param('status', ''),
            'target' => (string)$this->request->param('target', ''),
        ]));
    }

    /**
     * 构建任务详情。
     */
    public function read(string $id): Response
    {
        return $this->success($this->buildService->detail($id, $this->isFlagOn('log')));
    }

    /**
     * 构建日志。
     */
    public function log(string $id): Response
    {
        return $this->success($this->buildService->log($id, (int)$this->request->param('tail', 200)));
    }

    /**
     * 下载构建产物 zip。
     */
    public function artifact(string $id): Response
    {
        $artifact = $this->buildService->artifact($id);

        $response = Response::create((string)$artifact['content'], File::class);
        $response->isContent(true)->mimeType((string)$artifact['mime']);
        $response->name((string)$artifact['name'], false);

        return $response;
    }

    /**
     * 取消构建任务。
     */
    public function cancel(string $id): Response
    {
        return $this->success($this->buildService->cancel($id));
    }

    /**
     * 上传小程序私钥（一次性凭据，仅 mp-upload 目标需要）。
     *
     * 私钥只在请求内转发一次，本接口不落盘、不回显。
     */
    public function mpCredential(): Response
    {
        $payload = [
            'appid' => (string)$this->request->param('appid', ''),
            'description' => (string)$this->request->param('description', ''),
        ];

        $file = $this->request->file('file');
        if ($file !== null) {
            return $this->success($this->buildService->uploadMpCredential(
                $payload,
                (string)$file->getPathname()
            ));
        }

        $payload['private_key'] = (string)$this->request->param('private_key', '');

        return $this->success($this->buildService->uploadMpCredential($payload));
    }

    /**
     * 读取 1/true/yes 形式的开关参数。
     */
    private function isFlagOn(string $name): bool
    {
        $value = strtolower((string)$this->request->param($name, ''));

        return in_array($value, ['1', 'true', 'yes'], true);
    }
}
