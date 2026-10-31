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

namespace app\controller\mobile\cs;

use app\common\exception\AdminException;
use app\common\model\cs\CsMessage as CsMessageModel;
use app\common\model\cs\CsSession as CsSessionModel;
use app\common\service\cs\AiCustomerService;
use app\common\service\cs\CsMessageService;
use app\common\service\cs\CsSessionService;
use app\common\service\cs\CsStaffService;
use app\BaseController;
use Rabbit\service\websocket\PushNotification;
use think\annotation\Inject;
use think\facade\Log;
use think\Response;

/**
 * 用户端客服消息控制器.
 */
class CsMessage extends BaseController
{
    /**
     * 客服消息服务.
     *
     * @var CsMessageService
     */
    #[Inject]
    protected CsMessageService $service;

    /**
     * 发送客服消息.
     *
     * POST 参数：
     * - session_id string 会话 ID。
     * - content string 消息内容。
     * - message_type string 消息类型，默认 text。
     * - extra array 扩展数据。
     * - msg_uuid string 客户端消息唯一 ID，用于防重复提交。
     *
     * @return Response 返回发送后的消息信息。
     */
    public function create(): Response
    {
        $userId = $this->getLoginUserId();
        if ($userId <= 0) {
            return $this->fail('请先登录');
        }

        $data = $this->request->postMore([
            ['session_id', ''],
            ['content', ''],
            ['message_type', CsMessageModel::MESSAGE_TYPE_TEXT],
            ['extra', []],
            ['msg_uuid', ''],
        ]);

        $sessionId = trim((string)$data['session_id']);
        if ($sessionId === '') {
            return $this->fail('会话ID不能为空');
        }

        $content = is_string($data['content']) ? trim($data['content']) : '';
        if ($content === '') {
            return $this->fail('消息内容不能为空');
        }

        $session = $this->getSessionByUser($sessionId, $userId);
        if (!$session) {
            return $this->fail('会话不存在或无权访问');
        }

        $msgUuid = trim((string)$data['msg_uuid']);
        if ($msgUuid !== '' && $this->service->isDuplicate($msgUuid)) {
            $message = $this->service->getByMsgUuid($msgUuid);
            if (!$message || (string)$message->session_id !== $sessionId) {
                return $this->fail('消息唯一标识已被使用');
            }

            return $this->success('发送成功', $message->toArray());
        }

        $extra = is_array($data['extra']) ? $data['extra'] : [];

        $message = $this->service->sendMessage(
            $sessionId,
            CsMessageModel::SENDER_TYPE_USER,
            $userId,
            $content,
            (string)$data['message_type'],
            $extra,
            $msgUuid
        );

        $this->getSessionService()->updateLastMessage($sessionId, $content);
        $this->getSessionService()->incrementUnread($sessionId);

        PushNotification::sendCsMessage($sessionId, [
            'id' => $message->id,
            'sender_type' => CsMessageModel::SENDER_TYPE_USER,
            'sender_id' => $userId,
            'content' => $content,
            'message_type' => (string)$data['message_type'],
            'send_time' => $message->create_time,
            'msg_uuid' => $message->msg_uuid,
            'extra' => $extra,
        ]);
        PushNotification::notifyCsSessionUpdate($sessionId);

        $this->tryAiReply($session, $sessionId, $content, $extra);

        return $this->success('发送成功', $message->toArray());
    }

    /**
     * 获取客服历史消息.
     *
     * GET 参数：
     * - session_id string 会话 ID。
     * - page int 页码，默认 1。
     * - limit int 每页数量，默认 50。
     * - after_id int 只拉取指定消息 ID 之后的新消息，用于断线补偿。
     * - latest int 是否获取最近一页消息，1 是，0 否。
     *
     * @return Response 返回消息分页列表。
     */
    public function history(): Response
    {
        $userId = $this->getLoginUserId();
        if ($userId <= 0) {
            return $this->fail('请先登录');
        }

        $params = $this->request->getMore([
            ['session_id', ''],
            ['page', 1],
            ['limit', 50],
            ['after_id', 0],
            ['latest', 0],
        ]);

        $sessionId = trim((string)$params['session_id']);
        if ($sessionId === '') {
            return $this->fail('会话ID不能为空');
        }

        if (!$this->getSessionByUser($sessionId, $userId)) {
            return $this->fail('会话不存在或无权访问');
        }

        return $this->success(
            '获取成功',
            $this->service->getHistory(
                $sessionId,
                (int)$params['page'],
                (int)$params['limit'],
                (int)$params['after_id'],
                (int)$params['latest'] === 1
            )
        );
    }

    /**
     * 标记会话消息已读.
     *
     * POST 参数：
     * - session_id string 会话 ID。
     * - sender_type string 需要标记已读的发送方类型，默认 staff。
     *
     * @return Response 返回标记结果。
     */
    public function markSessionRead(): Response
    {
        $userId = $this->getLoginUserId();
        if ($userId <= 0) {
            return $this->fail('请先登录');
        }

        $data = $this->request->postMore([
            ['session_id', ''],
            ['sender_type', CsMessageModel::SENDER_TYPE_STAFF],
        ]);

        $sessionId = trim((string)$data['session_id']);
        if ($sessionId === '') {
            return $this->fail('会话ID不能为空');
        }

        if (!$this->getSessionByUser($sessionId, $userId)) {
            return $this->fail('会话不存在或无权访问');
        }

        if (!$this->service->markSessionAsRead($sessionId, (string)$data['sender_type'])) {
            return $this->fail('标记失败');
        }

        return $this->success('标记成功');
    }

    /**
     * 尝试 AI 回复.
     *
     * @param CsSessionModel $session 会话模型。
     * @param string $sessionId 会话 ID。
     * @param string $content 用户消息内容。
     * @param array $extra 用户消息扩展信息。
     * @return void
     */
    protected function tryAiReply(CsSessionModel $session, string $sessionId, string $content, array $extra = []): void
    {
        try {
            $serviceResult = $this->getSessionService()->resolveServiceForUserMessage($session);
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

            if ((int)$session->service_type !== CsSessionModel::SERVICE_TYPE_AI) {
                return;
            }

            if ($this->shouldRequestHuman($extra)) {
                $this->transferToHuman($sessionId, (int)$session->merchant_id, '用户主动请求人工客服');
                return;
            }

            $aiService = $this->getAiService();
            $result = $aiService->reply($sessionId, $content, (int)$session->merchant_id);
            if (($result['should_transfer'] ?? false) === true) {
                $this->transferToHuman($sessionId, (int)$session->merchant_id, 'AI自动转人工');
                return;
            }

            $aiContent = trim((string)($result['message'] ?? ''));
            if ($aiContent === '') {
                return;
            }

            $replyExtra = $this->buildAiMessageExtra((array)($result['extra'] ?? []));

            $aiMessage = $this->service->sendMessage(
                $sessionId,
                CsMessageModel::SENDER_TYPE_AI,
                0,
                $aiContent,
                CsMessageModel::MESSAGE_TYPE_TEXT,
                $replyExtra
            );
            $this->getSessionService()->updateLastMessage($sessionId, $aiContent);

            PushNotification::sendCsMessage($sessionId, [
                'id' => $aiMessage->id,
                'sender_type' => CsMessageModel::SENDER_TYPE_AI,
                'sender_id' => 0,
                'content' => $aiContent,
                'message_type' => CsMessageModel::MESSAGE_TYPE_TEXT,
                'send_time' => $aiMessage->create_time,
                'extra' => $replyExtra,
            ]);
            PushNotification::notifyCsSessionUpdate($sessionId);
        } catch (AdminException $exception) {
            Log::warning('[移动端客服] AI转人工失败: ' . $exception->getMessage());
        } catch (\Throwable $exception) {
            Log::warning('[移动端客服] AI回复失败: ' . $exception->getMessage());
        }
    }

    /**
     * 执行转人工.
     *
     * @param string $sessionId 会话 ID。
     * @param int $merchantId 商户 ID。
     * @param string $remark 转人工原因。
     * @return void
     */
    protected function transferToHuman(string $sessionId, int $merchantId, string $remark): void
    {
        $this->getSessionService()->requestHumanService($sessionId);
        $staff = app()->make(CsStaffService::class)->assignStaff($merchantId);
        if ($staff) {
            $this->getSessionService()->assignStaff($sessionId, (int)$staff->id);
            PushNotification::notifyCsTransfer($sessionId, 0, (int)$staff->id, $remark);
            PushNotification::notifyCsNewSession($sessionId, (int)$staff->id);
            return;
        }

        PushNotification::notifyCsHumanWaiting($sessionId);
    }

    /**
     * 构建 AI 消息扩展信息。
     *
     * @param array $extra AI 服务返回的扩展信息。
     * @return array
     */
    protected function buildAiMessageExtra(array $extra): array
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
            'mcp_used' => (bool)($extra['mcp_used'] ?? false),
            'tool_queries' => is_array($extra['tool_queries'] ?? null)
                ? array_values($extra['tool_queries'])
                : [],
            'sources' => is_array($extra['sources'] ?? null)
                ? array_values($extra['sources'])
                : [],
        ];
    }

    /**
     * 判断消息是否显式请求人工客服。
     *
     * @param array $extra 消息扩展信息。
     * @return bool
     */
    protected function shouldRequestHuman(array $extra): bool
    {
        return strtolower(trim((string)($extra['intent'] ?? ''))) === 'request_human';
    }

    /**
     * 根据会话和用户获取会话.
     *
     * @param string $sessionId 会话 ID。
     * @param int $userId 用户 ID。
     * @return CsSessionModel|null 当前用户可访问的会话。
     */
    protected function getSessionByUser(string $sessionId, int $userId): ?CsSessionModel
    {
        $session = $this->getSessionService()->getSessionBySessionId($sessionId);
        if (!$session) {
            return null;
        }

        return (int)$session->user_id === $userId ? $session : null;
    }

    /**
     * 获取会话服务.
     *
     * @return CsSessionService
     */
    protected function getSessionService(): CsSessionService
    {
        return app()->make(CsSessionService::class);
    }

    /**
     * 获取 AI 服务.
     *
     * @return AiCustomerService
     */
    protected function getAiService(): AiCustomerService
    {
        return app()->make(AiCustomerService::class);
    }

    /**
     * 获取当前登录用户 ID.
     *
     * @return int 当前登录用户 ID。
     */
    protected function getLoginUserId(): int
    {
        return (int)($this->request->user_id ?? 0);
    }
}
