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

namespace app\controller\merchant\content;

use app\common\service\content\TagCategoryService;
use app\common\service\content\TagService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户标签分组控制器。
 *
 * 商户端标签分组固定使用当前 merchantId 作为 owner_id，只能管理商户自己的分组。
 */
class TagCategory extends AuthController
{
    #[Inject]
    protected TagCategoryService $service;

    /**
     * 获取商户标签分组列表。
     *
     * @return Response 返回 list 树形分组列表和 total 总数。
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['tag_type', TagService::TAG_TYPE_CONTENT],
            ['status', ''],
        ]);

        return $this->success($this->service->getListByOwner($params, (int)$this->merchantId));
    }

    /**
     * 获取商户标签分组树。
     *
     * @return Response 返回分组树，GET 参数 tag_type：1=内容/专题标签，2=商品标签。
     */
    public function tree(): Response
    {
        $tagType = (int)$this->request->get('tag_type', TagService::TAG_TYPE_CONTENT);
        return $this->success('获取成功', $this->service->getTreeByTagType($tagType, (int)$this->merchantId));
    }

    /**
     * 新增商户标签分组。
     *
     * @return Response 返回新增后的分组数据。
     */
    public function save(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        return $this->success('添加成功', $this->service->saveCategory($data, (int)$this->merchantId));
    }

    /**
     * 读取商户标签分组详情。
     *
     * @param int $id 分组 ID。
     * @return Response 返回分组详情，包含 tag_type。
     */
    public function read(int $id): Response
    {
        return $this->success('获取成功', $this->service->readCategory($id, (int)$this->merchantId));
    }

    /**
     * 更新商户标签分组。
     *
     * @param int $id 分组 ID。
     * @return Response 返回修改结果。
     */
    public function update(int $id): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $this->service->saveCategory($data, (int)$this->merchantId, $id);
        return $this->success('修改成功');
    }

    /**
     * 删除商户标签分组。
     *
     * @param int $id 分组 ID。
     * @return Response 返回删除结果；存在子分组或标签时由 Service 拒绝删除。
     */
    public function delete(int $id): Response
    {
        $this->service->deleteCategory($id, (int)$this->merchantId);
        return $this->success('删除成功');
    }

    /**
     * 修改商户标签分组状态。
     *
     * @param int $id 分组 ID。
     * @return Response 返回状态修改结果，GET 参数 status：0=禁用，1=启用。
     */
    public function show(int $id): Response
    {
        $status = (int)$this->request->get('status', 1);
        $this->service->showCategory($id, $status, (int)$this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 快捷新增商户标签分组。
     *
     * @return Response 返回适合前端选择器使用的分组选项：id、value、label、pid。
     */
    public function quickAdd(): Response
    {
        $name = (string)$this->request->post('name', '');
        $pid = (int)$this->request->post('pid', 0);
        $tagType = (int)$this->request->post('tag_type', TagService::TAG_TYPE_CONTENT);
        return $this->success('添加成功', $this->service->quickAdd($name, $tagType, (int)$this->merchantId, $pid));
    }

    /**
     * 获取新增/编辑商户标签分组允许提交的字段。
     *
     * @return array 请求字段配置：name、tag_type、sort、path、pid、status。
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['tag_type', TagService::TAG_TYPE_CONTENT],
            ['sort', 0],
            ['path', []],
            ['pid', 0],
            ['status', 1],
        ];
    }
}
