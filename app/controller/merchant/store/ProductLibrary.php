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
use app\common\service\store\ProductLibraryService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商品库管理控制器（商户端）
 *
 * 商户可添加商品进库（默认私有，可设共享）；可浏览使用平台商品 + 自己自建 + 其他商户共享的商品。
 * 所有查询强制归属过滤。
 */
class ProductLibrary extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 商品库服务
     */
    #[Inject]
    protected ProductLibraryService $service;

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
        return \app\validate\store\ProductLibraryValidate::class;
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
            ['product_code', ''],
            ['category_id', 0],
            ['brand_id', 0],
            ['is_share', ''],
            ['status', ''],
            ['create_time', ''],
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
            ['keyword', ''],
            ['bar_code', ''],
            ['category_id', 0],
            ['brand_id', 0],
            ['group_id', 0],
            ['spec_value_id', 0],
            ['price', 0],
            ['ot_price', 0],
            ['store_price', 0],
            ['stock', 0],
            ['unit', ''],
            ['goods_type', 1],
            ['cover', ''],
            ['pics', []],
            ['video', ''],
            ['description', ''],
            ['detail', ''],
            ['attr_values', []],
            ['service_ids', ''],
            ['freight_template_id', 0],
            ['gift_points', 0],
            ['first_commission_rate', 0],
            ['second_commission_rate', 0],
            ['status', 1],
            ['audit_status', 1],
            ['sort', 0],
            ['is_share', 0],
            ['plugin_code', ''],
            ['plugin_payload', []],
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
     * 列表（带商户可用范围过滤）
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
     * 添加库商品（商户自建，默认私有）
     *
     * @return Response 添加结果
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

    /**
     * 修改库商品
     *
     * @param mixed $id 主键
     * @return Response 修改结果
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }
        $data = $this->request->postMore($this->getRequestFields());
        $this->validateCheck($data);
        if ($this->service->resourceUpdate((int) $id, $data, (int) $this->merchantId)) {
            return $this->success('修改成功');
        }
        return $this->fail('修改失败');
    }

    /**
     * 删除库商品（仅归属商户自己）
     *
     * @param mixed $id 主键
     * @return Response 删除结果
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('删除的ID不能为空');
        }
        if ($this->service->resourceDelete((int) $id, (int) $this->merchantId)) {
            return $this->success('删除成功');
        }
        return $this->fail('删除失败');
    }

    /**
     * 设置共享状态（仅归属商户自己）
     *
     * @param int $id 库商品ID
     * @return Response 更新结果
     */
    public function share(int $id): Response
    {
        $isShare = (int) $this->request->post('is_share/d', 0);
        $this->service->share($id, $isShare, (int) $this->merchantId);
        return $this->success('设置成功');
    }

    /**
     * 选品选项（select 组件数据源，仅可见范围）
     *
     * @return Response 库商品选项列表
     */
    public function options(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['category_id', 0],
        ]);
        return $this->success('获取成功', $this->service->options($params, (int) $this->merchantId));
    }
}
