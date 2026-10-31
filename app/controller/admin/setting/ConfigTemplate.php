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

namespace app\controller\admin\setting;

use app\common\exception\ApiValidateException;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\setting\ConfigTemplateAiService;
use app\common\service\setting\ConfigTemplateService;
use app\controller\admin\AuthController;
use Rabbit\util\StreamResponse;
use think\annotation\Inject;
use think\Response;

/**
 * 配置模板
 */
class ConfigTemplate extends AuthController implements ResourceControllerInterface
{

    use ResourceControllerTrait;

    /**
     * @var ConfigTemplateService
     */
    #[Inject]
    protected ConfigTemplateService $service;

    /**
     * @return string
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * @return string|null
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * @return array[]
     */
    protected function getIndexFields(): array
    {
        return [
            ['cate_id', ''],
            ['config_key', ''],
        ];
    }

    /**
     * @return array
     */
    protected function getRequestFields(): array
    {
        return [
            ['admin_id', $this->adminId],
            ['cate_id', ''],
            ['config_name', ''],
            ['config_desc', ''],
            ['config_key', ''],
            ['fields', []],
            ['status', ''],
        ];
    }

    /**
     * @return string[]
     */
    protected function getFormFields(): array
    {
        return ['id'];
    }

    /**
     * 保存前验证
     * @param array $data
     * @return void
     */
    protected function beforeSave(array $data)
    {
        if ($data['config_key'] && $this->service->checkConfigKey($data['config_key'])) {
            throw new ApiValidateException('配置键已存在');
        }
    }

    protected function beforeUpdate(array &$data)
    {
        $data['fields'] = is_string($data['fields']) ? $data['fields'] : json_encode($data['fields'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取状态开启的配置模板下拉选项
     * @return \think\Response
     */
    public function options(): \think\Response
    {
        return $this->success($this->service->getOptions());
    }

    /**
     * 保存模板
     * @return \think\Response
     */
    public function saveTemplate()
    {
        $data = $this->request->postMore([
            ['id', ''],
            ['content', ''],
            ['field_keys', ''],
        ]);

        if (!$data['id']) {
            return $this->fail('请选择要保存的模板');
        }
        if (!$data['content']) {
            return $this->fail('请配置模板内容');
        }
        if (!$data['field_keys']) {
            return $this->fail('请配置字段');
        }
        $this->service->update($data['id'], ['content' => $data['content'], 'field_keys' => $data['field_keys']]);

        return $this->success('保存成功');
    }

    /**
     * AI 流式生成配置模板设计草稿。
     *
     * @return Response
     */
    public function aiGenerate(): Response
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['prompt', ''],
            ['messages', []],
            ['current_fields', []],
        ]);

        if (!(int)$data['id']) {
            return $this->fail('请选择要配置的模板');
        }
        if (trim((string)$data['prompt']) === '') {
            return $this->fail('请输入配置需求');
        }

        return StreamResponse::eventStreamResponse(static function (callable $emit) use ($data): void {
            app()->make(ConfigTemplateAiService::class)->streamGenerate($data, $emit);
        }, $this->request);
    }
}
