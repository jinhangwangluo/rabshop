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

namespace app\controller\merchant\cs;

use app\common\service\cs\AiModelConfigService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端 AI 模型配置控制器。
 */
class CsAiModelConfig extends AuthController
{
    /**
     * @var AiModelConfigService
     */
    #[Inject]
    protected AiModelConfigService $service;

    /**
     * 服务商列表
     */
    public function providerList(): Response
    {
        $params = $this->request->getMore($this->getProviderListFields());

        $result = $this->service->getProviderList(
            trim((string)$params['type']),
            trim((string)$params['keyword']),
            $params['status'],
            (int)$params['page'],
            (int)$params['limit'],
            (int)$this->merchantId
        );

        return $this->success('获取成功', $result);
    }

    /**
     * 创建服务商
     */
    public function providerCreate(): Response
    {
        $data = $this->request->postMore($this->getProviderCreateFields());

        $provider = $this->service->createProvider($data, (int)$this->merchantId);
        return $this->success('创建成功', $provider->toArray());
    }

    /**
     * 更新服务商
     */
    public function providerUpdate(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $data = $this->request->postMore($this->getProviderUpdateFields());

        $this->service->updateProvider($id, $this->filterNullableData($data), (int)$this->merchantId);
        return $this->success('更新成功');
    }

    /**
     * 更新服务商状态
     */
    public function providerStatus(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $status = (int)$this->request->post('status/d', -1);
        $this->service->updateProviderStatus($id, $status, (int)$this->merchantId);
        return $this->success('状态更新成功');
    }

    /**
     * 删除服务商
     */
    public function providerDelete(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->deleteProvider($id, (int)$this->merchantId);
        return $this->success('删除成功');
    }

    /**
     * 模型类型选项
     */
    public function modelTypeOptions(): Response
    {
        return $this->success('获取成功', $this->service->getTypeOptions());
    }

    /**
     * 模型列表
     */
    public function modelList(): Response
    {
        $params = $this->request->getMore($this->getModelListFields());

        $result = $this->service->getModelList(
            trim((string)$params['type']),
            (int)$params['provider_id'],
            trim((string)$params['keyword']),
            $params['status'],
            (int)$params['page'],
            (int)$params['limit'],
            (int)$this->merchantId
        );

        return $this->success('获取成功', $result);
    }

    /**
     * 创建模型
     */
    public function modelCreate(): Response
    {
        $data = $this->request->postMore($this->getModelCreateFields());

        $model = $this->service->createModel($data, (int)$this->merchantId);
        return $this->success('创建成功', $model->toArray());
    }

    /**
     * 更新模型
     */
    public function modelUpdate(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $data = $this->request->postMore($this->getModelUpdateFields());

        $this->service->updateModel($id, $this->filterNullableData($data), (int)$this->merchantId);
        return $this->success('更新成功');
    }

    /**
     * 更新模型状态
     */
    public function modelStatus(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $status = (int)$this->request->post('status/d', -1);
        $this->service->updateModelStatus($id, $status, (int)$this->merchantId);
        return $this->success('状态更新成功');
    }

    /**
     * 设置默认模型
     */
    public function modelDefault(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->setDefaultModel($id, (int)$this->merchantId);
        return $this->success('默认模型设置成功');
    }

    /**
     * 删除模型
     */
    public function modelDelete(int $id): Response
    {
        if ($id <= 0) {
            return $this->fail('ID不能为空');
        }

        $this->service->deleteModel($id, (int)$this->merchantId);
        return $this->success('删除成功');
    }

    protected function getProviderListFields(): array
    {
        return [
            ['type', ''],
            ['keyword', ''],
            ['status', -1],
            ['page', 1],
            ['limit', 20],
        ];
    }

    protected function getProviderCreateFields(): array
    {
        return [
            ['type', ''],
            ['provider_code', ''],
            ['provider_name', ''],
            ['driver', 'openai_compatible'],
            ['base_url', ''],
            ['api_key', ''],
            ['status', 1],
            ['sort', 0],
        ];
    }

    protected function getProviderUpdateFields(): array
    {
        return [
            ['type', null],
            ['provider_code', null],
            ['provider_name', null],
            ['driver', null],
            ['base_url', null],
            ['api_key', null],
            ['status', null],
            ['sort', null],
        ];
    }

    protected function getModelListFields(): array
    {
        return [
            ['type', ''],
            ['provider_id', 0],
            ['keyword', ''],
            ['status', -1],
            ['page', 1],
            ['limit', 20],
        ];
    }

    protected function getModelCreateFields(): array
    {
        return [
            ['provider_id', 0],
            ['type', ''],
            ['model_id', ''],
            ['model_name', ''],
            ['params', []],
            ['timeout', 30],
            ['is_default', 0],
            ['status', 1],
            ['sort', 0],
        ];
    }

    protected function getModelUpdateFields(): array
    {
        return [
            ['provider_id', null],
            ['model_id', null],
            ['model_name', null],
            ['params', null],
            ['timeout', null],
            ['is_default', null],
            ['status', null],
            ['sort', null],
        ];
    }

    protected function filterNullableData(array $data): array
    {
        $saveData = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $saveData[$key] = $value;
        }

        return $saveData;
    }
}
