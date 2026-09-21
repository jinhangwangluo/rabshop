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
use app\common\service\store\FreightTemplateRuleService;
use app\common\service\store\FreightTemplateService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 运费模板控制器（商户端）
 *
 * 模板 CRUD + 默认模板互斥 + 指定地区规则子资源 + 发货时间/地区树选项。
 * 所有操作强制归属当前商户。
 */
class FreightTemplate extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 运费模板服务
     */
    #[Inject]
    protected FreightTemplateService $service;

    /**
     * 规则服务
     */
    #[Inject]
    protected FreightTemplateRuleService $ruleService;

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
        return \app\validate\store\FreightTemplateValidate::class;
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
            ['is_default', ''],
            ['charge_type', ''],
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
            ['delivery_time', ''],
            ['is_default', 0],
            ['is_free', 0],
            ['charge_type', 1],
            ['default_first_quantity', 1],
            ['default_first_amount', 0],
            ['default_continue_quantity', 0],
            ['default_continue_amount', 0],
            ['free_amount', 0],
            ['sort', 0],
            ['status', 1],
            ['rules', []],
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
        // 归属过滤：平台商品(0) + 自己自建(merchantId)，通过 _merchant_id 传入 Service
        $params['_merchant_id'] = (int) $this->merchantId;
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 添加模板（归属当前商户）
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
     * 修改模板
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
     * 删除模板（仅归属商户）
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
     * 设置默认模板（事务内互斥，仅归属商户）
     *
     * @param int $id 模板ID
     * @return Response 更新结果
     */
    public function setDefault(int $id): Response
    {
        $this->service->setDefault($id, (int) $this->merchantId);
        return $this->success('设置成功');
    }

    /**
     * 发货时间选项
     *
     * @return Response 选项列表
     */
    public function deliveryTimeOptions(): Response
    {
        return $this->success('获取成功', $this->service->deliveryTimeOptions());
    }

    /**
     * 地区树（指定地区规则选择器数据源）
     *
     * @return Response 树形地区数据
     */
    public function cityTree(): Response
    {
        return $this->success('获取成功', $this->service->cityTree());
    }

    /**
     * 模板规则列表（附带地区名称，仅归属商户）
     *
     * @param int $id 模板ID
     * @return Response 规则列表
     */
    public function rules(int $id): Response
    {
        $this->service->assertOwned($id, (int) $this->merchantId);
        return $this->success('获取成功', $this->ruleService->rulesWithCityNames($id));
    }

    /**
     * 运费模板选项（select 组件数据源，仅归属商户）
     *
     * @return Response 选项列表
     */
    public function options(): Response
    {
        return $this->success('获取成功', $this->service->options((int) $this->merchantId));
    }
}
