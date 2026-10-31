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
use app\common\service\store\ProductCategoryRelationService;
use app\common\service\store\StoreProductService;
use app\common\service\store\StoreProductSkuService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 店铺商品管理控制器（商户端）
 *
 * 商户运营自己的店铺商品：CRUD、上架组合、SKU 五项规则管理、虚拟统计维护。
 * 所有查询与操作强制归属当前商户。
 */
class StoreProduct extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 店铺商品服务
     */
    #[Inject]
    protected StoreProductService $service;

    /**
     * SKU 服务
     */
    #[Inject]
    protected StoreProductSkuService $skuService;

    /**
     * 分类关联服务
     */
    #[Inject]
    protected ProductCategoryRelationService $categoryRelationService;

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
        return \app\validate\store\StoreProductValidate::class;
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
            ['merchant_category_id', 0],
            ['brand_id', 0],
            ['spec_type', ''],
            ['goods_type', 0],
            ['status', ''],
            ['audit_status', ''],
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
            ['library_product_ids', []],
            ['library_skus', []],
            ['removed_library_product_ids', []],
            ['spec_mode', 'library'],
            ['custom_spec_type', 0],
            ['custom_specs', []],
            ['custom_skus', []],
            ['id', 0],
            ['name', ''],
            ['keyword', ''],
            ['category_id', 0],
            ['brand_id', 0],
            ['group_id', 0],
            ['goods_type', 1],
            ['cover', ''],
            ['pics', []],
            ['video', ''],
            ['description', ''],
            ['detail', ''],
            ['is_free_shipping', 0],
            ['attr_values', []],
            ['service_ids', ''],
            ['freight_template_id', 0],
            ['gift_points', 0],
            ['first_commission_rate', 0],
            ['second_commission_rate', 0],
            ['status', 1],
            ['audit_status', 1],
            ['sort', 0],
            ['is_collect', 0],
            ['weight', 0],
            ['plugin_payload', []],
            ['plugin_code', ''],
            ['category_ids', []],
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
     * 列表（强制归属当前商户）
     *
     * @return Response 列表 + 分页
     */
    public function index()
    {
        $params = $this->request->getMore($this->getIndexFields());
        // 归属过滤：只查询当前商户自己的店铺商品。
        $params['_merchant_id'] = (int) $this->merchantId;
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 添加店铺商品（单规格引用库商品）
     *
     * @return Response 添加结果
     */
    public function save()
    {
        $data = $this->appendParamFields($this->request->postMore($this->getRequestFields()));
        $this->validateCheck($data);
        $res = $this->service->resourceSave($data, (int) $this->merchantId);
        if ($res) {
            $this->saveCategoryRelations($data, (int) ($res->id ?? 0));
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
     * 修改店铺商品（含重新选品组合）
     *
     * @param mixed $id 主键
     * @return Response 修改结果
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail('修改的ID不能为空');
        }
        $data = $this->appendParamFields($this->request->postMore($this->getRequestFields()));
        // 编辑为部分字段更新，跳过全量校验（save 已校验，Service 层做业务校验）
        $hasCategoryIds = $this->request->post('category_ids', null) !== null;
        $updated = $this->service->resourceUpdate((int) $id, $data, (int) $this->merchantId);
        if ($updated || $hasCategoryIds) {
            $this->saveCategoryRelations($data, (int) $id);
            return $this->success('修改成功');
        }
        return $this->fail('修改失败');
    }

    /**
     * 删除店铺商品（仅归属商户）
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
     * 保存店铺商品分类关联（整体替换）
     *
     * @param array $data 请求数据
     * @param int $productId 店铺商品ID
     * @return void
     */
    protected function saveCategoryRelations(array $data, int $productId): void
    {
        if ($productId <= 0) {
            return;
        }
        $categoryIds = $this->request->post('category_ids', null);
        if ($categoryIds === null) {
            return;
        }
        $categoryIds = is_array($categoryIds) ? $categoryIds : [];
        $this->categoryRelationService->replaceRelations($productId, $categoryIds, (int) $this->merchantId);
    }

    /**
     * 仅在请求实际携带参数模板字段时合并，避免普通编辑误清空参数快照。
     *
     * @param array $data 请求数据
     * @return array
     */
    protected function appendParamFields(array $data): array
    {
        // 全局 API 中间件会移除标签；商品详情需要保留编辑器提交的原始 HTML。
        if ($this->request->has('detail', 'post')) {
            $data['detail'] = (string) $this->request->post('detail', '', null);
        } else {
            unset($data['detail']);
        }

        if ($this->request->has('param_attribute_id', 'post')) {
            $data['param_attribute_id'] = (int) $this->request->post('param_attribute_id/d', 0);
        }

        if ($this->request->has('param_snapshot', 'post')) {
            $paramSnapshot = $this->request->post('param_snapshot', []);
            if (is_string($paramSnapshot)) {
                $decoded = json_decode($paramSnapshot, true);
                $paramSnapshot = is_array($decoded) ? $decoded : [];
            }
            $data['param_snapshot'] = is_array($paramSnapshot) ? $paramSnapshot : [];
        }

        return $data;
    }

    /**
     * 上架组合（多选单规格库商品自动组合多规格店铺商品）★
     *
     * @return Response 组合结果
     */
    public function assemble(): Response
    {
        $data = $this->appendParamFields($this->request->postMore($this->getRequestFields()));
        $this->validate($data, \app\validate\store\StoreProductValidate::class);
        $result = $this->service->assembleFromLibrary((int) $this->merchantId, $data);
        $this->saveCategoryRelations($data, (int) ($result->id ?? 0));
        return $this->success('组合上架成功', [
            'id' => (int) ($result->id ?? 0),
        ]);
    }

    /**
     * SKU 列表（仅归属商户）
     *
     * @param int $id 店铺商品ID
     * @return Response SKU 列表
     */
    public function skuList(int $id): Response
    {
        $this->service->assertOperatable($id, (int) $this->merchantId);
        return $this->success('获取成功', $this->skuService->getSkuList($id));
    }

    /**
     * 保存 SKU（批量，判重 + 唯一值不可改校验）
     *
     * @param int $id 店铺商品ID
     * @return Response 保存结果
     */
    public function saveSku(int $id): Response
    {
        $skus = $this->request->post('skus/a', []);
        if (!is_array($skus)) {
            return $this->fail('SKU数据格式不正确');
        }

        $this->service->assertOperatable($id, (int) $this->merchantId);
        $this->service->saveSkuBatch($id, $skus);
        return $this->success('保存成功');
    }

    /**
     * 删除 SKU（软删，唯一值不释放）
     *
     * @param int $id 店铺商品ID
     * @param int $skuId SKU主键
     * @return Response 删除结果
     */
    public function deleteSku(int $id, int $skuId): Response
    {
        $this->service->assertOperatable($id, (int) $this->merchantId);
        $this->skuService->deleteSku($skuId, $id);
        $this->service->syncProductSnapshot($id);
        return $this->success('删除成功');
    }

    /**
     * SKU 隐藏/显示
     *
     * @param int $id 店铺商品ID
     * @param int $skuId SKU主键
     * @return Response 更新结果
     */
    public function skuStatus(int $id, int $skuId): Response
    {
        $status = (int) $this->request->post('status/d', 0);
        $this->service->assertOperatable($id, (int) $this->merchantId);
        $this->skuService->updateSkuStatus($skuId, $status, $id);
        $this->service->syncProductSnapshot($id);
        return $this->success('修改成功');
    }

    /**
     * SKU 排序
     *
     * @param int $id 店铺商品ID
     * @param int $skuId SKU主键
     * @return Response 更新结果
     */
    public function sortSku(int $id, int $skuId): Response
    {
        $sort = (int) $this->request->post('sort/d', 0);
        $this->service->assertOperatable($id, (int) $this->merchantId);
        $this->skuService->updateSkuSort($skuId, $sort, $id);
        return $this->success('修改成功');
    }

    /**
     * 维护虚拟展示统计基数（仅前台展示用）
     *
     * @param int $id 店铺商品ID
     * @return Response 更新结果
     */
    public function virtualStats(int $id): Response
    {
        $virtualSales = (int) $this->request->post('virtual_sales/d', 0);
        $virtualViewCount = (int) $this->request->post('virtual_view_count/d', 0);
        $this->service->assertOperatable($id, (int) $this->merchantId);
        $this->service->updateVirtualStats($id, $virtualSales, $virtualViewCount);
        return $this->success('修改成功');
    }

    /**
     * 商品选项（select 组件数据源，仅归属商户）
     *
     * @return Response 店铺商品选项列表
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
