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

namespace app\controller\merchant\store;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\store\ProductCategoryService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商品分类控制器（商户端）
 *
 * 平台分类只读用于商品 category_id；商户分类 CRUD 仅用于后台内部 category_ids。
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
     * 新增/编辑请求字段
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
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 商户分类列表（分页）
     */
    public function index()
    {
        $params = $this->request->getMore($this->getIndexFields());
        $params['_owner_id'] = (int) $this->merchantId;
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 添加商户自定义分类
     */
    public function save()
    {
        $data = $this->request->postMore($this->getRequestFields());
        $this->validateCheck($data);

        $res = $this->service->resourceSave($data, (int) $this->merchantId);
        if ($res) {
            return $this->success('添加成功', is_array($res) ? $res : []);
        }
        return $this->fail('添加失败');
    }

    /**
     * 修改商户自定义分类状态
     */
    public function show($id)
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }
        $status = $this->request->get('status');
        if ($status === null) {
            return $this->fail('修改状态值不存在');
        }

        $this->service->resourceShowUpdate((int) $id, [$this->getStatusField() => $status], (int) $this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 商户自定义分类详情
     */
    public function read($id)
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }

        return $this->success($this->service->resourceRead((int) $id, (int) $this->merchantId));
    }

    /**
     * 修改商户自定义分类
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }

        $data = $this->request->postMore($this->getRequestFields());
        $this->validateCheck($data);
        $this->service->resourceUpdate((int) $id, $data, (int) $this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 删除商户自定义分类
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('删除的ID不能为空');
        }

        $this->service->resourceDelete((int) $id, (int) $this->merchantId);
        return $this->success('删除成功');
    }

    /**
     * 平台商品分类树（只读）
     */
    public function platformTree(): Response
    {
        return $this->success('获取成功', $this->service->getProductCategoryTree(0));
    }

    /**
     * 平台商品分类列表（只读）
     */
    public function platformList(): Response
    {
        return $this->success('获取成功', $this->service->getProductCategoryList([], 0));
    }

    /**
     * 商户自定义商品分类树
     *
     * @return Response 树形分类数据
     */
    public function tree(): Response
    {
        return $this->success('获取成功', $this->service->getProductCategoryTree((int) $this->merchantId));
    }

    /**
     * 商户自定义商品分类列表
     *
     * @return Response 分类列表
     */
    public function list(): Response
    {
        return $this->success('获取成功', $this->service->getProductCategoryList([], (int) $this->merchantId));
    }
}
