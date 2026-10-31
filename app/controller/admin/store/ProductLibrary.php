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
use app\common\service\store\ProductLibraryService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商品库管理控制器（平台端）
 *
 * 库商品 = 单规格商品池，平台自建商品默认共享；
 * 支持 CRUD、共享设置、选品选项（options）。
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
            ['is_share', 1],
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
     * 设置共享状态
     *
     * @param int $id 库商品ID
     * @return Response 更新结果
     */
    public function share(int $id): Response
    {
        $isShare = (int) $this->request->post('is_share/d', 0);
        return $this->success('设置成功', $this->service->share($id, $isShare, 0) ? [] : []);
    }

    /**
     * 选品选项（select 组件数据源）
     *
     * @return Response 库商品选项列表
     */
    public function options(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['category_id', 0],
        ]);
        return $this->success('获取成功', $this->service->options($params, 0));
    }
}
