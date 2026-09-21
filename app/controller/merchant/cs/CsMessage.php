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

use app\common\model\cs\CsMessage as CsMessageModel;
use app\common\service\cs\CsMessageService;
use app\common\service\cs\CsSessionService;
use app\common\service\cs\CsStaffService;
use app\controller\merchant\AuthController;
use Rabbit\service\websocket\PushNotification;
use think\annotation\Inject;
use think\Response;
use Throwable;

/**
 * 客服消息控制器
 */
class CsMessage extends AuthController
{
    /**
     * @var CsMessageService
     */
    #[Inject]
    protected CsMessageService $service;

    /**
     * 发送消息
     */
    public function create(): Response
    {
        $data = $this->request->postMore([
            ['session_id', ''],
            ['content', ''],
            ['message_type', CsMessageModel::MESSAGE_TYPE_TEXT],
            ['extra', []],
            ['msg_uuid', ''],
        ]);

        if ((string)$data['session_id'] === '') {
            return $this->fail('会话ID不能为空');
        }
        $content = is_string($data['content']) ? trim($data['content']) : '';
        if ($content === '') {
            return $this->fail('消息内容不能为空');
        }

        try {
            $staff = $this->getStaffService()->getStaffByAdminId((int)$this->adminId, $this->getMerchantId());
            if (!$staff) {
                return $this->fail('当前管理员未绑定客服');
            }

            $session = $this->getSessionService()->getSessionBySessionId((string)$data['session_id'], $this->getMerchantId());
            if (!$session) {
                return $this->fail('会话不存在');
            }

            $msgUuid = trim((string)$data['msg_uuid']);
            if ($msgUuid !== '' && $this->service->isDuplicate($msgUuid)) {
                $message = $this->service->getByMsgUuid($msgUuid);
                return $this->success('发送成功', $message ? $message->toArray() : []);
            }

            $message = $this->service->sendMessage(
                (string)$data['session_id'],
                CsMessageModel::SENDER_TYPE_STAFF,
                (int)$staff->id,
                $content,
                (string)$data['message_type'],
                is_array($data['extra']) ? $data['extra'] : [],
                $msgUuid
            );

            $this->getSessionService()->updateLastMessage((string)$data['session_id'], $content);
            $this->getSessionService()->clearUnread((string)$data['session_id']);
            $this->getStaffService()->incrementTotalMessages((int)$staff->id);

            try {
                PushNotification::sendCsMessage((string)$data['session_id'], [
                    'id' => $message->id,
                    'sender_type' => CsMessageModel::SENDER_TYPE_STAFF,
                    'sender_id' => (int)$staff->id,
                    'content' => $content,
                    'message_type' => (string)$data['message_type'],
                    'send_time' => $message->create_time,
                    'msg_uuid' => $message->msg_uuid,
                    'extra' => is_array($data['extra']) ? $data['extra'] : [],
                ]);
                PushNotification::notifyCsSessionUpdate((string)$data['session_id']);
            } catch (Throwable $exception) {
                \think\facade\Log::warning('[商户客服] 推送失败：' . $exception->getMessage());
            }

            return $this->success('发送成功', $message->toArray());
        } catch (Throwable $exception) {
            return $this->fail('发送失败：' . $exception->getMessage());
        }
    }

    /**
     * 标记消息已读
     */
    public function markRead(): Response
    {
        $id = (int)$this->request->post('id/d', 0);
        if ($id <= 0) {
            return $this->fail('消息ID不能为空');
        }

        try {
            if (!$this->service->markAsRead($id)) {
                return $this->fail('标记失败');
            }

            return $this->success('标记成功');
        } catch (Throwable $exception) {
            return $this->fail('标记失败：' . $exception->getMessage());
        }
    }

    /**
     * 标记会话消息已读
     */
    public function markSessionRead(): Response
    {
        $data = $this->request->postMore([
            ['session_id', ''],
            ['sender_type', CsMessageModel::SENDER_TYPE_USER],
        ]);

        if ((string)$data['session_id'] === '') {
            return $this->fail('会话ID不能为空');
        }

        $sessionId = (string)$data['session_id'];
        $session = $this->getSessionService()->getSessionBySessionId($sessionId, $this->getMerchantId());
        if (!$session) {
            return $this->fail('会话不存在');
        }

        if (!$this->service->markSessionAsRead($sessionId, (string)$data['sender_type'])) {
            return $this->fail('标记失败');
        }

        if ((string)$data['sender_type'] === CsMessageModel::SENDER_TYPE_USER) {
            $this->getSessionService()->clearUnread($sessionId);
        }

        return $this->success('标记成功');
    }

    /**
     * 获取客服服务
     */
    protected function getStaffService(): CsStaffService
    {
        return app()->make(CsStaffService::class);
    }

    /**
     * 获取会话服务
     */
    protected function getSessionService(): CsSessionService
    {
        return app()->make(CsSessionService::class);
    }

    /**
     * 获取商户ID
     */
    protected function getMerchantId(): int
    {
        return (int)$this->merchantId;
    }
}
