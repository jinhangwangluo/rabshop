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
use app\common\model\cs\CsStaff;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 客服人员服务
 */
class CsStaffService extends BaseService
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return CsStaff::class;
    }

    /**
     * 获取客服列表
     * @param array $params
     * @param int $merchantId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getStaffList(array $params = [], int $merchantId = 0): array
    {
        [$page, $limit] = $this->getPagesValue();

        $status = $params['status'] ?? '';
        $isOnline = $params['is_online'] ?? '';
        $staffNo = trim((string)($params['staff_no'] ?? ''));
        $keyword = trim((string)($params['keyword'] ?? ''));

        $query = $this->getModel()->where('merchant_id', $merchantId);

        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }

        if ($isOnline !== '' && $isOnline !== null) {
            $query->where('is_online', (int)$isOnline);
        }

        if ($staffNo !== '') {
            $query->where('staff_no', 'like', '%' . $staffNo . '%');
        }

        if ($keyword !== '') {
            $query->where('nickname', 'like', '%' . $keyword . '%');
        }

        $total = $query->count();
        $list = $query->with(['admin'])
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
     * 根据ID获取客服
     * @param int $staffId
     * @param int $merchantId
     * @return CsStaff|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getStaffById(int $staffId, int $merchantId = -1): ?CsStaff
    {
        $query = $this->getModel()->where('id', $staffId);
        if ($merchantId >= 0) {
            $query->where('merchant_id', $merchantId);
        }

        return $query->find();
    }

    /**
     * 根据管理员ID获取客服
     * @param int $adminId
     * @param int $merchantId
     * @return CsStaff|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getStaffByAdminId(int $adminId, int $merchantId = -1): ?CsStaff
    {
        $query = $this->getModel()->where('admin_id', $adminId);
        if ($merchantId >= 0) {
            $query->where('merchant_id', $merchantId);
        }

        return $query->find();
    }

    /**
     * 创建客服
     * @param int $merchantId 商户ID
     * @param int $adminId 管理员ID
     * @param string $nickname 昵称
     * @param string $avatar 头像
     * @return mixed
     */
    public function createStaff(int $merchantId, int $adminId, string $nickname, string $avatar = '')
    {
        $staffNo = $this->generateStaffNo($merchantId);

        return $this->getModel()->create([
            'merchant_id' => $merchantId,
            'admin_id' => $adminId,
            'staff_no' => $staffNo,
            'nickname' => $nickname,
            'avatar' => $avatar,
            'status' => CsStaff::STATUS_OFFLINE,
            'is_online' => 0,
            'current_sessions' => 0,
            'max_sessions' => 10,
            'total_sessions' => 0,
            'total_messages' => 0,
        ]);
    }

    /**
     * 更新客服
     * @param int $staffId
     * @param array $data
     * @param int $merchantId
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateStaff(int $staffId, array $data, int $merchantId = -1): bool
    {
        $staff = $this->getStaffById($staffId, $merchantId);
        if (!$staff) {
            throw new AdminException('客服不存在');
        }

        return (bool)$staff->save($data);
    }

    /**
     * 删除客服
     * @param int $staffId
     * @param int $merchantId
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteStaff(int $staffId, int $merchantId = -1): bool
    {
        $staff = $this->getStaffById($staffId, $merchantId);
        if (!$staff) {
            throw new AdminException('客服不存在');
        }

        return (bool)$staff->delete();
    }

    /**
     * 更新在线状态
     * @param int $staffId 客服ID
     * @param int $isOnline 是否在线
     * @param int|null $status 状态
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateOnlineStatus(int $staffId, int $isOnline, ?int $status = null): bool
    {
        $staff = $this->getModel()->find($staffId);
        if (!$staff) {
            throw new AdminException('客服不存在');
        }

        $staff->is_online = $isOnline;
        if ($status !== null) {
            $staff->status = $status;
        }

        if ($isOnline === 1) {
            $staff->last_online_time = date('Y-m-d H:i:s');
        } else {
            $staff->last_offline_time = date('Y-m-d H:i:s');
        }

        return (bool)$staff->save();
    }

    /**
     * 增加当前会话数
     * @param int $staffId 客服ID
     * @param int $count 数量
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function incrementCurrentSessions(int $staffId, int $count = 1): bool
    {
        $staff = $this->getModel()->find($staffId);
        if (!$staff) {
            return false;
        }

        $staff->current_sessions = (int)$staff->current_sessions + $count;
        $staff->total_sessions = (int)$staff->total_sessions + $count;

        return (bool)$staff->save();
    }

    /**
     * 减少当前会话数
     * @param int $staffId 客服ID
     * @param int $count 数量
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function decrementCurrentSessions(int $staffId, int $count = 1): bool
    {
        $staff = $this->getModel()->find($staffId);
        if (!$staff) {
            return false;
        }

        $staff->current_sessions = max(0, (int)$staff->current_sessions - $count);

        return (bool)$staff->save();
    }

    /**
     * 增加消息数
     * @param int $staffId 客服ID
     * @param int $count 数量
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function incrementTotalMessages(int $staffId, int $count = 1): bool
    {
        $staff = $this->getModel()->find($staffId);
        if (!$staff) {
            return false;
        }

        $staff->total_messages = (int)$staff->total_messages + $count;

        return (bool)$staff->save();
    }

    /**
     * 负载均衡分配客服
     * @param int $merchantId 商户ID
     * @return CsStaff|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function assignStaff(int $merchantId = 0): ?CsStaff
    {
        $staffList = $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('is_online', 1)
            ->where('status', '<>', CsStaff::STATUS_BUSY)
            ->order('current_sessions asc, id asc')
            ->select();

        foreach ($staffList as $staff) {
            if ($this->canAcceptSession((int)$staff->id)) {
                return $staff;
            }
        }

        return null;
    }

    /**
     * 获取在线客服列表
     * @param int $merchantId 商户ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOnlineStaff(int $merchantId = 0): array
    {
        return $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('is_online', 1)
            ->order('status asc, current_sessions asc')
            ->select()
            ->toArray();
    }

    /**
     * 检查客服是否可以接入新会话
     * @param int $staffId 客服ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function canAcceptSession(int $staffId): bool
    {
        $staff = $this->getModel()->find($staffId);
        if (!$staff) {
            return false;
        }

        return (int)$staff->is_online === 1 && (int)$staff->current_sessions < (int)$staff->max_sessions;
    }

    /**
     * 生成客服工号
     * @param int $merchantId 商户ID
     * @return string
     */
    private function generateStaffNo(int $merchantId): string
    {
        $prefix = $merchantId > 0 ? 'M' . $merchantId : 'P';
        $count = $this->getModel()->where('merchant_id', $merchantId)->count();

        return $prefix . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
