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

namespace app\controller\admin\cs;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\cs\CsScriptCategoryService;
use app\common\service\setting\CategoryService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 话术分类控制器
 */
class CsScriptCategory extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 话术分类服务
     */
    #[Inject]
    protected CsScriptCategoryService $service;

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
     * POST请求获取的字段
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
     * 获取话术分类树（用于级联选择器）
     */
    public function tree(): Response
    {
        $tree = $this->service->getCategoryTree(CategoryService::SCRIPT_TYPE);
        return $this->success('获取成功', $tree);
    }

    /**
     * 快捷添加分类
     */
    public function quickAdd(): Response
    {
        $name = $this->request->post('name', '');
        $pid = (int)$this->request->post('pid', 0);
        $item = $this->service->quickAdd($name, $pid, 0);
        return $this->success('添加成功', $item);
    }

}
