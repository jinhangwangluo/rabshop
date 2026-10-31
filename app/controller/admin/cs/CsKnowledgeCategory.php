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

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\cs\CsKnowledgeCategoryService;
use app\common\service\setting\CategoryService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 知识库分类控制器
 */
class CsKnowledgeCategory extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

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
     * POST请求字段
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
     * 表单前置字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 保存前处理
     */
    protected function beforeSave(mixed &$data): void
    {
        if (is_array($data)) {
            $data['owner_id'] = 0;
        }
    }

    /**
     * 更新前处理
     */
    protected function beforeUpdate(mixed &$data): void
    {
        if (is_array($data)) {
            $data['owner_id'] = 0;
        }
    }

    /**
     * 获取知识库分类树
     */
    public function tree(): Response
    {
        $tree = $this->service->getCategoryTree(CategoryService::KNOWLEDGE_TYPE);
        return $this->success('获取成功', $tree);
    }

    /**
     * 快捷新增分类
     */
    public function quickAdd(): Response
    {
        $name = (string)$this->request->post('name', '');
        $pid = (int)$this->request->post('pid', 0);
        $item = $this->service->quickAdd($name, $pid, 0);

        return $this->success('添加成功', $item);
    }
}
