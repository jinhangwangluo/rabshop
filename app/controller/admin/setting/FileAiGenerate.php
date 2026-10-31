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

namespace app\controller\admin\setting;

use app\common\service\setting\FileAiGenerateService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 文件库 AI 生图任务。
 */
class FileAiGenerate extends AuthController
{
    /**
     * @var FileAiGenerateService
     */
    #[Inject]
    protected FileAiGenerateService $service;

    /**
     * 获取任务列表。
     */
    public function list(): Response
    {
        $params = $this->request->getMore([
            ['status', ''],
            ['keyword', ''],
            ['page', 1, '', 'd'],
            ['limit', 10, '', 'd'],
        ]);

        return $this->success($this->service->getTaskList($params));
    }

    /**
     * 创建任务并加入队列。
     */
    public function save(): Response
    {
        $data = $this->request->postMore([
            ['title', ''],
            ['prompt', ''],
            ['negative_prompt', ''],
            ['style', ''],
            ['size', '1024x1024'],
            ['ratio', '1:1'],
            ['count', 4, '', 'd'],
            ['quality', 'auto'],
            ['output_format', 'png'],
            ['output_compression', 100, '', 'd'],
            ['seed', ''],
            ['random_seed', 1, '', 'd'],
            ['high_quality', 0, '', 'd'],
            ['reference_file_id', 0, '', 'd'],
        ]);

        return $this->success('任务已加入队列', $this->service->createTask($data, (int)$this->adminId));
    }

    /**
     * 获取任务详情。
     */
    public function read(int $id): Response
    {
        return $this->success($this->service->getTaskDetail($id));
    }

    /**
     * 取消任务。
     */
    public function cancel(int $id): Response
    {
        return $this->success('取消成功', $this->service->cancelTask($id));
    }

    /**
     * 重试任务。
     */
    public function retry(int $id): Response
    {
        return $this->success('任务已重新加入队列', $this->service->retryTask($id));
    }
}
