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

use app\common\service\content\TagCategoryService;
use app\common\service\content\TagService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户商品标签分组控制器。
 *
 * 固定 tag_type=2，仅管理当前商户商品标签分组。
 */
class ProductTagCategory extends AuthController
{
    #[Inject]
    protected TagCategoryService $service;

    /**
     * 商品标签分组列表。
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['status', ''],
        ]);
        $params['tag_type'] = TagService::TAG_TYPE_PRODUCT;

        return $this->success($this->service->getListByOwner($params, (int)$this->merchantId));
    }

    /**
     * 商品标签分组树。
     */
    public function tree(): Response
    {
        return $this->success(
            '获取成功',
            $this->service->getTreeByTagType(TagService::TAG_TYPE_PRODUCT, (int)$this->merchantId)
        );
    }

    /**
     * 新增商品标签分组。
     */
    public function save(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $data['tag_type'] = TagService::TAG_TYPE_PRODUCT;
        return $this->success('添加成功', $this->service->saveCategory($data, (int)$this->merchantId));
    }

    /**
     * 读取商品标签分组详情。
     */
    public function read(int $id): Response
    {
        return $this->success(
            '获取成功',
            $this->service->readCategory($id, (int)$this->merchantId, TagService::TAG_TYPE_PRODUCT)
        );
    }

    /**
     * 更新商品标签分组。
     */
    public function update(int $id): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $data['tag_type'] = TagService::TAG_TYPE_PRODUCT;
        $this->service->saveCategory($data, (int)$this->merchantId, $id);
        return $this->success('修改成功');
    }

    /**
     * 删除商品标签分组。
     */
    public function delete(int $id): Response
    {
        $this->service->readCategory($id, (int)$this->merchantId, TagService::TAG_TYPE_PRODUCT);
        $this->service->deleteCategory($id, (int)$this->merchantId);
        return $this->success('删除成功');
    }

    /**
     * 修改商品标签分组状态。
     */
    public function show(int $id): Response
    {
        $status = (int)$this->request->get('status', 1);
        $this->service->readCategory($id, (int)$this->merchantId, TagService::TAG_TYPE_PRODUCT);
        $this->service->showCategory($id, $status, (int)$this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 快捷新增商品标签分组。
     */
    public function quickAdd(): Response
    {
        $name = (string)$this->request->post('name', '');
        $pid = (int)$this->request->post('pid', 0);
        return $this->success(
            '添加成功',
            $this->service->quickAdd($name, TagService::TAG_TYPE_PRODUCT, (int)$this->merchantId, $pid)
        );
    }

    /**
     * 商品标签分组允许提交字段。
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['tag_type', TagService::TAG_TYPE_PRODUCT],
            ['sort', 0],
            ['path', []],
            ['pid', 0],
            ['status', 1],
        ];
    }
}
