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

namespace app\controller\admin\combination;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\combination\CombinationDataConfigService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 平台端组合数据配置控制器.
 *
 * 管理平台端组合数据配置、设计器 schema 和配置状态，平台端 merchant_id 固定为 0。
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
     * 获取平台端组合数据配置列表.
     *
     * @return mixed
     */
    public function index()
    {
        $this->service->setMerchantId(0);
        return $this->traitIndex();
    }

    /**
     * 获取平台端组合数据配置详情.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function read($id)
    {
        $this->service->setMerchantId(0);
        return $this->traitRead($id);
    }

    /**
     * 按配置标识获取平台端组合数据配置详情.
     *
     * @param string $configKey 配置标识
     * @return Response
     */
    public function readByKey(string $configKey): Response
    {
        return $this->success('获取成功', $this->service->setMerchantId(0)->getOwnedConfigByKey($configKey));
    }

    /**
     * 新增平台端组合数据配置.
     *
     * @return mixed
     */
    public function save()
    {
        $this->service->setMerchantId(0);
        return $this->traitSave();
    }

    /**
     * 更新平台端组合数据配置.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function update($id)
    {
        $this->service->setMerchantId(0);
        return $this->traitUpdate($id);
    }

    /**
     * 删除平台端组合数据配置.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function delete($id)
    {
        $this->service->setMerchantId(0);
        return $this->traitDelete($id);
    }

    /**
     * 修改平台端组合数据配置状态.
     *
     * @param mixed $id 配置ID
     * @return mixed
     */
    public function show($id)
    {
        $this->service->setMerchantId(0);
        return $this->traitShow($id);
    }

    /**
     * 获取启用的配置下拉选项.
     *
     * @return Response
     */
    public function options(): Response
    {
        return $this->success($this->service->setMerchantId(0)->getOptions());
    }
}
