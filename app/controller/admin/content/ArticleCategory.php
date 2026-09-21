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

namespace app\controller\admin\content;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\setting\CategoryService;
use app\controller\admin\AuthController;
use app\common\service\content\ArticleCategoryService;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 文章分类控制器
 */
class ArticleCategory extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 文章分类服务
     */
    #[Inject]
    protected ArticleCategoryService $service;

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
        return 'app\common\validate\ArticleCategoryValidate';
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
     * post请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['sort', 0, '', 'd'],
            ['path', []],
            ['pid', 0],
            ['status', 0],
            ['other', '']
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
     * 获取二级分类树（用于级联选择器）
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function tree(): Response
    {
        $tree = $this->service->getCategoryTree(CategoryService::ARTICLE_TYPE);
        return $this->success('获取成功', $tree);
    }

    /**
     * 快捷添加分类
     */
    public function quickAdd(): Response
    {
        $name = $this->request->post('name', '');
        $pid = (int)$this->request->post('pid', 0);
        $item = $this->service->quickAdd($name, $pid);
        return $this->success('添加成功', $item);
    }
}
