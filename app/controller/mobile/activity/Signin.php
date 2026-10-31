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

namespace app\controller\mobile\activity;

use app\BaseController;
use app\common\service\activity\ActivitySigninService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端每日签到控制器。
 *
 * 仅负责读取登录用户、基础参数校验和返回统一响应；签到规则、积分发放和任务判断放在服务层。
 */
class Signin extends BaseController
{
    /**
     * 签到服务。
     *
     * @var ActivitySigninService
     */
    #[Inject]
    protected ActivitySigninService $service;

    /**
     * 获取签到页数据。
     *
     * GET /api/mobile/activity/signin/index
     *
     * @return Response 返回签到状态、7天积分进度、用户积分余额和每日任务列表。
     */
    public function index(): Response
    {
        return $this->success('获取成功', $this->service->getMobileIndex($this->getLoginUserId()));
    }

    /**
     * 执行今日签到。
     *
     * POST /api/mobile/activity/signin/sign
     *
     * @return Response 签到成功后返回最新页面数据和本次获得积分。
     */
    public function sign(): Response
    {
        return $this->success('签到成功', $this->service->signIn($this->getLoginUserId()));
    }

    /**
     * 领取签到任务积分。
     *
     * POST /api/mobile/activity/signin/task/claim
     * 参数：task_key，见 ActivitySigninService::TASK_* 常量。
     *
     * @return Response 领取成功后返回最新页面数据和本次任务获得积分。
     */
    public function claimTask(): Response
    {
        $taskKey = trim((string)$this->request->post('task_key', ''));
        if ($taskKey === '') {
            return $this->fail('任务标识不能为空');
        }

        return $this->success('领取成功', $this->service->claimTask($this->getLoginUserId(), $taskKey));
    }

    /**
     * 获取当前登录用户ID。
     *
     * AuthMobileLoginMiddleware 会把用户ID挂到 request->user_id。
     */
    protected function getLoginUserId(): int
    {
        return (int)($this->request->user_id ?? 0);
    }
}
