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

use app\common\service\cs\CsScriptService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 客服话术控制器
 */
class CsScript extends AuthController
{
    /**
     * @var CsScriptService
     */
    #[Inject]
    protected CsScriptService $service;

    /**
     * 获取话术列表
     */
    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());

        $result = $this->service->getScriptList(
            $this->getMerchantId(),
            (int)$params['category_id'],
            trim((string)$params['keyword']),
            (int)$params['page'],
            (int)$params['limit']
        );

        return $this->success('获取成功', $result);
    }

    /**
     * 创建话术
     */
    public function create(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());

        if (trim((string)$data['title']) === '') {
            return $this->fail('标题不能为空');
        }
        if (trim((string)$data['content']) === '') {
            return $this->fail('内容不能为空');
        }

        $script = $this->service->createScript(
            (int)$data['category_id'],
            $this->getMerchantId(),
            trim((string)$data['title']),
            trim((string)$data['content']),
            trim((string)$data['shortcut'])
        );

        if ((int)$data['status'] !== 1 || (int)$data['sort'] !== 0) {
            $this->service->update((int)$script->id, [
                'status' => (int)$data['status'],
                'sort' => (int)$data['sort'],
            ]);
            $script = $this->service->getScriptById((int)$script->id, $this->getMerchantId());
        }

        return $this->success('保存成功', $script->toArray());
    }

    /**
     * 更新话术
     */
    public function update(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $data = $this->request->postMore($this->getRequestFields());
        if (trim((string)$data['title']) === '') {
            return $this->fail('标题不能为空');
        }
        if (trim((string)$data['content']) === '') {
            return $this->fail('内容不能为空');
        }

        $this->service->update($id, [
            'category_id' => (int)$data['category_id'],
            'title' => trim((string)$data['title']),
            'content' => trim((string)$data['content']),
            'shortcut' => trim((string)$data['shortcut']),
            'status' => (int)$data['status'],
            'sort' => (int)$data['sort'],
        ]);

        return $this->success('修改成功');
    }

    /**
     * 删除话术
     */
    public function delete(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->deleteScript($id);
        return $this->success('删除成功');
    }

    /**
     * 更新话术状态
     */
    public function updateStatus(): Response
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['status', 0],
        ]);

        $id = (int)$data['id'];
        $status = (int)$data['status'];

        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        if (!in_array($status, [0, 1], true)) {
            return $this->fail('状态值不正确');
        }

        $this->service->update($id, ['status' => $status]);
        return $this->success('状态更新成功');
    }

    /**
     * 批量删除话术
     */
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids/a', []);
        if ($ids === []) {
            return $this->fail('请选择要删除的话术');
        }

        if (!$this->service->batchDelete(array_map('intval', $ids))) {
            return $this->fail('删除失败');
        }

        return $this->success('删除成功');
    }

    /**
     * 搜索话术
     */
    public function search(): Response
    {
        $keyword = trim((string)$this->request->get('keyword', ''));
        if ($keyword === '') {
            return $this->fail('关键词不能为空');
        }

        return $this->success('获取成功', $this->service->searchScript($this->getMerchantId(), $keyword));
    }

    /**
     * 获取列表筛选字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['category_id', 0],
            ['keyword', ''],
            ['page', 1],
            ['limit', 20],
        ];
    }

    /**
     * 获取请求字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['category_id', 0],
            ['title', ''],
            ['content', ''],
            ['shortcut', ''],
            ['status', 1],
            ['sort', 0],
        ];
    }

    /**
     * 获取商户ID
     */
    protected function getMerchantId(): int
    {
        return (int)$this->merchantId;
    }
}
