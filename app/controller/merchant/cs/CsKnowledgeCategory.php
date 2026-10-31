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

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\cs\CsKnowledgeCategoryService;
use app\common\service\setting\CategoryService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端知识库分类控制器
 */
class CsKnowledgeCategory extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait {
        index as traitIndex;
        save as traitSave;
        read as traitRead;
        update as traitUpdate;
        delete as traitDelete;
        show as traitShow;
    }

    /**
     * 知识库分类服务
     */
    #[Inject]
    protected CsKnowledgeCategoryService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['name', ''],
            ['pid', ''],
            ['status', ''],
        ];
    }

    /**
     * POST 请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['sort', 0, '', 'd'],
            ['path', []],
            ['pid', 0],
            ['status', 0],
            ['other', ''],
        ];
    }

    /**
     * 获取表单的前置字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 列表
     */
    public function index()
    {
        $this->initServiceOwner();
        return $this->traitIndex();
    }

    /**
     * 详情
     */
    public function read($id)
    {
        $this->initServiceOwner();
        return $this->traitRead($id);
    }

    /**
     * 新增
     */
    public function save()
    {
        $this->initServiceOwner();
        return $this->traitSave();
    }

    /**
     * 修改
     */
    public function update($id)
    {
        $this->initServiceOwner();
        return $this->traitUpdate($id);
    }

    /**
     * 删除
     */
    public function delete($id)
    {
        $this->initServiceOwner();
        return $this->traitDelete($id);
    }

    /**
     * 状态切换
     */
    public function show($id)
    {
        $this->initServiceOwner();
        return $this->traitShow($id);
    }

    /**
     * 保存前处理
     */
    protected function beforeSave(mixed &$data): void
    {
        if (is_array($data)) {
            $data['owner_id'] = (int)$this->merchantId;
        }
    }

    /**
     * 更新前处理
     */
    protected function beforeUpdate(mixed &$data): void
    {
        if (is_array($data)) {
            $data['owner_id'] = (int)$this->merchantId;
        }
    }

    /**
     * 获取知识库分类树
     */
    public function tree(): Response
    {
        $tree = $this->service->getCategoryTree(CategoryService::KNOWLEDGE_TYPE, (int)$this->merchantId);
        return $this->success('获取成功', $tree);
    }

    /**
     * 快捷新增分类
     */
    public function quickAdd(): Response
    {
        $name = $this->request->post('name', '');
        $pid = (int)$this->request->post('pid', 0);
        $item = $this->service->quickAdd($name, $pid, (int)$this->merchantId);
        return $this->success('添加成功', $item);
    }

    /**
     * 初始化服务归属
     */
    protected function initServiceOwner(): void
    {
        $this->service->setOwnerId((int)$this->merchantId);
    }
}
