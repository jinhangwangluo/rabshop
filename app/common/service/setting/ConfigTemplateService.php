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

namespace app\common\service\setting;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\LiveConfigKeyEnum;
use app\common\model\setting\system\ConfigTemplate;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 配置模板服务类
 */
class ConfigTemplateService extends BaseService implements ResourceServiceInterfaceTrait
{

    use ResourceServiceTrait;

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return ConfigTemplate::class;
    }

    /**
     * 获取状态开启的配置模板下拉列表
     * @return array
     */
    public function getOptions(): array
    {
        return $this->getModel()->where('status', 1)->field('id, config_name')->select()->toArray();
    }

    /**
     * 检查配置键是否已存在
     * @param string $configKey
     * @return bool
     */
    public function checkConfigKey(string $configKey)
    {
        return (bool)$this->getModel()->where('config_key', $configKey)->count();
    }

    /**
     * 获取资源数据
     * @param int|array $id
     * @return array
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws DbException
     */
    public function resourceRead(int|array $id)
    {
        $model = $this->get($id);
        if (!$model) {
            throw new ModelNotFoundException('查询的数据不存在');
        }
        $resData = $model->toArray();

        $configService = app()->make(ConfigService::class);

        // 只读注入：仅当 field_keys 中声明了对应字段才计算并注入
        $fieldKeys = $model->field_keys;
        if (!is_array($fieldKeys)) {
            $fieldKeys = [];
        }
        $resData['field_keys'] = $fieldKeys;
        $resData['configData'] = $fieldKeys ? $configService->getMultiConfigVlaue($fieldKeys, false) : [];

        if (in_array(ConfigService::WECHAT_URL_KEY, $fieldKeys, true)) {
            $resData['configData'][ConfigService::WECHAT_URL_KEY] =
                $configService->getValue(ConfigKeyEnum::WEB_HOST) . '/api/mobile/wechat';
        }

        if (in_array('ali_callback_url', $fieldKeys, true)) {
            $webHost  = rtrim((string)$configService->getValue(ConfigKeyEnum::WEB_HOST), '/');
            $aliToken = (string)($configService->getValue(LiveConfigKeyEnum::ALI_CALLBACK_TOKEN) ?? '');
            $resData['configData']['ali_callback_url'] =
                $webHost . '/api/live/callback/aliyun' . ($aliToken ? '?token=' . $aliToken : '');
        }

        if (in_array('qiniu_callback_url', $fieldKeys, true)) {
            $webHost    = rtrim((string)$configService->getValue(ConfigKeyEnum::WEB_HOST), '/');
            $qiniuToken = (string)($configService->getValue(LiveConfigKeyEnum::QINIU_CALLBACK_TOKEN) ?? '');
            $resData['configData']['qiniu_callback_url'] =
                $webHost . '/api/live/callback/qiniu' . ($qiniuToken ? '?token=' . $qiniuToken : '');
        }

        return $resData;
    }
}
