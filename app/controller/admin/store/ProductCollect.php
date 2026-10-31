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

namespace app\controller\admin\store;

use app\common\service\store\ProductCollectService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\file\UploadedFile;
use think\Response;

/**
 * 商品批量采集（Excel 导入 → 队列采集 → 补全 → 批量上架）。
 */
class ProductCollect extends AuthController
{
    #[Inject]
    protected ProductCollectService $service;

    /**
     * 导入表格批量创建采集任务。
     *
     * @return Response 任务统计
     */
    public function import(): Response
    {
        $file = $this->request->file('file');
        if (!$file instanceof UploadedFile) {
            return $this->fail('请上传表格文件');
        }
        $adminId = (int)($this->request->adminInfo['id'] ?? 0);

        return $this->success('导入成功', $this->service->importFromFile($file, $adminId));
    }

    /**
     * 采集任务列表。
     *
     * @return Response 任务分页
     */
    public function tasks(): Response
    {
        $params = $this->request->getMore([
            ['status', ''],
            ['publish_status', ''],
            ['batch_no', ''],
            ['keyword', ''],
        ]);

        return $this->success('获取成功', $this->service->getTaskList($params));
    }

    /**
     * 下载导入模板。
     */
    public function template(): Response
    {
        return $this->service->templateDownload();
    }

    /**
     * 批量上架（入队逐条执行）。
     *
     * @return Response 入队统计
     */
    public function publish(): Response
    {
        $data = $this->request->postMore([['task_ids', []]]);
        $taskIds = (array)($data['task_ids'] ?? []);
        $adminId = (int)($this->request->adminInfo['id'] ?? 0);

        return $this->success('已提交上架队列', $this->service->batchPublish($taskIds, $adminId));
    }

    /**
     * 批量补全任务的库商品分类/品牌/运费模板。
     *
     * @return Response 补全统计
     */
    public function complete(): Response
    {
        $data = $this->request->postMore([
            ['task_ids', []],
            ['category_id', 0],
            ['brand_id', 0],
            ['freight_template_id', 0],
        ]);
        $adminId = (int)($this->request->adminInfo['id'] ?? 0);

        return $this->success('补全成功', $this->service->completeTasks((array)$data['task_ids'], $data, $adminId));
    }

    /**
     * 重试采集失败的任务。
     *
     * @param int $id 任务ID
     * @return Response 任务信息
     */
    public function retry(int $id): Response
    {
        return $this->success('已重新加入采集队列', $this->service->retryTask($id));
    }

    /**
     * 批量重试全部采集失败的任务（延迟队列逐秒错峰执行）。
     *
     * @return Response 重试统计
     */
    public function retryFailed(): Response
    {
        return $this->success('失败任务已重新加入采集队列', $this->service->retryFailedTasks());
    }

    /**
     * 删除任务（不动已生成的库商品）。
     *
     * @param int $id 任务ID
     * @return Response
     */
    public function delete(int $id): Response
    {
        $this->service->deleteTask($id);

        return $this->success('删除成功');
    }

    /**
     * 批量删除任务（上架中的任务自动跳过）。
     *
     * @return Response 删除统计
     */
    public function deleteBatch(): Response
    {
        $data = $this->request->postMore([['task_ids', []]]);

        return $this->success('删除成功', $this->service->deleteTasks((array)$data['task_ids']));
    }
}
