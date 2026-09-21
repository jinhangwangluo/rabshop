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
use app\common\model\live\LiveRoomUserLevel;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 用户直播间等级服务
 */
class LiveRoomUserLevelService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return LiveRoomUserLevel::class;
    }

    /**
     * 处理用户在直播间赠送礼物，触发贡献值增加和升级检测
     *
     * @param int   $userId     用户 ID
     * @param int   $liveRoomId 直播间 ID
     * @param float $giftPrice  礼物单价（元）
     * @param int   $quantity   礼物数量
     * @return array{old_level: int, new_level: int, contribution: string, is_upgrade: bool, benefits: array}
     * @throws AdminResponseException
     */
    public function handleGiftContribution(int $userId, int $liveRoomId, float $giftPrice, int $quantity): array
    {
        $contributionAdd = bcmul((string)$giftPrice, (string)$quantity, 2);

        return $this->transaction(function () use ($userId, $liveRoomId, $contributionAdd) {
            // 1. 查询或初始化用户直播间等级记录
            $record = $this->getModel()->where([
                'user_id'      => $userId,
                'live_room_id' => $liveRoomId,
            ])->find();

            if (!$record) {
                $record = $this->create([
                    'user_id'      => $userId,
                    'live_room_id' => $liveRoomId,
                    'level'        => 0,
                    'contribution' => '0.00',
                    'gift_count'   => 0,
                ]);
            }

            $oldLevel        = (int)$record['level'];
            $oldContribution = (string)$record['contribution'];

            // 2. 累加贡献值与礼物次数
            $newContribution = bcadd($oldContribution, $contributionAdd, 2);
            $newGiftCount    = (int)$record['gift_count'] + 1;

            // 3. 计算新等级
            $newLevel  = $this->calcLevel($newContribution);
            $isUpgrade = $newLevel > $oldLevel;
            $benefits  = [];

            if ($isUpgrade) {
                // 4. 升级：写入新等级
                $this->update(['id' => $record['id']], [
                    'level'        => $newLevel,
                    'contribution' => $newContribution,
                    'gift_count'   => $newGiftCount,
                ]);

                // 5. 读取新等级权益配置
                $levelConfigService = app()->make(LiveRoomLevelConfigService::class);
                $levelConfig        = $levelConfigService->getModel()->where('level', $newLevel)->find();
                $upgradePoints = 0;

                if ($levelConfig) {
                    $benefits      = $levelConfigService->sanitizeBenefits($levelConfig['benefits'] ?? []);
                    $upgradePoints = (int)($benefits['upgrade_points'] ?? 0);

                    // 发放升级奖励积分
                    if ($upgradePoints > 0) {
                        app()->make(UserAssetService::class)->changeAsset(
                            $userId,
                            'points',
                            $upgradePoints,
                            'in',
                            "直播间 {$liveRoomId} 升级至{$newLevel}级奖励",
                            ['source_type' => 'live_level_upgrade', 'live_room_id' => $liveRoomId]
                        );
                    }
                }

                // 6. 触发升级广播事件
                $userService = app()->make(UserService::class);
                $user        = $userService->get(['uid' => $userId], ['nickname', 'avatar']);

                event('live.user.levelUp', [
                    'user_id'        => $userId,
                    'nickname'       => $user['nickname'] ?? '',
                    'avatar'         => $user['avatar'] ?? '',
                    'live_room_id'   => $liveRoomId,
                    'old_level'      => $oldLevel,
                    'new_level'      => $newLevel,
                    'level_name'     => $levelConfig['name'] ?? '',
                    'level_icon'     => $levelConfig['icon'] ?? '',
                    'upgrade_points' => $upgradePoints,
                ]);
            } else {
                // 未升级：仅更新数值
                $this->update(['id' => $record['id']], [
                    'contribution' => $newContribution,
                    'gift_count'   => $newGiftCount,
                ]);
            }

            // 7. 触发贡献榜更新广播（无论是否升级）
            event('live.room.rankUpdate', [
                'live_room_id' => $liveRoomId,
                'rank_list'    => $this->getRoomTopRankList($liveRoomId, 10),
            ]);

            return [
                'old_level'    => $oldLevel,
                'new_level'    => $newLevel,
                'contribution' => $newContribution,
                'is_upgrade'   => $isUpgrade,
                'benefits'     => $benefits,
            ];
        });
    }

    /**
     * 获取用户在指定直播间的等级详情
     *
     * @param int $userId
     * @param int $liveRoomId
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserLevel(int $userId, int $liveRoomId): ?array
    {
        $record = $this->getModel()->where([
            'user_id'      => $userId,
            'live_room_id' => $liveRoomId,
        ])->find();

        if (!$record) {
            return null;
        }

        $levelConfigService = app()->make(LiveRoomLevelConfigService::class);
        $levelConfig = $levelConfigService->getModel()
            ->where('level', $record['level'])
            ->field(['level', 'name', 'icon', 'benefits'])
            ->find();

        return [
            'user_id'      => $userId,
            'live_room_id' => $liveRoomId,
            'level'        => (int)$record['level'],
            'contribution' => (string)$record['contribution'],
            'gift_count'   => (int)$record['gift_count'],
            'name'         => $levelConfig['name'] ?? '',
            'icon'         => $levelConfig['icon'] ?? '',
            'level_name'   => $levelConfig['name'] ?? '',
            'level_icon'   => $levelConfig['icon'] ?? '',
            'benefits'     => $levelConfig ? $levelConfigService->sanitizeBenefits($levelConfig['benefits'] ?? []) : [],
        ];
    }

    /**
     * 批量获取直播间用户等级贡献映射。
     *
     * @param int $liveRoomId
     * @param int[] $userIds
     * @return array<int, array>
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomUserLevelMap(int $liveRoomId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($liveRoomId <= 0 || $userIds === []) {
            return [];
        }

        $levels = $this->getModel()
            ->where('live_room_id', $liveRoomId)
            ->whereIn('user_id', $userIds)
            ->field(['user_id', 'level', 'contribution'])
            ->select()
            ->toArray();

        $levelMap = [];
        foreach ($levels as $level) {
            $levelMap[(int)$level['user_id']] = $level;
        }

        return $levelMap;
    }

    /**
     * 获取直播间贡献排行榜（分页）
     *
     * @param int $liveRoomId
     * @param int $page
     * @param int $limit
     * @return array{total: int, list: array}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomRankList(int $liveRoomId, int $page = 1, int $limit = 10): array
    {
        $total = $this->getModel()
            ->where('live_room_id', $liveRoomId)
            ->where('contribution', '>', 0)
            ->count();

        $list = $this->getModel()
            ->alias('l')
            ->join('user u', 'u.uid = l.user_id')
            ->where('l.live_room_id', $liveRoomId)
            ->where('l.contribution', '>', 0)
            ->field([
                'l.user_id',
                'l.level',
                'l.contribution',
                'l.gift_count',
                'l.create_time',
                'l.update_time',
                'u.nickname',
                'u.avatar',
            ])
            ->order('l.contribution', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'list'  => $this->fillLevelConfigInfo($list, ['name', 'icon']),
        ];
    }

    /**
     * 获取直播间贡献排行榜 Top N（用于广播等内部场景）
     *
     * @param int $liveRoomId
     * @param int $limit
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomTopRankList(int $liveRoomId, int $limit = 10): array
    {
        $list = $this->getModel()
            ->alias('l')
            ->join('user u', 'u.uid = l.user_id')
            ->where('l.live_room_id', $liveRoomId)
            ->where('l.contribution', '>', 0)
            ->field([
                'l.user_id',
                'l.level',
                'l.contribution',
                'l.gift_count',
                'u.nickname',
                'u.avatar',
            ])
            ->order('l.contribution', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return $this->fillLevelConfigInfo($list, ['icon']);
    }

    /**
     * 计算当前贡献值对应的等级
     *
     * @param float|string $contribution 累计贡献值
     * @return int 等级值（0 = 无等级）
     */
    private function calcLevel(float|string $contribution): int
    {
        $levels       = app()->make(LiveRoomLevelConfigService::class)->getEnabledLevels();
        $contribution = (string)$contribution;
        $currentLevel = 0;

        foreach ($levels as $config) {
            if (bccomp($contribution, (string)$config['min_contribution'], 2) >= 0) {
                $currentLevel = (int)$config['level'];
            } else {
                break; // 已按 min_contribution 升序，可提前退出
            }
        }

        return $currentLevel;
    }

    /**
     * 批量填充列表中每条记录的等级配置信息（解决 N+1 查询问题）
     *
     * @param array $list 待填充的列表（每项需含 level 字段）
     * @param string[] $fields 需要从等级配置中取出的字段，如 ['name', 'icon']
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function fillLevelConfigInfo(array $list, array $fields): array
    {
        if (empty($list)) {
            return $list;
        }

        // 收集列表中出现的所有等级值（去重）
        $levels = array_values(array_unique(array_column($list, 'level')));

        // 一次查询批量获取所有相关等级配置
        $configMap = app()->make(LiveRoomLevelConfigService::class)->getModel()
            ->whereIn('level', $levels)
            ->field(array_merge(['level'], $fields))
            ->select()
            ->toArray();

        // 以 level 为键建立索引，方便 O(1) 查找
        $configIndex = array_column($configMap, null, 'level');

        foreach ($list as &$item) {
            $config = $configIndex[$item['level']] ?? [];
            foreach ($fields as $field) {
                $item['level_' . $field] = $config[$field] ?? '';
            }
        }
        unset($item);

        return $list;
    }
}
