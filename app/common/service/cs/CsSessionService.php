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

namespace app\common\service\cs;

use app\common\exception\AdminException;
use app\common\model\cs\CsSession;
use app\common\model\cs\CsTransferLog;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 客服会话服务
 */
class CsSessionService extends BaseService
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return CsSession::class;
    }

    /**
     * 获取会话列表
     * @param array $params
     * @param int $merchantId
     * @return array
     */
    public function getSessionList(array $params = [], int $merchantId = 0): array
    {
        [$page, $limit] = $this->getPagesValue();
        $status = $params['status'] ?? '';
        $userId = $params['user_id'] ?? '';
        $keyword = trim((string)($params['keyword'] ?? ''));

        $query = $this->getModel()->where('merchant_id', $merchantId);

        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }

        if ($userId !== '' && $userId !== null) {
            $query->where('user_id', (int)$userId);
        }

        if ($keyword !== '') {
            $query->where('last_message', 'like', '%' . $keyword . '%');
        }

        $total = $query->count();
        $list = $query->with(['user', 'staff'])
            ->order('create_time desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 获取会话详情
     * @param string $sessionId
     * @param int $merchantId
     * @param array $with
     * @return CsSession|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getSessionBySessionId(string $sessionId, int $merchantId = -1, array $with = []): ?CsSession
    {
        $query = $this->getModel()->where('session_id', $sessionId);
        if ($merchantId >= 0) {
            $query->where('merchant_id', $merchantId);
        }

        if ($with !== []) {
            $query->with($with);
        }

        return $query->find();
    }

    /**
     * 获取统计数据
     * @param int $merchantId
     * @return array
     */
    public function getStats(int $merchantId = 0): array
    {
        return [
            'waiting_count' => $this->getModel()->where('merchant_id', $merchantId)->where('status', CsSession::STATUS_WAITING)->count(),
            'serving_count' => $this->getModel()->where('merchant_id', $merchantId)->where('status', CsSession::STATUS_SERVING)->count(),
            'today_count' => $this->getModel()->where('merchant_id', $merchantId)->whereTime('create_time', 'today')->count(),
        ];
    }

    /**
     * 创建会话
     * @param int $userId 用户ID
     * @param int $merchantId 商户ID
     * @param string $userIp 用户IP
     * @param string $userAgent 用户浏览器信息
     * @return mixed
     */
    public function createSession(int $userId, int $merchantId = 0, string $userIp = '', string $userAgent = '')
    {
        return $this->getModel()->create([
            'session_id' => $this->generateSessionId(),
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'status' => CsSession::STATUS_WAITING,
            'service_type' => CsSession::SERVICE_TYPE_AI,
            'user_ip' => $userIp,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * 获取或创建会话
     * @param int $userId 用户ID
     * @param int $merchantId 商户ID
     * @param string $userIp 用户IP
     * @param string $userAgent 用户浏览器信息
     * @return CsSession
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOrCreateSession(int $userId, int $merchantId = 0, string $userIp = '', string $userAgent = ''): CsSession
    {
        $session = $this->getModel()
            ->where('user_id', $userId)
            ->where('merchant_id', $merchantId)
            ->whereIn('status', [CsSession::STATUS_WAITING, CsSession::STATUS_SERVING])
            ->order('create_time desc')
            ->find();

        return $session ?: $this->createSession($userId, $merchantId, $userIp, $userAgent);
    }

    /**
     * 接入会话
     * @param string $sessionId 会话ID
     * @param int $staffId 客服ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function assignStaff(string $sessionId, int $staffId): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminException('会话不存在');
        }

        if ((int)$session->status === CsSession::STATUS_FINISHED) {
            throw new AdminException('会话已结束');
        }

        $oldStaffId = (int)$session->staff_id;
        $session->staff_id = $staffId;
        $session->status = CsSession::STATUS_SERVING;
        $session->service_type = CsSession::SERVICE_TYPE_HUMAN;
        $session->unread_count = 0;
        $result = (bool)$session->save();

        if ($result) {
            $staffService = app()->make(CsStaffService::class);
            if ($oldStaffId > 0 && $oldStaffId !== $staffId) {
                $staffService->decrementCurrentSessions($oldStaffId);
            }
            if ($staffId > 0 && $oldStaffId !== $staffId) {
                $staffService->incrementCurrentSessions($staffId);
            }
        }

        return $result;
    }

    /**
     * 请求人工服务
     * @param string $sessionId 会话ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function requestHumanService(string $sessionId): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminException('会话不存在');
        }

        $oldStaffId = (int)$session->staff_id;
        $session->status = CsSession::STATUS_WAITING;
        $session->service_type = CsSession::SERVICE_TYPE_HUMAN;
        $session->staff_id = 0;
        $result = (bool)$session->save();

        if ($result && $oldStaffId > 0) {
            app()->make(CsStaffService::class)->decrementCurrentSessions($oldStaffId);
        }

        return $result;
    }

    /**
     * 用户消息进入时解析当前服务模式。
     *
     * 人工会话会先检查已分配客服是否在线；客服不在线时尝试分配其他在线客服；
     * 仍无可用客服时切回 AI 服务。
     *
     * @param CsSession $session 当前会话。
     * @return array{session:CsSession,service_type:int,assigned_staff_id:int,from_staff_id:int,switched_to_ai:bool}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function resolveServiceForUserMessage(CsSession $session): array
    {
        $sessionId = (string)$session->session_id;
        $merchantId = (int)$session->merchant_id;
        $fromStaffId = (int)$session->staff_id;

        if ((int)$session->service_type === CsSession::SERVICE_TYPE_AI) {
            return [
                'session' => $session,
                'service_type' => CsSession::SERVICE_TYPE_AI,
                'assigned_staff_id' => 0,
                'from_staff_id' => $fromStaffId,
                'switched_to_ai' => false,
            ];
        }

        /** @var CsStaffService $staffService */
        $staffService = app()->make(CsStaffService::class);
        if ($this->isStaffOnline($staffService, $fromStaffId, $merchantId)) {
            return [
                'session' => $session,
                'service_type' => CsSession::SERVICE_TYPE_HUMAN,
                'assigned_staff_id' => $fromStaffId,
                'from_staff_id' => $fromStaffId,
                'switched_to_ai' => false,
            ];
        }

        $staff = $staffService->assignStaff($merchantId);
        if ($staff) {
            $this->assignStaff($sessionId, (int)$staff->id);
            $freshSession = $this->getSessionBySessionId($sessionId) ?: $session;
            return [
                'session' => $freshSession,
                'service_type' => CsSession::SERVICE_TYPE_HUMAN,
                'assigned_staff_id' => (int)$staff->id,
                'from_staff_id' => $fromStaffId,
                'switched_to_ai' => false,
            ];
        }

        $this->switchToAiService($sessionId);
        $freshSession = $this->getSessionBySessionId($sessionId) ?: $session;
        return [
            'session' => $freshSession,
            'service_type' => CsSession::SERVICE_TYPE_AI,
            'assigned_staff_id' => 0,
            'from_staff_id' => $fromStaffId,
            'switched_to_ai' => true,
        ];
    }

    /**
     * 切换会话为 AI 服务。
     *
     * @param string $sessionId 会话 ID。
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function switchToAiService(string $sessionId): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminException('会话不存在');
        }

        $oldStaffId = (int)$session->staff_id;
        $session->status = CsSession::STATUS_WAITING;
        $session->service_type = CsSession::SERVICE_TYPE_AI;
        $session->staff_id = 0;
        $result = (bool)$session->save();

        if ($result && $oldStaffId > 0) {
            app()->make(CsStaffService::class)->decrementCurrentSessions($oldStaffId);
        }

        return $result;
    }

    /**
     * 判断指定客服是否在线。
     *
     * @param CsStaffService $staffService 客服服务。
     * @param int $staffId 客服 ID。
     * @param int $merchantId 商户 ID。
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function isStaffOnline(CsStaffService $staffService, int $staffId, int $merchantId): bool
    {
        if ($staffId <= 0) {
            return false;
        }

        $staff = $staffService->getStaffById($staffId, $merchantId);
        return $staff && (int)$staff->is_online === 1;
    }

    /**
     * 转接会话
     * @param string $sessionId 会话ID
     * @param int $fromStaffId 原客服ID
     * @param int $toStaffId 目标客服ID
     * @param string $remark 备注
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function transfer(string $sessionId, int $fromStaffId, int $toStaffId, string $remark = ''): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminException('会话不存在');
        }

        if ((int)$session->status !== CsSession::STATUS_SERVING) {
            throw new AdminException('只能转接进行中的会话');
        }

        if ((int)$session->staff_id !== $fromStaffId) {
            throw new AdminException('无权转接此会话');
        }

        /** @var CsTransferLog $transferLog */
        $transferLog = app()->make(CsTransferLog::class);
        $transferLog->save([
            'session_id' => $sessionId,
            'from_staff_id' => $fromStaffId,
            'to_staff_id' => $toStaffId,
            'remark' => $remark,
            'transfer_time' => date('Y-m-d H:i:s'),
        ]);

        $session->staff_id = $toStaffId;
        $result = (bool)$session->save();

        if ($result) {
            $staffService = app()->make(CsStaffService::class);
            $staffService->decrementCurrentSessions($fromStaffId);
            $staffService->incrementCurrentSessions($toStaffId);
        }

        return $result;
    }

    /**
     * 结束会话
     * @param string $sessionId 会话ID
     * @param int $staffId 客服ID
     * @param string $remark 备注
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function finish(string $sessionId, int $staffId, string $remark = ''): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            throw new AdminException('会话不存在');
        }

        if ((int)$session->status !== CsSession::STATUS_SERVING) {
            throw new AdminException('只能结束进行中的会话');
        }

        if ((int)$session->staff_id !== $staffId) {
            throw new AdminException('无权结束此会话');
        }

        $session->status = CsSession::STATUS_FINISHED;
        $session->finish_time = date('Y-m-d H:i:s');
        $session->finish_remark = $remark;
        $result = (bool)$session->save();

        if ($result && $staffId > 0) {
            app()->make(CsStaffService::class)->decrementCurrentSessions($staffId);
        }

        return $result;
    }

    /**
     * 更新会话最后消息
     * @param string $sessionId 会话ID
     * @param string $message 消息内容
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateLastMessage(string $sessionId, string $message): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            return false;
        }

        $session->last_message = mb_substr($message, 0, 500);
        $session->last_message_time = date('Y-m-d H:i:s');

        return (bool)$session->save();
    }

    /**
     * 增加未读数
     * @param string $sessionId 会话ID
     * @param int $count 数量
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function incrementUnread(string $sessionId, int $count = 1): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            return false;
        }

        $session->unread_count = (int)$session->unread_count + $count;

        return (bool)$session->save();
    }

    /**
     * 清空未读数
     * @param string $sessionId 会话ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function clearUnread(string $sessionId): bool
    {
        $session = $this->getSessionBySessionId($sessionId);
        if (!$session) {
            return false;
        }

        $session->unread_count = 0;

        return (bool)$session->save();
    }

    /**
     * 客服断线时回退其进行中的会话为待接入
     * @param int $staffId 客服ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function setServingSessionsWaitingByStaff(int $staffId): array
    {
        if ($staffId <= 0) {
            return [];
        }

        $sessionIds = [];
        $sessions = $this->getModel()
            ->where('staff_id', $staffId)
            ->where('status', CsSession::STATUS_SERVING)
            ->select();

        foreach ($sessions as $session) {
            $session->status = CsSession::STATUS_WAITING;
            $session->service_type = CsSession::SERVICE_TYPE_HUMAN;
            $session->staff_id = 0;
            if ($session->save()) {
                $sessionIds[] = (string)$session->session_id;
            }
        }

        if ($sessionIds !== []) {
            app()->make(CsStaffService::class)->decrementCurrentSessions($staffId, count($sessionIds));
        }

        return $sessionIds;
    }

    /**
     * 生成会话ID
     * @return string
     */
    private function generateSessionId(): string
    {
        return 'cs' . date('YmdHis') . mt_rand(1000, 9999) . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
    }
}
