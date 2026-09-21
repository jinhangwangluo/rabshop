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

namespace app\controller\mobile\user;

use app\BaseController;
use app\common\service\user\UserMemberService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户会员控制器
 */
class UserMember extends BaseController
{
    #[Inject]
    protected UserMemberService $service;

    /**
     * 获取会员信息
     *
     * GET /api/mobile/user/member/info
     */
    public function info(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);

        return $this->success('获取成功', $this->service->getMobileMemberInfo($userId));
    }

    /**
     * 获取会员套餐列表
     *
     * GET /api/mobile/user/member/packages
     */
    public function packages(): Response
    {
        return $this->success('获取成功', $this->service->getPackages());
    }

    /**
     * 创建会员订单并发起支付
     *
     * POST /api/mobile/user/member/create
     * 参数：package_id, pay_type, trade_type, return_url, terminal
     */
    public function create(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $packageId = (int)$this->request->post('package_id/d', 0);
        $payType = trim((string)$this->request->post('pay_type', 'wechat'));
        $tradeType = trim((string)$this->request->post('trade_type', ''));
        $returnUrl = trim((string)$this->request->post('return_url', ''));
        $terminal = trim((string)$this->request->post('terminal', ''));

        if ($packageId <= 0) {
            return $this->fail('请选择会员套餐');
        }

        $data = $this->service->createMemberOrder($userId, $packageId, [
            'pay_type' => $payType,
            'trade_type' => $tradeType,
            'return_url' => $returnUrl,
            'terminal' => $terminal,
        ]);

        return $this->success('会员支付发起成功', $data);
    }
}
