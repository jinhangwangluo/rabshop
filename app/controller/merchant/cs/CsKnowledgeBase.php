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

use app\common\service\cs\CsKnowledgeBaseService;
use app\controller\merchant\AuthController;
use Rabbit\util\StreamResponse;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端知识库实体控制器。
 *
 * 控制器只负责读取请求参数、基础校验和调用服务层，知识库业务规则统一放在 CsKnowledgeBaseService。
 */
class CsKnowledgeBase extends AuthController
{
    #[Inject]
    protected CsKnowledgeBaseService $service;

    /**
     * 列表
     */
    public function index(): Response
    {
        $this->initService();
        $params = $this->request->getMore([
            ['keyword', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 20],
        ]);

        $result = $this->service->getList(
            (int)$params['page'],
            (int)$params['limit'],
            trim((string)$params['keyword']),
            $params['status']
        );

        return $this->success('获取成功', $result);
    }

    /**
     * 详情
     */
    public function read(int $id): Response
    {
        $this->initService();
        $record = $this->service->getOneOrFail($id);

        return $this->success('获取成功', $record->toArray());
    }

    /**
     * 新增
     */
    public function save(): Response
    {
        $this->initService();
        $data = $this->request->postMore([
            ['name', ''],
            ['description', ''],
            ['cover', ''],
            ['ai_model_id', 0],
            ['status', 1],
            ['sort', 0],
            ['threshold', 0.6],
            ['search_limit', 5],
        ]);

        $base = $this->service->create([
            'name'         => trim((string)$data['name']),
            'description'  => trim((string)$data['description']),
            'cover'        => trim((string)$data['cover']),
            'ai_model_id'  => (int)$data['ai_model_id'],
            'status'       => (int)$data['status'],
            'sort'         => (int)$data['sort'],
            'threshold'    => (float)$data['threshold'],
            'search_limit' => (int)$data['search_limit'],
        ]);

        return $this->success('创建成功', $base->toArray());
    }

    /**
     * 更新
     */
    public function update(int $id): Response
    {
        $this->initService();
        $data = $this->request->postMore([
            ['name', ''],
            ['description', ''],
            ['cover', ''],
            ['ai_model_id', 0],
            ['status', 1],
            ['sort', 0],
            ['threshold', 0.6],
            ['search_limit', 5],
        ]);

        $this->service->updateBase($id, [
            'name'         => trim((string)$data['name']),
            'description'  => trim((string)$data['description']),
            'cover'        => trim((string)$data['cover']),
            'ai_model_id'  => (int)$data['ai_model_id'],
            'status'       => (int)$data['status'],
            'sort'         => (int)$data['sort'],
            'threshold'    => (float)$data['threshold'],
            'search_limit' => (int)$data['search_limit'],
        ]);

        return $this->success('更新成功');
    }

    /**
     * 删除
     */
    public function delete(int $id): Response
    {
        $this->initService();
        $this->service->deleteBase($id);

        return $this->success('删除成功');
    }

    /**
     * 切换状态
     */
    public function show(int $id): Response
    {
        $this->initService();
        $status = (int)$this->request->get('status', 1);
        $this->service->toggleStatus($id, $status);

        return $this->success('状态更新成功');
    }

    /**
     * 流式测试知识库回复。
     *
     * 控制器只负责参数校验和 SSE 输出，检索、Prompt 组装和 AI 调用由服务层完成。
     *
     * @param int $id 知识库ID
     * @return Response
     */
    public function testReply(int $id): Response
    {
        $question = trim((string)$this->request->post('question', ''));
        if ($question === '') {
            return $this->fail('请输入问题');
        }

        $this->initService();
        return StreamResponse::eventStreamResponse(function (callable $emit) use ($id, $question): void {
            $this->service->streamTestReply($id, $question, $emit);
        }, $this->request);
    }

    /**
     * 初始化服务归属商户
     */
    private function initService(): void
    {
        $this->service->setMerchantId((int)$this->merchantId);
    }
}
