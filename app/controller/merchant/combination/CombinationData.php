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

namespace app\controller\merchant\combination;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\combination\CombinationDataService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端组合数据控制器.
 *
 * 按组合数据配置动态保存和读取当前商户数据。
 */
class CombinationData extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait {
        index as traitIndex;
        save as traitSave;
        read as traitRead;
        update as traitUpdate;
        delete as traitDelete;
    }

    /**
     * 组合数据服务.
     *
     * @var CombinationDataService
     */
    #[Inject]
    protected CombinationDataService $service;

    /**
     * 获取状态字段名.
     *
     * @return string
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 获取验证器类名.
     *
     * @return string|null
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 获取列表查询字段.
     *
     * @return array
     */
    protected function getIndexFields(): array
    {
        return [
            ['category_id', ''],
            ['config_id', ''],
            ['data_type', ''],
            ['status', ''],
        ];
    }

    /**
     * 获取新增/编辑请求字段.
     *
     * @return array
     */
    protected function getRequestFields(): array
    {
        return [
            ['category_id', 0],
            ['config_id', 0],
            ['sort', 0],
            ['status', 1],
            ['form_data', []],
            ['data_json', []],
        ];
    }

    /**
     * 获取表单前置字段.
     *
     * @return array
     */
    protected function getFormFields(): array
    {
        return ['id'];
    }

    /**
     * 初始化服务商户归属.
     *
     * @return void
     */
    protected function initMerchant(): void
    {
        $this->service->setMerchantId((int)$this->merchantId);
    }

    /**
     * 获取当前商户组合数据列表.
     *
     * @return mixed
     */
    public function index()
    {
        $this->initMerchant();
        return $this->traitIndex();
    }

    /**
     * 获取当前商户组合数据详情.
     *
     * @param mixed $id 数据ID
     * @return mixed
     */
    public function read($id)
    {
        $this->initMerchant();
        return $this->traitRead($id);
    }

    /**
     * 新增当前商户组合数据.
     *
     * @return mixed
     */
    public function save()
    {
        $this->initMerchant();
        return $this->traitSave();
    }

    /**
     * 更新当前商户组合数据.
     *
     * @param mixed $id 数据ID
     * @return mixed
     */
    public function update($id)
    {
        $this->initMerchant();
        return $this->traitUpdate($id);
    }

    /**
     * 删除当前商户组合数据.
     *
     * @param mixed $id 数据ID
     * @return mixed
     */
    public function delete($id)
    {
        $this->initMerchant();
        return $this->traitDelete($id);
    }

    /**
     * 获取指定配置的动态表单 schema.
     *
     * @param int $configId 配置ID
     * @return Response
     */
    public function schema(int $configId): Response
    {
        return $this->success($this->service->setMerchantId((int)$this->merchantId)->getSchema($configId));
    }
}
