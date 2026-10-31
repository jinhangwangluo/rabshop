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

use app\common\service\cs\CsKnowledgeQaService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 平台端知识库问答控制器
 */
class CsKnowledgeQa extends AuthController
{
    #[Inject]
    protected CsKnowledgeQaService $service;

    /**
     * 获取 QA 列表（支持分页）
     * knowledge_base_id 必传；knowledge_id / segment_id 可选
     */
    public function index(): Response
    {
        $knowledgeBaseId = (int)$this->request->param('knowledge_base_id/d', 0);
        if ($knowledgeBaseId <= 0) {
            return $this->fail('知识库ID不能为空');
        }

        $knowledgeId = (int)$this->request->param('knowledge_id/d', 0);
        $segmentId   = (int)$this->request->param('segment_id/d', 0);

        return $this->success('获取成功', $this->service->getPaginatedList($knowledgeBaseId, $knowledgeId, $segmentId));
    }

    /**
     * 新增单条 QA
     */
    public function create(): Response
    {
        $segmentId   = (int)$this->request->post('segment_id/d', 0);
        $knowledgeId = (int)$this->request->post('knowledge_id/d', 0);
        $question    = (string)$this->request->post('question/s', '');
        $answer      = (string)$this->request->post('answer/s', '');
        $sort        = (int)$this->request->post('sort/d', 0);

        if ($segmentId <= 0) {
            return $this->fail('段落ID不能为空');
        }
        if ($knowledgeId <= 0) {
            return $this->fail('知识ID不能为空');
        }
        if ($question === '') {
            return $this->fail('问题不能为空');
        }
        if ($answer === '') {
            return $this->fail('答案不能为空');
        }

        $id = $this->service->createQa($segmentId, $knowledgeId, $question, $answer, $sort);
        return $this->success('新增成功', ['id' => $id]);
    }

    /**
     * 批量保存某段落的 QA（覆盖式）
     */
    public function batchSave(): Response
    {
        $segmentId   = (int)$this->request->post('segment_id/d', 0);
        $knowledgeId = (int)$this->request->post('knowledge_id/d', 0);
        $qaList      = $this->request->post('qa_list/a', []);

        if ($segmentId <= 0) {
            return $this->fail('段落ID不能为空');
        }
        if ($knowledgeId <= 0) {
            return $this->fail('知识ID不能为空');
        }
        if ($qaList === []) {
            return $this->fail('QA列表不能为空');
        }

        $ids = $this->service->batchSave($segmentId, $knowledgeId, $qaList);
        return $this->success('保存成功', ['qa_ids' => $ids]);
    }

    /**
     * 更新单条 QA
     */
    public function update(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $question = (string)$this->request->post('question/s', '');
        $answer   = (string)$this->request->post('answer/s', '');
        $sort     = (int)$this->request->post('sort/d', 0);

        if ($question === '') {
            return $this->fail('问题不能为空');
        }
        if ($answer === '') {
            return $this->fail('答案不能为空');
        }

        $this->service->updateQa($id, $question, $answer, $sort);
        return $this->success('修改成功');
    }

    /**
     * 删除单条 QA
     */
    public function delete(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->deleteQa($id);
        return $this->success('删除成功');
    }

    /**
     * 单条 QA 向量化（异步入队）
     */
    public function vectorize(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $result = $this->service->batchVectorize([$id], $this->getMerchantId());
        if ((int)$result['queued_count'] === 0) {
            return $this->fail('QA不存在');
        }

        return $this->success('向量化任务已加入队列', $result);
    }

    /**
     * 批量 QA 向量化（异步入队）
     */
    public function batchVectorize(): Response
    {
        $ids = $this->request->post('ids/a', []);
        if ($ids === []) {
            return $this->fail('请选择要向量化的问答');
        }

        $result = $this->service->batchVectorize(array_map('intval', $ids), $this->getMerchantId());
        return $this->success('批量向量化任务已加入队列', $result);
    }

    protected function getMerchantId(): int
    {
        return 0;
    }
}
