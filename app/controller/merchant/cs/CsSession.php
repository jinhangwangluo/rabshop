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

use app\common\service\cs\CsMessageService;
use app\common\service\cs\CsSessionService;
use app\common\service\cs\CsStaffService;
use app\controller\merchant\AuthController;
use Rabbit\service\websocket\PushNotification;
use think\annotation\Inject;
use think\Response;

/**
 * 客服会话控制器
 */
class CsSession extends AuthController
{
    /**
     * @var CsSessionService
     */
    #[Inject]
    protected CsSessionService $service;

    /**
     * 获取会话列表
     */
    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());

        return $this->success('获取成功', $this->service->getSessionList($params, $this->getMerchantId()));
    }

    /**
     * 获取会话详情
     */
    public function read(): Response
    {
        $sessionId = (string)$this->request->get('session_id', '');
        if ($sessionId === '') {
            return $this->fail('会话ID不能为空');
        }

        $session = $this->service->getSessionBySessionId($sessionId, $this->getMerchantId(), ['user', 'staff']);
        if (!$session) {
            return $this->fail('会话不存在');
        }

        return $this->success('获取成功', $session->toArray());
    }

    /**
     * 接入会话
     */
    public function accept(): Response
    {
        $data = $this->request->postMore([
            ['session_id', ''],
            ['staff_id', 0],
        ]);

        if ($data['session_id'] === '' || (int)$data['staff_id'] <= 0) {
            return $this->fail('参数错误');
        }

        $sessionId = (string)$data['session_id'];
        $staffId = (int)$data['staff_id'];
        $this->service->assignStaff($sessionId, $staffId);
        PushNotification::notifyCsNewSession($sessionId, $staffId);

        return $this->success('接入成功');
    }

    /**
     * 转接会话
     */
    public function transfer(): Response
    {
        $data = $this->request->postMore([
            ['session_id', ''],
            ['to_staff_id', 0],
            ['remark', ''],
        ]);

        if ($data['session_id'] === '' || (int)$data['to_staff_id'] <= 0) {
            return $this->fail('参数错误');
        }

        $staff = $this->getCurrentStaff();
        if (!$staff) {
            return $this->fail('当前管理员未绑定客服');
        }

 
            $sessionId = (string)$data['session_id'];
            $fromStaffId = (int)$staff->id;
            $toStaffId = (int)$data['to_staff_id'];
            $remark = trim((string)$data['remark']);

            $this->service->transfer($sessionId, $fromStaffId, $toStaffId, $remark);
            PushNotification::notifyCsTransfer($sessionId, $fromStaffId, $toStaffId, $remark);
            PushNotification::notifyCsNewSession($sessionId, $toStaffId);

            return $this->success('转接成功');
   
    }

    /**
     * 结束会话
     */
    public function close(): Response
    {
        $data = $this->request->postMore([
            ['session_id', ''],
            ['remark', ''],
        ]);

        if ($data['session_id'] === '') {
            return $this->fail('会话ID不能为空');
        }

        $staff = $this->getCurrentStaff();
        if (!$staff) {
            return $this->fail('当前管理员未绑定客服');
        }

        $sessionId = (string)$data['session_id'];
        $this->service->finish($sessionId, (int)$staff->id, trim((string)$data['remark']));
        PushNotification::notifyCsClose($sessionId);

        return $this->success('结束成功');
    }

    /**
     * 获取会话消息记录
     */
    public function messages(): Response
    {
        $params = $this->request->getMore([
            ['session_id', ''],
            ['page', 1],
            ['limit', 50],
        ]);

        if ($params['session_id'] === '') {
            return $this->fail('会话ID不能为空');
        }

        $session = $this->service->getSessionBySessionId((string)$params['session_id'], $this->getMerchantId());
        if (!$session) {
            return $this->fail('会话不存在');
        }

        return $this->success(
            '获取成功',
            $this->getMessageService()->getHistory((string)$params['session_id'], (int)$params['page'], (int)$params['limit'])
        );
    }

    /**
     * 获取会话统计
     */
    public function stats(): Response
    {
        return $this->success('获取成功', $this->service->getStats($this->getMerchantId()));
    }

    /**
     * 获取列表筛选字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['status', ''],
            ['user_id', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 20],
        ];
    }

    /**
     * 获取当前管理员绑定的客服信息
     */
    protected function getCurrentStaff(): object|null
    {
        return app()->make(CsStaffService::class)->getStaffByAdminId((int)$this->adminId, $this->getMerchantId());
    }

    /**
     * 获取消息服务
     */
    protected function getMessageService(): CsMessageService
    {
        return app()->make(CsMessageService::class);
    }

    /**
     * 获取商户ID
     */
    protected function getMerchantId(): int
    {
        return (int)$this->merchantId;
    }
}
