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

namespace app\common\service\live;

use app\common\exception\AdminResponseException;
use app\common\model\live\LiveRoomUser;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\user\UserService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 直播间用户服务
 * 负责：用户进出在线状态维护、禁言管理、在线用户列表查询
 */
class LiveRoomUserService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return LiveRoomUser::class;
    }

    /**
     * 用户进入直播间
     * - 首次进入：创建记录
     * - 再次进入：更新在线状态 + 进入次数
     * @param int $roomId
     * @param int $userId
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function userEnter(int $roomId, int $userId): void
    {
        // 获取用户信息快照
        $userService = app()->make(UserService::class);
        $userInfo    = $userService->get(['uid' => $userId], ['nickname', 'avatar']);

        $nickname = $userInfo['nickname'] ?? '';
        $avatar   = $userInfo['avatar'] ?? '';
        $now      = date('Y-m-d H:i:s');

        $record = $this->getModel()->where(['room_id' => $roomId, 'user_id' => $userId])->find();

        if (!$record) {
            // 首次进入：新建记录
            $this->getModel()->insert([
                'room_id'         => $roomId,
                'user_id'         => $userId,
                'nickname'        => $nickname,
                'avatar'          => $avatar,
                'is_online'       => 1,
                'online_count'    => 1,
                'last_enter_time' => $now,
                'last_leave_time' => null,
                'is_muted'        => 0,
                'mute_end_time'   => null,
                'mute_admin_id'   => 0,
                'mute_reason'     => '',
                'create_time'     => $now,
                'update_time'     => $now,
            ]);
        } else {
            // 再次进入：更新状态
            $this->getModel()->where(['room_id' => $roomId, 'user_id' => $userId])->update([
                'nickname'        => $nickname,
                'avatar'          => $avatar,
                'is_online'       => 1,
                'online_count'    => (int)$record['online_count'] + 1,
                'last_enter_time' => $now,
                'update_time'     => $now,
            ]);
        }
    }

    /**
     * 用户离开直播间（断开连接时调用）
     * @param int $roomId
     * @param int $userId
     * @return void
     */
    public function userLeave(int $roomId, int $userId): void
    {
        $this->getModel()->where(['room_id' => $roomId, 'user_id' => $userId])->update([
            'is_online'       => 0,
            'last_leave_time' => date('Y-m-d H:i:s'),
            'update_time'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 禁言用户
     * @param int $roomId
     * @param int $userId
     * @param int $adminId 操作管理员ID
     * @param int $duration 禁言时长（秒），0=永久禁言
     * @param string $reason 禁言原因
     * @return array 禁言信息
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function muteUser(int $roomId, int $userId, int $adminId, int $duration = 3600, string $reason = ''): array
    {
        $record = $this->getModel()->where(['room_id' => $roomId, 'user_id' => $userId])->find();
        if (!$record) {
            throw new AdminResponseException('该用户未进入过此直播间');
        }

        // duration=0 表示永久禁言，mute_end_time 设置为 10 年后
        $muteEndTime = $duration > 0
            ? date('Y-m-d H:i:s', time() + $duration)
            : date('Y-m-d H:i:s', strtotime('+10 years'));

        $this->getModel()->where(['room_id' => $roomId, 'user_id' => $userId])->update([
            'is_muted'      => 1,
            'mute_end_time' => $muteEndTime,
            'mute_admin_id' => $adminId,
            'mute_reason'   => $reason,
            'update_time'   => date('Y-m-d H:i:s'),
        ]);

        return [
            'room_id'       => $roomId,
            'user_id'       => $userId,
            'nickname'      => $record['nickname'],
            'is_muted'      => 1,
            'mute_end_time' => $muteEndTime,
            'duration'      => $duration,
            'reason'        => $reason,
        ];
    }

    /**
     * 解除禁言
     * @param int $roomId
     * @param int $userId
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function unmuteUser(int $roomId, int $userId): void
    {
        $record = $this->getModel()->where(['room_id' => $roomId, 'user_id' => $userId])->find();
        if (!$record) {
            throw new AdminResponseException('该用户未进入过此直播间');
        }

        $this->getModel()->where(['room_id' => $roomId, 'user_id' => $userId])->update([
            'is_muted'      => 0,
            'mute_end_time' => null,
            'mute_admin_id' => 0,
            'mute_reason'   => '',
            'update_time'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 检查用户是否在禁言期内
     * @param int $roomId
     * @param int $userId
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function isMuted(int $roomId, int $userId): bool
    {
        $record = $this->getModel()
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('is_muted', 1)
            ->where('mute_end_time', '>', date('Y-m-d H:i:s'))
            ->find();

        return $record !== null;
    }

    /**
     * 获取禁言剩余秒数（用于提示）
     * @param int $roomId
     * @param int $userId
     * @return int 剩余秒数，0 表示未被禁言
     */
    public function getMuteRemainingSeconds(int $roomId, int $userId): int
    {
        $record = $this->getModel()
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('is_muted', 1)
            ->where('mute_end_time', '>', date('Y-m-d H:i:s'))
            ->field(['mute_end_time'])
            ->find();

        if (!$record) {
            return 0;
        }

        return max(0, strtotime($record['mute_end_time']) - time());
    }

    /**
     * 获取当前在线用户列表
     * @param int $roomId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOnlineUsers(int $roomId): array
    {
        return $this->getModel()
            ->where('room_id', $roomId)
            ->where('is_online', 1)
            ->field(['id', 'user_id', 'nickname', 'avatar', 'is_muted', 'mute_end_time', 'last_enter_time', 'online_count'])
            ->order('last_enter_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取当前在线人数
     * @param int $roomId
     * @return int
     */
    public function getOnlineCount(int $roomId): int
    {
        return $this->getModel()
            ->where('room_id', $roomId)
            ->where('is_online', 1)
            ->count();
    }

    /**
     * 获取直播间累计进入用户数。
     */
    public function countRoomUsers(int $roomId): int
    {
        return (int)$this->getModel()
            ->where('room_id', $roomId)
            ->count();
    }

    /**
     * 获取直播间用户分页数据。
     *
     * @param string[] $fields
     * @return array{list: array, total: int}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomUserPage(int $roomId, int $page = 1, int $limit = 20, array $fields = []): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $fields = $fields ?: [
            'id',
            'user_id',
            'nickname',
            'avatar',
            'is_online',
            'is_muted',
            'mute_end_time',
            'last_enter_time',
            'online_count',
        ];

        $total = $this->countRoomUsers($roomId);
        $list = $this->getModel()
            ->where('room_id', $roomId)
            ->field($fields)
            ->order('is_online desc, last_enter_time desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return compact('list', 'total');
    }

    /**
     * 判断用户是否进入过直播间。
     */
    public function hasRoomUser(int $roomId, int $userId): bool
    {
        return $this->getModel()
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->find() !== null;
    }

    /**
     * 获取当前被禁言用户列表
     * @param int $roomId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMutedUsers(int $roomId): array
    {
        return $this->getModel()
            ->where('room_id', $roomId)
            ->where('is_muted', 1)
            ->where('mute_end_time', '>', date('Y-m-d H:i:s'))
            ->field(['id', 'user_id', 'nickname', 'avatar', 'mute_end_time', 'mute_admin_id', 'mute_reason'])
            ->order('update_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 直播间结束时将所有用户标记为离线
     * @param int $roomId
     * @return void
     */
    public function markAllOffline(int $roomId): void
    {
        $this->getModel()->where(['room_id' => $roomId, 'is_online' => 1])->update([
            'is_online'       => 0,
            'last_leave_time' => date('Y-m-d H:i:s'),
            'update_time'     => date('Y-m-d H:i:s'),
        ]);
    }
}
