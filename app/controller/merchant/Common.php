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

namespace app\controller\merchant;

use app\common\enum\ConfigKeyEnum;
use app\common\service\setting\ConfigService;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端公共接口控制器
 * Class Common
 * @package app\controller\merchant
 */
class Common extends AuthController
{
    /**
     * @var ConfigService
     */
    #[Inject]
    protected ConfigService $configService;

    /**
     * 获取前端所需公共平台配置
     * @return Response
     */
    public function config(): Response
    {
        $data = $this->configService->getMultiConfigVlaue([
            ConfigKeyEnum::TENCENT_MAP_KEY,
        ]);
        return $this->success($data);
    }
}
