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
use app\common\service\store\ProductAttributeService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商品参数模板控制器（商户端）
 */
class ProductAttribute extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected ProductAttributeService $service;

    /**
     * 状态字段
     *
     * @return string 状态字段名
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     *
     * @return string|null 验证器类名
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 列表搜索字段
     *
     * @return array 字段 => 默认值
     */
    protected function getIndexFields(): array
    {
        return [
            ['name', ''],
            ['is_required', ''],
            ['status', ''],
        ];
    }

    /**
     * 新增/编辑请求字段
     *
     * @return array 字段 => 默认值
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['category_ids', []],
            ['is_required', 0],
            ['sort', 0],
            ['status', 1],
            ['params', []],
        ];
    }

    /**
     * 表单初始化字段
     *
     * @return array 字段 => 默认值
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 列表（带归属过滤）
     *
     * @return Response 列表 + 分页
     */
    public function index()
    {
        $params = $this->request->getMore($this->getIndexFields());
        // 归属过滤：平台商品(0) + 自己自建(merchantId)，通过 _merchant_id 传入 Service
        $params['_merchant_id'] = (int) $this->merchantId;
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 添加属性（归属当前商户）
     *
     * @return Response 添加结果
     */
    public function save()
    {
        $data = $this->request->postMore($this->getRequestFields());
        $data['merchant_id'] = (int) $this->merchantId;
        $res = $this->service->resourceSave($data);
        if ($res) {
            return $this->success('添加成功', is_array($res) ? $res : []);
        }
        return $this->fail('添加失败');
    }

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

    public function read($id)
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }
        return $this->success($this->service->resourceRead((int) $id, (int) $this->merchantId));
    }

    public function update($id)
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }
        $data = $this->request->postMore($this->getRequestFields());
        $this->service->resourceUpdate((int) $id, $data, (int) $this->merchantId);
        return $this->success('修改成功');
    }

    public function delete($id)
    {
        if (!$id) {
            return $this->fail('删除的ID不能为空');
        }
        $this->service->resourceDelete((int) $id, (int) $this->merchantId);
        return $this->success('删除成功');
    }

    /**
     * 属性选项（select 组件数据源）
     *
     * @return Response 选项列表
     */
    public function options(): Response
    {
        return $this->success('获取成功', $this->service->options((int) $this->merchantId));
    }

    /**
     * 根据分类获取参数模板。
     */
    public function templateByCategory(int $categoryId): Response
    {
        return $this->success('获取成功', $this->service->findByCategoryId($categoryId, (int) $this->merchantId));
    }
}
