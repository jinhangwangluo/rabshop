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

namespace app\controller\admin\cs;

use app\common\service\cs\CsKnowledgeSegmentService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 平台端知识库段落控制器
 */
class CsKnowledgeSegment extends AuthController
{
    #[Inject]
    protected CsKnowledgeSegmentService $service;

    /**
     * 获取段落列表（支持分页）
     * knowledge_base_id 必传；knowledge_id 可选（不传则查知识库下所有）
     */
    public function index(): Response
    {
        $knowledgeBaseId = (int)$this->request->param('knowledge_base_id/d', 0);
        if ($knowledgeBaseId <= 0) {
            return $this->fail('知识库ID不能为空');
        }

        $knowledgeId = (int)$this->request->param('knowledge_id/d', 0);

        return $this->success('获取成功', $this->service->getPaginatedList($knowledgeBaseId, $knowledgeId));
    }

    /**
     * 批量保存段落（覆盖式）
     */
    public function batchSave(): Response
    {
        $knowledgeId = (int)$this->request->post('knowledge_id/d', 0);
        if ($knowledgeId <= 0) {
            return $this->fail('知识ID不能为空');
        }

        $segments = $this->request->post('segments/a', []);
        if ($segments === []) {
            return $this->fail('段落不能为空');
        }

        $ids = $this->service->batchSave($knowledgeId, $segments);
        return $this->success('保存成功', ['segment_ids' => $ids]);
    }

    /**
     * 更新单个段落
     */
    public function update(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $content = (string)$this->request->post('content/s', '');
        $sort    = (int)$this->request->post('sort/d', 0);

        if ($content === '') {
            return $this->fail('内容不能为空');
        }

        $this->service->updateSegment($id, $content, $sort);
        return $this->success('修改成功');
    }

    /**
     * 删除段落
     */
    public function delete(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->deleteSegment($id);
        return $this->success('删除成功');
    }

    /**
     * 单段落向量化（异步入队）
     */
    public function vectorize(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $result = $this->service->batchVectorize([$id], $this->getMerchantId());
        if ((int)$result['queued_count'] === 0) {
            return $this->fail('段落不存在');
        }

        return $this->success('向量化任务已加入队列', $result);
    }

    /**
     * 批量段落向量化（异步入队）
     */
    public function batchVectorize(): Response
    {
        $ids = $this->request->post('ids/a', []);
        if ($ids === []) {
            return $this->fail('请选择要向量化的段落');
        }

        $result = $this->service->batchVectorize(array_map('intval', $ids), $this->getMerchantId());
        return $this->success('批量向量化任务已加入队列', $result);
    }

    protected function getMerchantId(): int
    {
        return 0;
    }
}
