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

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\store\ProductCategoryService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商品分类控制器（平台端）
 *
 * 复用通用 category 表（type=10），提供分类管理（增删改查/状态）与分类树/列表。
 */
class ProductCategory extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 商品分类服务
     */
    #[Inject]
    protected ProductCategoryService $service;

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
        return 'app\common\validate\ProductCategoryValidate';
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
            ['image', ''],
            ['banner_image', ''],
            ['sort', 0, '', 'd'],
            ['path', []],
            ['pid', 0],
            ['status', 0],
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
     * 商品分类树
     *
     * @return Response 树形分类数据
     */
    public function tree(): Response
    {
        $ownerId = (int) $this->request->get('owner_id/d', 0);
        return $this->success('获取成功', $this->service->getProductCategoryTree($ownerId));
    }

    /**
     * 商品分类列表
     *
     * @return Response 分类列表
     */
    public function list(): Response
    {
        $ownerId = (int) $this->request->get('owner_id/d', 0);
        return $this->success('获取成功', $this->service->getProductCategoryList([], $ownerId));
    }
}
