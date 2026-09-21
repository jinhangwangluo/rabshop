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
 * 商户端AI模型查询控制器（只读）
 */
class CsAiModel extends AuthController
{
    #[Inject]
    protected AiModelConfigService $service;

    /**
     * 获取模型列表（商户端只读）
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['type', ''],
            ['provider_id', 0],
            ['keyword', ''],
            ['status', -1],
            ['page', 1],
            ['limit', 100],
        ]);

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
}
