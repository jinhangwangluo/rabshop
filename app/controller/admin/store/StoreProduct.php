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
use app\common\service\store\ProductCategoryRelationService;
use app\common\service\store\ProductLibraryService;
use app\common\service\store\StoreProductService;
use app\common\service\store\StoreProductSkuService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 店铺商品管理控制器（平台端）
 *
 * 店铺商品 = 店铺运营副本：支持 CRUD、上架组合（assembleFromLibrary）、
 * SKU 五项规则管理（列表/保存/删除/隐藏/排序）、虚拟统计维护。
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
     * 商品库服务。
     */
    #[Inject]
    protected ProductLibraryService $libraryService;

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
            ['include_child_categories', 0],
            ['brand_id', 0],
            ['tag_id', 0],
            ['spec_type', ''],
            ['goods_type', 0],
            ['status', ''],
            ['audit_status', ''],
            ['source_type', 'all'],
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
            ['tag_ids', []],
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
     * 保存后事件：保存分类关联
     *
     * @param array $data 请求数据
     * @param int $id 主键
     * @param mixed $res 保存结果
     * @return void
     */
    protected function afterSave(array &$data, int $id, mixed $res): void
    {
        $productId = (int) ($data['id'] ?? 0);
        if ($productId <= 0 && is_object($res)) {
            $productId = (int) ($res->id ?? 0);
        }
        $this->saveCategoryRelations($data, $productId);
    }

    /**
     * 更新后事件：保存分类关联
     *
     * @param array $data 请求数据
     * @param int $id 主键
     * @param mixed $res 更新结果
     * @return void
     */
    protected function afterUpdate(array &$data, int $id, mixed $res): void
    {
        $this->saveCategoryRelations($data, $id);
    }

    /**
     * 添加店铺商品。
     *
     * @return Response 添加结果
     */
    public function save(): Response
    {
        $data = $this->appendParamFields($this->request->postMore($this->getRequestFields()));
        $this->validateCheck($data);

        $this->eventMethod(event: 'save', data: $data);
        $res = $this->service->resourceSave($data);
        if ($res) {
            $this->eventMethod(event: 'save', eventType: 'after', data: $data, res: $res);
            return $this->success($this->message['save']['success'], is_array($res) ? $res : []);
        }

        $this->eventMethod(event: 'save', eventType: 'after', data: $data, res: $res);
        return $this->fail($this->message['save']['fail']);
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
        $categoryIds = is_array($data['category_ids'] ?? null) ? $data['category_ids'] : [];
        if ($categoryIds !== []) {
            $this->categoryRelationService->replaceRelations($productId, $categoryIds);
        }
    }

    /**
     * 仅在请求实际携带参数模板字段时合并，避免普通编辑误清空参数快照。
     *
     * @param array $data 请求数据
     * @return array
     */
    protected function appendParamFields(array $data): array
    {
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
     * 采集第三方商品并写入商品库。
     *
     * @return Response 采集结果。
     */
    public function collect(): Response
    {
        $url = trim((string) $this->request->post('url/s', ''));
        if ($url === '') {
            $url = trim((string) $this->request->post('num_iid/s', ''));
        }

        return $this->success('采集成功', $this->libraryService->collectFromExternal($url));
    }

    /**
     * 修改店铺商品（部分字段更新，name 可不传）
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
        $this->validate($data, \app\validate\store\StoreProductValidate::class . '.update');
        $this->eventMethod(event: 'update', data: $data, id: (int) $id);
        if ($this->service->resourceUpdate((int) $id, $data, 0)) {
            $this->eventMethod(event: 'update', eventType: 'after', data: $data, id: (int) $id, res: true);
            return $this->success('修改成功');
        }
        $this->eventMethod(event: 'update', eventType: 'after', data: $data, id: (int) $id, res: false);
        return $this->fail('修改失败');
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
        $result = $this->service->assembleFromLibrary(0, $data);
        return $this->success('组合上架成功', [
            'id' => (int) ($result->id ?? 0),
        ]);
    }

    /**
     * 审核商户商品。
     *
     * @param int $id 商品 ID
     * @return Response 审核结果
     */
    public function audit(int $id): Response
    {
        $auditStatus = (int) $this->request->post('audit_status/d', 0);
        $this->service->auditMerchantProduct($id, $auditStatus);
        return $this->success('审核操作成功');
    }

    /**
     * SKU 列表
     *
     * @param int $id 店铺商品ID
     * @return Response SKU 列表
     */
    public function skuList(int $id): Response
    {
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
        $this->service->updateVirtualStats($id, $virtualSales, $virtualViewCount);
        return $this->success('修改成功');
    }

    /**
     * 商品选项（select 组件数据源）
     *
     * @return Response 店铺商品选项列表
     */
    public function options(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['category_id', 0],
            ['include_child_categories', 0],
            ['brand_id', 0],
            ['tag_id', 0],
            ['platform_only', 0],
        ]);
        return $this->success('获取成功', $this->service->options($params, 0));
    }
}
