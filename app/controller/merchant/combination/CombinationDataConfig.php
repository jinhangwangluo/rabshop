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
use app\common\service\combination\CombinationDataConfigService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端组合数据配置控制器.
 *
 * 管理当前商户的组合数据配置、设计器 schema 和配置状态。
 */
class CombinationDataConfig extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait {
        index as traitIndex;
        save as traitSave;
        read as traitRead;
        update as traitUpdate;
        delete as traitDelete;
        show as traitShow;
    }

    /**
     * 组合数据配置服务.
     *
     * @var CombinationDataConfigService
     */
    #[Inject]
    protected CombinationDataConfigService $service;

    /**
     * 获取资源状态字段名.
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
            ['status', ''],
            ['keyword', ''],
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
            ['config_name', ''],
            ['config_desc', ''],
            ['config_key', ''],
            ['content', []],
            ['field_keys', []],
            ['fields', []],
            ['status', 1],
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
     * 获取当前商户组合数据配置列表.
     *
     * @return mixed
     */
    public function index()
    {
        $this->initMerchant();
        return $this->traitIndex();
    }

    /**
     * 获取当前商户组合数据配置详情.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function read($id)
    {
        $this->initMerchant();
        return $this->traitRead($id);
    }

    /**
     * 新增当前商户组合数据配置.
     *
     * @return mixed
     */
    public function save()
    {
        $this->initMerchant();
        return $this->traitSave();
    }

    /**
     * 更新当前商户组合数据配置.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function update($id)
    {
        $this->initMerchant();
        return $this->traitUpdate($id);
    }

    /**
     * 删除当前商户组合数据配置.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function delete($id)
    {
        $this->initMerchant();
        return $this->traitDelete($id);
    }

    /**
     * 修改当前商户组合数据配置状态.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function show($id)
    {
        $this->initMerchant();
        return $this->traitShow($id);
    }

    /**
     * 获取当前商户启用的配置下拉选项.
     *
     * @return Response
     */
    public function options(): Response
    {
        return $this->success($this->service->setMerchantId((int)$this->merchantId)->getOptions());
    }

}
