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
use app\common\service\store\ProductGroupService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商品分组控制器（平台端）
 *
 * 商品运营分组（热卖/新品），店铺商品通过 group_id 关联。
 */
class ProductGroup extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 分组服务
     */
    #[Inject]
    protected ProductGroupService $service;

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
            ['merchant_id', 0],
            ['name', ''],
            ['sort', 0],
            ['status', 1],
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
     * 分组选项（select 组件数据源）
     *
     * @return Response 选项列表
     */
    public function options(): Response
    {
        return $this->success('获取成功', $this->service->options(0));
    }
}
