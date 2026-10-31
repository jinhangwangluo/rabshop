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

namespace Rabbit\service\websocket\handlers;

use app\common\enum\ClientTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\cs\CsMessage;
use app\common\model\cs\CsSession;
use app\common\service\cs\AiCustomerService;
use app\common\service\cs\CsMessageService;
use app\common\service\cs\CsSessionService;
use app\common\service\cs\CsStaffService;
use app\common\service\SensitiveFilterTrait;
use Rabbit\service\websocket\ConnectionManager;
use Rabbit\service\websocket\PushNotification;
use Rabbit\service\websocket\WebSocketService;
use think\facade\Log;

/**
 * 客服消息处理器
 */
class CustomerServiceHandler implements MessageHandlerInterface
{
    use SensitiveFilterTrait;

    /**
     * @inheritDoc
     */
    public function handle(string|int $fd, array $data): void
    {
        $event = (string)($data['event'] ?? '');
        $connection = ConnectionManager::getConnection($fd);
        if (!$connection) {
            throw new AdminResponseException('连接不存在');
        }

        if (!in_array($connection['client_type'], [ClientTypeEnum::USER, ClientTypeEnum::PLATFORM, ClientTypeEnum::MERCHANT], true)) {
            throw new AdminResponseException('无效的客户端类型');
        }

        $userId = (int)$connection['user_id'];
        $merchantId = $connection['client_type'] === ClientTypeEnum::MERCHANT
            ? (int)($connection['merchant_id'] ?? 0)
            : 0;
        $adminId = $connection['client_type'] === ClientTypeEnum::MERCHANT
            ? (int)($connection['admin_id'] ?? 0)
            : $userId;

        if ($connection['client_type'] === ClientTypeEnum::MERCHANT && ($merchantId <= 0 || $adminId <= 0)) {
            throw new AdminResponseException('商户客服身份信息不完整，请重新连接');
        }

        switch ($event) {
            case 'cs_message':
                $this->handleMessage($fd, $data, $connection, $userId, $adminId, $merchantId);
                break;
            case 'cs_transfer':
                $this->handleTransfer($data, $connection, $adminId, $merchantId);
                break;
            case 'cs_close':
                $this->handleClose($data, $connection, $adminId, $merchantId);
                break;
            case 'cs_typing':
                $this->handleTyping($data, $connection, $adminId, $merchantId);
                break;
            default:
                throw new AdminResponseException('未知的事件类型');
        }
    }

    /**
     * 处理客服消息
     * @param int $fd
     * @param array $data
     * @param array $connection
     * @param int $userId
     * @param int $adminId
     * @param int $merchantId
     * @return void
     */
    private function handleMessage(
        string|int $fd,
        array $data,
        array $connection,
        int $userId,
        int $adminId,
        int $merchantId
    ): void
    {
        $payload = $data['data'] ?? [];
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        $content = trim((string)($payload['content'] ?? ''));
        $msgType = (string)($payload['msg_type'] ?? 'text');
        $msgUuid = (string)($payload['msg_uuid'] ?? '');
        $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];

        if ($sessionId === '' || $content === '') {
            throw new AdminResponseException('会话ID或消息内容不能为空');
        }

        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminResponseException('会话不存在');
        }
        $this->assertSessionAccess($session, $connection, $merchantId);

        ConnectionManager::addConnectionToCsSession($fd, $sessionId);

        $filtered = $this->filterSensitiveText($content);
        if (trim($filtered, '*') === '' && strlen($filtered) > 0) {
            throw new AdminResponseException('消息包含敏感词，发送失败');
        }
        $content = $filtered;

        /** @var CsMessageService $messageService */
        $messageService = app()->make(CsMessageService::class);
        if ($messageService->isDuplicate($msgUuid)) {
            $duplicateMessage = $msgUuid !== '' ? $messageService->getByMsgUuid($msgUuid) : null;
            if (!$duplicateMessage || (string)$duplicateMessage->session_id !== $sessionId) {
                throw new AdminResponseException('消息唯一标识已被使用');
            }

            $this->sendMessageAck($fd, $sessionId, $duplicateMessage->toArray());
            return;
        }

        $senderType = 'user';
        $senderId = $userId;
        if (in_array($connection['client_type'], [ClientTypeEnum::PLATFORM, ClientTypeEnum::MERCHANT], true)) {
            $staff = $this->getStaffByAdminId($adminId, $merchantId);
            if (!$staff) {
                throw new AdminResponseException('客服信息不存在');
            }
            $senderType = 'staff';
            $senderId = (int)$staff->id;
        }

        $message = $messageService->sendMessage(
            $sessionId,
            $senderType,
            $senderId,
            $content,
            $msgType,
            $extra,
            $msgUuid
        );
        $this->sendMessageAck($fd, $sessionId, $message->toArray());

        Log::info("[客服消息] session={$sessionId}, sender={$senderType}, content={$content}");
        $sessionService->updateLastMessage($sessionId, $content);

        if ($senderType === CsMessage::SENDER_TYPE_USER) {
            $sessionService->incrementUnread($sessionId);
        } else {
            app()->make(CsStaffService::class)->incrementTotalMessages($senderId);
            $sessionService->clearUnread($sessionId);
        }

        PushNotification::sendCsMessage($sessionId, [
            'id' => $message->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'content' => $content,
            'message_type' => $msgType,
            'send_time' => $message->create_time,
            'msg_uuid' => $message->msg_uuid,
            'extra' => $extra,
        ]);
        PushNotification::notifyCsSessionUpdate($sessionId);

        if ($senderType === CsMessage::SENDER_TYPE_USER) {
            $this->handleAiReply($session, $sessionId, $content, $extra);
        }
    }

    /**
     * 向发送方确认消息已落库。
     *
     * @param string|int $fd 发送方连接 fd。
     * @param string $sessionId 会话 ID。
     * @param array $message 已落库消息数据。
     * @return void
     */
    private function sendMessageAck(string|int $fd, string $sessionId, array $message): void
    {
        app()->make(WebSocketService::class)->sendToFd($fd, [
            'type' => 'response',
            'event' => 'cs_message_ack',
            'code' => 200,
            'message' => 'success',
            'timestamp' => time(),
            'data' => [
                'session_id' => $sessionId,
                'id' => (int)($message['id'] ?? 0),
                'sender_type' => (string)($message['sender_type'] ?? ''),
                'sender_id' => (int)($message['sender_id'] ?? 0),
                'content' => (string)($message['content'] ?? ''),
                'message_type' => (string)($message['message_type'] ?? 'text'),
                'send_time' => (string)($message['create_time'] ?? date('Y-m-d H:i:s')),
                'msg_uuid' => (string)($message['msg_uuid'] ?? ''),
                'extra' => is_array($message['extra'] ?? null) ? $message['extra'] : [],
            ],
        ]);
    }

    /**
     * 处理 AI 回复
     * @param CsSession $session
     * @param string $sessionId
     * @param string $content
     * @param array $messageExtra 用户消息扩展信息
     * @return void
     */
    private function handleAiReply(CsSession $session, string $sessionId, string $content, array $messageExtra = []): void
    {
        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $serviceResult = $sessionService->resolveServiceForUserMessage($session);
        $session = $serviceResult['session'];

        $assignedStaffId = (int)($serviceResult['assigned_staff_id'] ?? 0);
        $fromStaffId = (int)($serviceResult['from_staff_id'] ?? 0);
        if ($assignedStaffId > 0 && $assignedStaffId !== $fromStaffId) {
            PushNotification::notifyCsTransfer($sessionId, $fromStaffId, $assignedStaffId, '客服在线，继续转入人工服务');
            PushNotification::notifyCsNewSession($sessionId, $assignedStaffId);
        }

        if ((bool)($serviceResult['switched_to_ai'] ?? false)) {
            PushNotification::notifyCsSessionUpdate($sessionId);
        }

        if ((int)$session->service_type !== CsSession::SERVICE_TYPE_AI) {
            return;
        }

        if ($this->shouldRequestHuman($messageExtra)) {
            $this->transferToHuman($sessionService, $sessionId, (int)$session->merchant_id, '用户主动请求人工客服');
            return;
        }

        /** @var AiCustomerService $aiService */
        $aiService = app()->make(AiCustomerService::class);
        $result = $aiService->reply($sessionId, $content, (int)$session->merchant_id);
        if (($result['should_transfer'] ?? false) === true) {
            $this->transferToHuman($sessionService, $sessionId, (int)$session->merchant_id, 'AI自动转人工');
            return;
        }

        $aiContent = trim((string)($result['message'] ?? ''));
        if ($aiContent === '') {
            return;
        }

        $replyExtra = $this->buildAiMessageExtra((array)($result['extra'] ?? []));

        /** @var CsMessageService $messageService */
        $messageService = app()->make(CsMessageService::class);
        $aiMessage = $messageService->sendMessage(
            $sessionId,
            'ai',
            0,
            $aiContent,
            'text',
            $replyExtra
        );

        $sessionService->updateLastMessage($sessionId, $aiContent);

        PushNotification::sendCsMessage($sessionId, [
            'id' => $aiMessage->id,
            'sender_type' => CsMessage::SENDER_TYPE_AI,
            'sender_id' => 0,
            'content' => $aiContent,
            'message_type' => CsMessage::MESSAGE_TYPE_TEXT,
            'send_time' => $aiMessage->create_time,
            'extra' => $replyExtra,
        ]);
        PushNotification::notifyCsSessionUpdate($sessionId);
    }

    /**
     * 构建 AI 消息扩展信息。
     *
     * @param array $extra AI 服务返回的扩展信息。
     * @return array
     */
    private function buildAiMessageExtra(array $extra): array
    {
        return [
            'knowledge_hit' => (bool)($extra['knowledge_hit'] ?? false),
            'knowledge_hit_count' => (int)($extra['knowledge_hit_count'] ?? 0),
            'fallback' => (bool)($extra['fallback'] ?? false),
            'fallback_reason' => (string)($extra['fallback_reason'] ?? ''),
            'ai_route' => (string)($extra['ai_route'] ?? ''),
            'knowledge_id' => (int)($extra['knowledge_id'] ?? 0),
            'source_type' => (string)($extra['source_type'] ?? ''),
            'source_id' => (int)($extra['source_id'] ?? 0),
            'similarity' => (float)($extra['similarity'] ?? 0),
        ];
    }

    /**
     * 处理转接
     * @param array $data
     * @param array $connection
     * @param int $adminId
     * @param int $merchantId
     * @return void
     */
    private function handleTransfer(array $data, array $connection, int $adminId, int $merchantId): void
    {
        $payload = $data['data'] ?? [];
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        $toStaffId = (int)($payload['to_staff_id'] ?? 0);
        $remark = (string)($payload['remark'] ?? '');

        if ($sessionId === '' || $toStaffId <= 0) {
            throw new AdminResponseException('会话ID或目标客服ID不能为空');
        }

        $staff = $this->getStaffByAdminId($adminId, $merchantId);
        if (!$staff) {
            throw new AdminResponseException('客服信息不存在');
        }

        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminResponseException('会话不存在');
        }
        $this->assertSessionAccess($session, $connection, $merchantId);

        $targetStaff = app()->make(CsStaffService::class)->getStaffById($toStaffId, (int)$session->merchant_id);
        if (!$targetStaff) {
            throw new AdminResponseException('目标客服不属于当前客服范围');
        }
        $sessionService->transfer($sessionId, (int)$staff->id, $toStaffId, $remark);
        PushNotification::notifyCsTransfer($sessionId, (int)$staff->id, $toStaffId, $remark);

        Log::info("[客服转接] session={$sessionId}, from={$staff->id}, to={$toStaffId}");
    }

    /**
     * 处理结束会话
     * @param array $data
     * @param array $connection
     * @param int $adminId
     * @param int $merchantId
     * @return void
     */
    private function handleClose(array $data, array $connection, int $adminId, int $merchantId): void
    {
        $payload = $data['data'] ?? [];
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        $remark = (string)($payload['remark'] ?? '');

        if ($sessionId === '') {
            throw new AdminResponseException('会话ID不能为空');
        }

        $staff = $this->getStaffByAdminId($adminId, $merchantId);
        if (!$staff) {
            throw new AdminResponseException('客服信息不存在');
        }

        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminResponseException('会话不存在');
        }
        $this->assertSessionAccess($session, $connection, $merchantId);
        $sessionService->finish($sessionId, (int)$staff->id, $remark);
        PushNotification::notifyCsClose($sessionId);

        Log::info("[客服结束] session={$sessionId}, staff={$staff->id}");
    }

    /**
     * 处理输入中状态
     * @param array $data
     * @param array $connection
     * @param int $adminId
     * @param int $merchantId
     * @return void
     */
    private function handleTyping(array $data, array $connection, int $adminId, int $merchantId): void
    {
        $payload = $data['data'] ?? [];
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        if ($sessionId === '') {
            return;
        }

        /** @var CsSessionService $sessionService */
        $sessionService = app()->make(CsSessionService::class);
        $session = $sessionService->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminResponseException('会话不存在');
        }
        $this->assertSessionAccess($session, $connection, $merchantId);

        $operatorId = $connection['client_type'] === ClientTypeEnum::USER
            ? (int)$connection['user_id']
            : $adminId;
        PushNotification::notifyCsTyping($sessionId, $operatorId, (bool)($payload['is_typing'] ?? false));
    }

    /**
     * 根据管理员ID获取客服
     * @param int $adminId
     * @param int $merchantId
     * @return object|null
     */
    private function getStaffByAdminId(int $adminId, int $merchantId = -1): ?object
    {
        /** @var CsStaffService $staffService */
        $staffService = app()->make(CsStaffService::class);
        return $staffService->getStaffByAdminId($adminId, $merchantId);
    }

    /**
     * 执行转人工
     * @param CsSessionService $sessionService
     * @param string $sessionId
     * @param int $merchantId
     * @param string $remark
     * @return void
     */
    private function transferToHuman(CsSessionService $sessionService, string $sessionId, int $merchantId, string $remark): void
    {
        $sessionService->requestHumanService($sessionId);

        /** @var CsStaffService $staffService */
        $staffService = app()->make(CsStaffService::class);
        $staff = $staffService->assignStaff($merchantId);
        if ($staff) {
            $sessionService->assignStaff($sessionId, (int)$staff->id);
            PushNotification::notifyCsTransfer($sessionId, 0, (int)$staff->id, $remark);
            PushNotification::notifyCsNewSession($sessionId, (int)$staff->id);
            return;
        }

        PushNotification::notifyCsHumanWaiting($sessionId);
    }

    /**
     * 判断消息是否显式请求人工客服。
     *
     * @param array $extra 消息扩展信息
     * @return bool
     */
    private function shouldRequestHuman(array $extra): bool
    {
        return strtolower(trim((string)($extra['intent'] ?? ''))) === 'request_human';
    }

    /**
     * 校验连接是否可访问客服会话。
     *
     * @param CsSession $session 会话模型
     * @param array $connection 当前连接
     * @param int $merchantId 商户客服所属商户 ID
     * @return void
     */
    private function assertSessionAccess(CsSession $session, array $connection, int $merchantId): void
    {
        if (!$this->canAccessSession($session, $connection, $merchantId)) {
            throw new AdminResponseException('无权访问该客服会话');
        }
    }

    /**
     * 判断连接是否可访问客服会话。
     *
     * @param CsSession $session 会话模型
     * @param array $connection 当前连接
     * @param int $merchantId 商户客服所属商户 ID
     * @return bool
     */
    private function canAccessSession(CsSession $session, array $connection, int $merchantId): bool
    {
        $clientType = (int)($connection['client_type'] ?? 0);
        if ($clientType === ClientTypeEnum::USER) {
            return (int)$session->user_id === (int)($connection['user_id'] ?? 0);
        }

        if ($clientType === ClientTypeEnum::PLATFORM) {
            return (int)$session->merchant_id === 0;
        }

        if ($clientType === ClientTypeEnum::MERCHANT) {
            return $merchantId > 0 && (int)$session->merchant_id === $merchantId;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public static function getEvents(): array
    {
        return ['cs_message', 'cs_transfer', 'cs_close', 'cs_typing'];
    }
}
