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

namespace app\controller\merchant\cs;

use app\common\model\cs\CsKnowledge as CsKnowledgeModel;
use app\common\service\cs\CsKnowledgeSegmentService;
use app\common\service\cs\CsKnowledgeService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 客服知识库控制器
 */
class CsKnowledge extends AuthController
{
    /**
     * @var CsKnowledgeService
     */
    #[Inject]
    protected CsKnowledgeService $service;

    /**
     * 获取知识列表
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['knowledge_base_id', ''],
            ['keyword', ''],
            ['vector_status', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 20],
        ]);

        $result = $this->service->getKnowledgeList(
            $this->getMerchantId(),
            $params['knowledge_base_id'],
            trim((string)$params['keyword']),
            $params['vector_status'],
            $params['status'],
            (int)$params['page'],
            (int)$params['limit']
        );

        return $this->success('获取成功', $result);
    }

    /**
     * 创建知识
     */
    public function create(): Response
    {
        $data = $this->request->postMore([
            ['knowledge_base_id', 0],
            ['title', ''],
            ['content', ''],
            ['source', CsKnowledgeModel::SOURCE_MANUAL],
            ['source_url', ''],
            ['status', 1],
            ['sort', 0],
        ]);

        if ((int)$data['knowledge_base_id'] <= 0) {
            return $this->fail('知识库不能为空');
        }
        if (trim((string)$data['title']) === '') {
            return $this->fail('标题不能为空');
        }
        if (trim((string)$data['content']) === '') {
            return $this->fail('内容不能为空');
        }

        $knowledge = $this->service->createKnowledge(
            (int)$data['knowledge_base_id'],
            $this->getMerchantId(),
            trim((string)$data['title']),
            trim((string)$data['content']),
            (string)$data['source'],
            trim((string)$data['source_url'])
        );

        if ((int)$data['status'] !== 1 || (int)$data['sort'] !== 0) {
            $this->service->updateKnowledge((int)$knowledge->id, [
                'status' => (int)$data['status'],
                'sort' => (int)$data['sort'],
            ], $this->getMerchantId());
            $knowledge = $this->service->getKnowledgeById((int)$knowledge->id, $this->getMerchantId());
        }

        return $this->success('保存成功', $knowledge ? $knowledge->toArray() : []);
    }

    /**
     * 更新知识
     */
    public function update(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $data = $this->request->postMore([
            ['knowledge_base_id', 0],
            ['title', ''],
            ['content', ''],
            ['status', 1],
            ['sort', 0],
        ]);
        if ((int)$data['knowledge_base_id'] <= 0) {
            return $this->fail('知识库不能为空');
        }
        if (trim((string)$data['title']) === '') {
            return $this->fail('标题不能为空');
        }
        if (trim((string)$data['content']) === '') {
            return $this->fail('内容不能为空');
        }

        $this->service->updateKnowledge($id, [
            'knowledge_base_id' => (int)$data['knowledge_base_id'],
            'title' => trim((string)$data['title']),
            'content' => trim((string)$data['content']),
            'status' => (int)$data['status'],
            'sort' => (int)$data['sort'],
        ], $this->getMerchantId());

        return $this->success('修改成功');
    }

    /**
     * 删除知识
     */
    public function delete(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->deleteKnowledge($id, $this->getMerchantId());
        return $this->success('删除成功');
    }

    /**
     * 批量删除知识
     */
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids/a', []);
        if ($ids === []) {
            return $this->fail('请选择要删除的知识');
        }

        $this->service->batchDeleteKnowledge(array_map('intval', $ids), $this->getMerchantId());
        return $this->success('删除成功');
    }

    /**
     * 单条向量化
     */
    public function vectorize(): Response
    {
        $id = (int)$this->request->post('id/d', 0);
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        if (!$this->service->vectorize($id, $this->getMerchantId())) {
            return $this->fail('向量化失败');
        }

        return $this->success('向量化成功');
    }

    /**
     * 批量向量化
     */
    public function batchVectorize(): Response
    {
        $ids = $this->request->post('ids/a', []);
        if ($ids === []) {
            return $this->fail('请选择要向量化的知识');
        }

        $result = $this->service->batchVectorize(array_map('intval', $ids), $this->getMerchantId());
        return $this->success('批量向量化任务已加入队列', $result);
    }

    /**
     * 解析内容并返回段落预览（不入库）
     * 支持手动文本、文件内容 或 URL，统一返回段落数组
     */
    public function parseContent(): Response
    {
        $source = trim((string)$this->request->post('source/s', 'manual'));
        /** @var CsKnowledgeSegmentService $segmentService */
        $segmentService = app()->make(CsKnowledgeSegmentService::class);

        if ($source === CsKnowledgeModel::SOURCE_MANUAL) {
            $text = trim((string)$this->request->post('content/s', ''));
            if ($text === '') {
                return $this->fail('内容不能为空');
            }
            return $this->success('解析成功', $segmentService->splitContent($text));
        }

        if ($source === CsKnowledgeModel::SOURCE_URL) {
            $url = trim((string)$this->request->post('url/s', ''));
            if ($url === '') {
                return $this->fail('URL不能为空');
            }
            $text = $this->service->extractTextFromUrl($url);
            return $this->success('解析成功', $segmentService->splitContent($text));
        }

        if ($source === CsKnowledgeModel::SOURCE_FILE) {
            $file = $this->request->file('file');
            if (!$file) {
                return $this->fail('请选择文件');
            }
            $text = $this->service->extractTextFromFile(
                (string)$file->getOriginalName(),
                (string)$file->getRealPath()
            );
            return $this->success('解析成功', $segmentService->splitContent($text));
        }

        return $this->fail('不支持的来源类型');
    }

    /**
     * 批量保存段落（前端确认分段结果后调用）
     */
    public function updateSegments(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $segments = $this->request->post('segments/a', []);
        if ($segments === []) {
            return $this->fail('段落不能为空');
        }

        // 确认知识归属
        $knowledge = $this->service->getKnowledgeById($id, $this->getMerchantId());
        if (!$knowledge) {
            return $this->fail('知识不存在');
        }

        /** @var CsKnowledgeSegmentService $segmentService */
        $segmentService = app()->make(CsKnowledgeSegmentService::class);
        $segmentIds = $segmentService->batchSave($id, $segments, $this->getMerchantId());

        return $this->success('保存成功', ['segment_ids' => $segmentIds]);
    }

    /**
     * 搜索知识
     */
    public function search(): Response
    {
        $params = $this->request->getMore([
            ['query', ''],
            ['limit', 5],
        ]);

        if (trim((string)$params['query']) === '') {
            return $this->fail('查询内容不能为空');
        }

        return $this->success(
            '获取成功',
            $this->service->search(trim((string)$params['query']), $this->getMerchantId(), (int)$params['limit'])
        );
    }

    /**
     * 文件导入知识
     */
    public function importFile(): Response
    {
        $knowledgeBaseId = (int)$this->request->post('knowledge_base_id/d', 0);
        $autoVectorize = (int)$this->request->post('auto_vectorize/d', 1);
        $customTitle = trim((string)$this->request->post('title/s', ''));
        if ($knowledgeBaseId <= 0) {
            return $this->fail('知识库不能为空');
        }

        $file = $this->request->file('file');
        if (!$file) {
            return $this->fail('请选择文件');
        }

        $knowledge = $this->service->importByFile(
            $knowledgeBaseId,
            $this->getMerchantId(),
            (string)$file->getOriginalName(),
            (string)$file->getRealPath(),
            $customTitle
        );

        if ($autoVectorize === 1) {
            $this->service->batchVectorize([(int)$knowledge->id], $this->getMerchantId());
        }

        return $this->success('导入成功', $knowledge ? $knowledge->toArray() : []);
    }

    /**
     * URL采集知识
     */
    public function importUrl(): Response
    {
        $data = $this->request->postMore([
            ['knowledge_base_id', 0],
            ['url', ''],
            ['title', ''],
            ['auto_vectorize', 1],
        ]);

        $knowledgeBaseId = (int)$data['knowledge_base_id'];
        $url = trim((string)$data['url']);
        $title = trim((string)$data['title']);
        $autoVectorize = (int)$data['auto_vectorize'];

        if ($knowledgeBaseId <= 0) {
            return $this->fail('知识库不能为空');
        }
        if ($url === '') {
            return $this->fail('URL不能为空');
        }

        $knowledge = $this->service->importByUrl($knowledgeBaseId, $this->getMerchantId(), $url, $title);
        if ($autoVectorize === 1) {
            $this->service->batchVectorize([(int)$knowledge->id], $this->getMerchantId());
        }

        return $this->success('采集成功', $knowledge ? $knowledge->toArray() : []);
    }

    /**
     * 获取商户ID
     */
    protected function getMerchantId(): int
    {
        return (int)$this->merchantId;
    }
}
