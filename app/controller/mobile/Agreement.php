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

namespace app\controller\mobile;

use app\BaseController;
use app\common\service\agreement\AgreementService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端协议控制器
 */
class Agreement extends BaseController
{
    #[Inject]
    protected AgreementService $service;

    /**
     * 获取协议详情
     *
     * GET /api/mobile/agreement/detail?type=member
     */
    public function detail(): Response
    {
        $type = trim((string)$this->request->get('type', ''));

        if ($type === '') {
            return $this->fail('协议类型不能为空');
        }

        return $this->success('获取成功', $this->service->getByType($type));
    }
}
