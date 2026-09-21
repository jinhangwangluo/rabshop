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

namespace app\common\service\activity;

use app\common\enum\CombinationDataTypeEnum;
use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\activity\UserSigninRecord;
use app\common\model\order\Cart;
use app\common\model\order\Order;
use app\common\model\store\StoreCoupon;
use app\common\model\store\StoreCouponUser;
use app\common\service\BaseService;
use app\common\service\order\CartService;
use app\common\service\order\OrderService;
use app\common\service\store\StoreCouponService;
use app\common\service\store\StoreCouponUserService;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserBrowseRecordService;
use app\common\service\user\UserFavoriteService;

/**
 * 移动端每日签到服务。
 *
 * 负责签到页聚合数据、每日签到奖励发放、每日任务完成状态判断和任务积分领取。
 * 7 天签到奖励来源于组合数据，支持积分和平台优惠券。
 */
class ActivitySigninService extends BaseService
{
    /** @var string 关注任意一个商家店铺任务 */
    public const TASK_FOLLOW_MERCHANT = 'follow_merchant';

    /** @var string 创建一笔商品订单任务 */
    public const TASK_ORDER_GOODS = 'order_goods';

    /** @var string 添加任意商品到购物车任务 */
    public const TASK_ADD_CART = 'add_cart';

    /** @var string 浏览任意专题详情任务 */
    public const TASK_BROWSE_SPECIAL = 'browse_special';

    /** @var string 积分奖励 */
    private const REWARD_TYPE_POINTS = 'points';

    /** @var string 优惠券奖励 */
    private const REWARD_TYPE_COUPON = 'coupon';

    /** @var string 积分和优惠券叠加奖励 */
    private const REWARD_TYPE_MIXED = 'mixed';

    /**
     * 设置签到记录模型。
     */
    protected function setModel(): string
    {
        return UserSigninRecord::class;
    }

    /**
     * 获取签到页聚合数据。
     *
     * @param int $userId 当前登录用户ID。
     * @return array 签到状态、7天进度、积分余额和每日任务列表。
     * @throws AdminResponseException 登录用户无效时抛出。
     */
    public function getMobileIndex(int $userId): array
    {
        $this->assertUser($userId);

        $today = date('Y-m-d');
        $config = $this->getSigninConfig();
        $switches = $this->getPointsSwitches();
        $recordService = app()->make(UserSigninRecordService::class);
        $todayRecord = $recordService->getByDate($userId, $today);
        $latestRecord = $todayRecord ?: $recordService->getLatestBefore($userId, $today);
        $signedToday = (bool)$todayRecord;
        $currentStreak = $this->resolveCurrentStreak($latestRecord, $signedToday);
        $currentCycleDay = $this->resolveCurrentCycleDay($latestRecord, $currentStreak, $signedToday);
        $nextCycleDay = $signedToday ? $currentCycleDay : (($currentStreak % 7) + 1);
        $displayCycleDay = $signedToday ? max(1, $currentCycleDay) : $nextCycleDay;
        $todayReward = $this->getRewardByCycleDay($config['rewards'], $displayCycleDay);
        if ($signedToday && (int)$todayReward['points'] > 0) {
            $todayReward['points'] = (int)($todayRecord['reward_points'] ?? 0);
            $todayReward['reward_label'] = $this->buildRewardLabel($todayReward);
        }
        $todayRewardPoints = (int)$todayReward['points'];

        return [
            'signed_today' => $signedToday,
            'streak_days' => $currentStreak,
            'cycle_day' => $currentCycleDay,
            'next_cycle_day' => $nextCycleDay,
            'daily_points' => $todayRewardPoints,
            'today_reward_points' => $todayRewardPoints,
            'today_reward' => $this->formatRewardPayload($todayReward),
            'points' => $this->getUserPoints($userId),
            'points_switch' => $switches['points_switch'],
            'activity_signin_switch' => $switches['activity_signin_switch'],
            'points_enabled' => $switches['points_enabled'],
            'activity_signin_enabled' => $switches['activity_signin_enabled'],
            'summary' => [
                'title' => $currentStreak > 0 ? '已连签' . $currentStreak . '天' : '开启今日签到',
                'desc' => !$switches['activity_signin_enabled']
                    ? $switches['message']
                    : ($signedToday
                        ? '今日已获得' . $todayReward['reward_label']
                        : '今日签到可得' . $todayReward['reward_label']),
                'button_text' => !$switches['activity_signin_enabled']
                    ? '暂不可签到'
                    : ($signedToday ? '今日已签到' : '立即签到'),
            ],
            'sign_days' => $this->buildSignDays($config['rewards'], $currentCycleDay, $signedToday),
            'tasks' => $switches['activity_signin_enabled']
                ? $this->buildTaskList($userId, $config['tasks'], $today)
                : [],
        ];
    }

    /**
     * 执行今日签到，并按 7 天组合数据发放积分或优惠券奖励。
     *
     * @param int $userId 当前登录用户ID。
     * @return array 最新签到页数据，并附加本次签到结果 sign_result。
     * @throws AdminResponseException 今日已签到或用户无效时抛出。
     */
    public function signIn(int $userId): array
    {
        $this->assertUser($userId);
        $this->assertSigninEnabled();

        $today = date('Y-m-d');
        $recordService = app()->make(UserSigninRecordService::class);
        if ($recordService->getByDate($userId, $today)) {
            throw new AdminResponseException('今日已签到');
        }

        $config = $this->getSigninConfig();
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterdayRecord = $recordService->getByDate($userId, $yesterday);
        $streakDays = $yesterdayRecord ? ((int)($yesterdayRecord['streak_days'] ?? 0) + 1) : 1;
        $cycleDay = (($streakDays - 1) % 7) + 1;
        $reward = $this->getRewardByCycleDay($config['rewards'], $cycleDay);
        $rewardPoints = (int)$reward['points'];
        $remark = '连续签到第' . $streakDays . '天';
        $rewardResult = [];

        // 签到记录和奖励发放必须在同一事务内完成，避免记录成功但奖励未到账。
        $this->transaction(function () use (
            $recordService,
            $userId,
            $today,
            $reward,
            $rewardPoints,
            $streakDays,
            $cycleDay,
            $remark,
            &$rewardResult
        ) {
            $recordService->create([
                'user_id' => $userId,
                'sign_date' => $today,
                'reward_points' => $rewardPoints,
                'streak_days' => $streakDays,
                'cycle_day' => $cycleDay,
                'remark' => $remark,
            ]);

            $rewardResult = $this->grantSigninReward($userId, $reward, $remark);
        });

        $payload = $this->getMobileIndex($userId);
        $payload['sign_result'] = array_merge($this->formatRewardPayload($reward), [
            'reward_points' => $rewardPoints,
            'streak_days' => $streakDays,
            'cycle_day' => $cycleDay,
        ], $rewardResult);

        return $payload;
    }

    /**
     * 领取已完成任务的积分奖励。
     *
     * @param int $userId 当前登录用户ID。
     * @param string $taskKey 任务标识，见 TASK_* 常量。
     * @return array 最新签到页数据，并附加本次任务领奖结果 task_result。
     * @throws AdminResponseException 任务关闭、未完成、已领取或用户无效时抛出。
     */
    public function claimTask(int $userId, string $taskKey): array
    {
        $this->assertUser($userId);
        $this->assertSigninEnabled();
        $taskKey = trim($taskKey);

        $config = $this->getSigninConfig();
        $task = $config['tasks'][$taskKey] ?? null;
        if (!$task || !$task['enabled']) {
            throw new AdminResponseException('任务已关闭');
        }

        $today = date('Y-m-d');
        $recordService = app()->make(UserSigninTaskRecordService::class);
        if ($recordService->getByTaskDate($userId, $taskKey, $today)) {
            throw new AdminResponseException('今日任务积分已领取');
        }

        if (!$this->isTaskCompleted($userId, $taskKey)) {
            throw new AdminResponseException('请先完成任务');
        }

        $points = max(0, (int)$task['points']);
        $remark = '完成任务：' . $task['title'];

        // 任务领奖记录和积分流水同事务提交，唯一索引负责兜底防止同日重复领取。
        $this->transaction(function () use ($recordService, $userId, $taskKey, $today, $points, $remark) {
            $recordService->create([
                'user_id' => $userId,
                'task_key' => $taskKey,
                'task_date' => $today,
                'reward_points' => $points,
                'remark' => $remark,
            ]);

            $this->grantPoints($userId, $points, '签到任务奖励', 'activity_signin_task_' . $taskKey, $remark . '，获得' . $points . '积分');
        });

        $payload = $this->getMobileIndex($userId);
        $payload['task_result'] = [
            'task_key' => $taskKey,
            'reward_points' => $points,
        ];

        return $payload;
    }

    /**
     * 读取并标准化签到配置。
     *
     * 奖励来源于组合数据：
     * - activity_signin_reward：7天签到奖励组合。
     *
     * 每日任务配置仍来源于 config 表：
     * - activity_signin_task_*：每日任务开关和任务积分。
     */
    private function getSigninConfig(): array
    {
        return [
            'rewards' => $this->getSigninRewards(),
            'tasks' => $this->getTaskConfig(),
        ];
    }

    /**
     * 读取积分和签到开关状态。
     */
    private function getPointsSwitches(): array
    {
        $pointsEnabled = (int)db_config(ConfigKeyEnum::POINTS_ENABLED, '1') === 1;
        $signinConfigEnabled = (int)db_config(ConfigKeyEnum::POINTS_ACTIVITY_SIGNIN_ENABLED, '1') === 1;
        $signinEnabled = $pointsEnabled && $signinConfigEnabled;

        return [
            'points_switch' => $pointsEnabled ? 1 : 0,
            'activity_signin_switch' => $signinEnabled ? 1 : 0,
            'points_enabled' => $pointsEnabled,
            'activity_signin_config_enabled' => $signinConfigEnabled,
            'activity_signin_enabled' => $signinEnabled,
            'message' => $signinEnabled ? 'ok' : ($pointsEnabled ? '签到功能已关闭' : '积分功能已关闭'),
        ];
    }

    /**
     * 校验签到入口是否允许发放签到奖励。
     *
     * @throws AdminResponseException 积分总开关或签到开关关闭时抛出。
     */
    private function assertSigninEnabled(): void
    {
        $switches = $this->getPointsSwitches();
        if (!$switches['points_enabled']) {
            throw new AdminResponseException('积分功能已关闭');
        }

        if (!$switches['activity_signin_config_enabled']) {
            throw new AdminResponseException('签到功能已关闭');
        }
    }

    /**
     * 获取并校验 7 天签到奖励组合数据。
     *
     * @return array<int, array> 以 day 为键的奖励配置
     */
    private function getSigninRewards(): array
    {
        $items = db_combination_data(CombinationDataTypeEnum::ACTIVITY_SIGNIN_REWARD, 0, []);
        if ($items === []) {
            throw new AdminResponseException('签到奖励配置缺失，请先配置7天签到奖励');
        }

        $rewards = [];
        foreach ($items as $item) {
            $reward = $this->normalizeSigninReward($item);
            if ($reward === null) {
                continue;
            }
            $rewards[(int)$reward['day']] = $reward;
        }

        for ($day = 1; $day <= 7; $day++) {
            if (!isset($rewards[$day])) {
                throw new AdminResponseException('签到奖励配置不足7天，请先完成配置');
            }
        }

        ksort($rewards);
        return $this->appendRewardCouponInfo($rewards);
    }

    /**
     * 标准化单天签到奖励。
     *
     * @param array $item 组合数据项
     * @return array|null
     */
    private function normalizeSigninReward(array $item): array|null
    {
        $day = (int)($item['day'] ?? 0);
        if ($day < 1 || $day > 7) {
            return null;
        }

        $points = max(0, (int)($item['points'] ?? 0));
        $couponId = max(0, (int)($item['coupon_id'] ?? 0));
        if ($points <= 0 && $couponId <= 0) {
            throw new AdminResponseException('第' . $day . '天签到奖励未配置');
        }

        return [
            'id' => (int)($item['id'] ?? $item['combination_data_id'] ?? 0),
            'day' => $day,
            'reward_type' => $this->resolveRewardType($points, $couponId),
            'points' => $points,
            'coupon_id' => $couponId,
            'coupon_name' => (string)($item['coupon_name'] ?? ''),
            'sort' => (int)($item['sort'] ?? 0),
            'reward_label' => '',
        ];
    }

    /**
     * 补充优惠券名称和奖励文案。
     *
     * @param array $rewards 签到奖励配置
     * @return array
     */
    private function appendRewardCouponInfo(array $rewards): array
    {
        $couponIds = array_values(array_unique(array_filter(array_map(static function (array $reward): int {
            return (int)($reward['coupon_id'] ?? 0);
        }, $rewards))));
        $couponMap = [];

        if ($couponIds !== []) {
            $rows = app()->make(StoreCouponService::class)->getModel()
                ->whereIn('id', $couponIds)
                ->field(['id', 'name', 'scope_type', 'status'])
                ->select()
                ->toArray();
            $couponMap = array_column($rows, null, 'id');
        }

        foreach ($rewards as $day => $reward) {
            if ((int)$reward['coupon_id'] > 0) {
                $coupon = $couponMap[(int)$reward['coupon_id']] ?? [];
                $reward['coupon_name'] = (string)($coupon['name'] ?? $reward['coupon_name']);
                $reward['coupon_scope_type'] = (int)($coupon['scope_type'] ?? 0);
                $reward['coupon_status'] = (int)($coupon['status'] ?? 0);
            }

            $reward['reward_label'] = $this->buildRewardLabel($reward);
            $rewards[$day] = $reward;
        }

        return $rewards;
    }

    /**
     * 根据 7 天周期天数读取奖励配置。
     *
     * @param array $rewards 签到奖励配置
     * @param int $cycleDay 7 天周期内天数
     * @return array
     */
    private function getRewardByCycleDay(array $rewards, int $cycleDay): array
    {
        if ($cycleDay < 1 || $cycleDay > 7 || !isset($rewards[$cycleDay])) {
            throw new AdminResponseException('签到奖励配置异常，请先检查7天奖励');
        }

        return $rewards[$cycleDay];
    }

    /**
     * 构建奖励展示文案。
     *
     * @param array $reward 奖励配置
     * @return string
     */
    private function buildRewardLabel(array $reward): string
    {
        $labels = [];
        $points = (int)($reward['points'] ?? 0);
        if ($points > 0) {
            $labels[] = $points . '积分';
        }

        if ((int)($reward['coupon_id'] ?? 0) > 0) {
            $couponName = trim((string)($reward['coupon_name'] ?? ''));
            $labels[] = $couponName !== '' ? $couponName : '优惠券#' . (int)($reward['coupon_id'] ?? 0);
        }

        return $labels !== [] ? implode(' + ', $labels) : '无奖励';
    }

    /**
     * 格式化奖励字段给移动端。
     *
     * @param array $reward 奖励配置
     * @return array
     */
    private function formatRewardPayload(array $reward): array
    {
        $points = (int)($reward['points'] ?? 0);
        $couponId = (int)($reward['coupon_id'] ?? 0);

        return [
            'reward_type' => $this->resolveRewardType($points, $couponId),
            'reward_types' => $this->resolveRewardTypes($points, $couponId),
            'reward_points' => $points,
            'coupon_id' => $couponId,
            'coupon_name' => (string)($reward['coupon_name'] ?? ''),
            'reward_label' => (string)($reward['reward_label'] ?? $this->buildRewardLabel($reward)),
        ];
    }

    /**
     * 发放签到奖励。
     *
     * @param int $userId 用户ID
     * @param array $reward 奖励配置
     * @param string $remark 签到备注
     * @return array
     */
    private function grantSigninReward(int $userId, array $reward, string $remark): array
    {
        $points = max(0, (int)($reward['points'] ?? 0));
        $couponId = (int)($reward['coupon_id'] ?? 0);
        $result = [
            'reward_type' => $this->resolveRewardType($points, $couponId),
            'reward_types' => $this->resolveRewardTypes($points, $couponId),
            'reward_points' => $points,
        ];

        if ($points > 0) {
            $this->grantPoints($userId, $points, '每日签到奖励', 'activity_signin', $remark . '，获得' . $points . '积分');
        }

        if ($couponId > 0) {
            $result['coupon'] = $this->grantCouponReward($userId, $reward);
        }

        return $result;
    }

    /**
     * 发放签到优惠券奖励。
     *
     * @param int $userId 用户ID
     * @param array $reward 奖励配置
     * @return array
     */
    private function grantCouponReward(int $userId, array $reward): array
    {
        $couponId = (int)($reward['coupon_id'] ?? 0);
        $this->assertSigninRewardCoupon($couponId);
        return app()->make(StoreCouponUserService::class)->sendCouponToUser(
            $userId,
            $couponId,
            StoreCouponUser::RECEIVE_TYPE_SEND
        );
    }

    /**
     * 校验签到奖励优惠券范围。
     *
     * @param int $couponId 优惠券ID
     * @return void
     */
    private function assertSigninRewardCoupon(int $couponId): void
    {
        $coupon = app()->make(StoreCouponService::class)->get($couponId);
        if (!$coupon) {
            throw new AdminResponseException('签到奖励优惠券不存在');
        }

        if ((int)$coupon->merchant_id !== 0) {
            throw new AdminResponseException('签到奖励仅支持平台优惠券');
        }

        if ((int)$coupon->status !== StoreCoupon::STATUS_OPEN) {
            throw new AdminResponseException('签到奖励优惠券已关闭或失效');
        }

        if (!in_array((int)$coupon->scope_type, [StoreCoupon::SCOPE_TYPE_PLATFORM, StoreCoupon::SCOPE_TYPE_CATEGORY], true)) {
            throw new AdminResponseException('签到奖励仅支持平台通用券和平台品类券');
        }
    }

    /**
     * 根据奖励内容推导移动端兼容奖励类型。
     */
    private function resolveRewardType(int $points, int $couponId): string
    {
        if ($points > 0 && $couponId > 0) {
            return self::REWARD_TYPE_MIXED;
        }

        if ($couponId > 0) {
            return self::REWARD_TYPE_COUPON;
        }

        return self::REWARD_TYPE_POINTS;
    }

    /**
     * 根据奖励内容推导奖励类型列表。
     *
     * @return array<int, string>
     */
    private function resolveRewardTypes(int $points, int $couponId): array
    {
        $types = [];
        if ($points > 0) {
            $types[] = self::REWARD_TYPE_POINTS;
        }
        if ($couponId > 0) {
            $types[] = self::REWARD_TYPE_COUPON;
        }

        return $types;
    }

    /**
     * 获取签到任务配置。
     *
     * @return array 任务定义合并后台开关和积分后的配置。
     */
    private function getTaskConfig(): array
    {
        $definitions = $this->getTaskDefinitions();
        $result = [];
        foreach ($definitions as $key => $definition) {
            $result[$key] = array_merge($definition, [
                'enabled' => (int)db_config($definition['enabled_key'], '1') === 1,
                'points' => max(0, (int)db_config($definition['points_key'], $definition['default_points'])),
            ]);
        }

        return $result;
    }

    /**
     * 签到任务定义。
     *
     * 这里只描述任务元信息、跳转入口和配置 key；是否完成由 isTaskCompleted() 根据业务表判断。
     */
    private function getTaskDefinitions(): array
    {
        return [
            self::TASK_FOLLOW_MERCHANT => [
                'key' => self::TASK_FOLLOW_MERCHANT,
                'title' => '关注一个店铺',
                'desc' => '关注任意一个商家店铺后可领取',
                'icon' => 'shop',
                'action_url' => '/pages/contents/search/index?type=shop',
                'enabled_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_ENABLED,
                'points_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_POINTS,
                'default_points' => 5,
            ],
            self::TASK_ORDER_GOODS => [
                'key' => self::TASK_ORDER_GOODS,
                'title' => '下单一个商品',
                'desc' => '创建一笔商品订单后可领取',
                'icon' => 'compose',
                'action_url' => '/pages/contents/search/index?type=product',
                'enabled_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_ENABLED,
                'points_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_POINTS,
                'default_points' => 10,
            ],
            self::TASK_ADD_CART => [
                'key' => self::TASK_ADD_CART,
                'title' => '加入购物车',
                'desc' => '添加任意商品到购物车后可领取',
                'icon' => 'cart',
                'action_url' => '/pages/contents/search/index?type=product',
                'enabled_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_ENABLED,
                'points_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_POINTS,
                'default_points' => 5,
            ],
            self::TASK_BROWSE_SPECIAL => [
                'key' => self::TASK_BROWSE_SPECIAL,
                'title' => '浏览一个专题',
                'desc' => '进入任意专题详情后可领取',
                'icon' => 'eye',
                'action_url' => '/pages/contents/special/index',
                'enabled_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_ENABLED,
                'points_key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_POINTS,
                'default_points' => 5,
            ],
        ];
    }

    /**
     * 构造移动端任务列表。
     *
     * @param int $userId 当前登录用户ID。
     * @param array $tasks 已合并后台配置的任务定义。
     * @param string $today 当前日期，格式 Y-m-d。
     * @return array 移动端可直接渲染的任务列表。
     */
    private function buildTaskList(int $userId, array $tasks, string $today): array
    {
        $claimedMap = app()->make(UserSigninTaskRecordService::class)->getClaimedMap($userId, $today);
        $list = [];

        foreach ($tasks as $key => $task) {
            if (!$task['enabled']) {
                continue;
            }

            $claimed = isset($claimedMap[$key]);
            $completed = $this->isTaskCompleted($userId, $key);
            $status = $claimed ? 'claimed' : ($completed ? 'ready' : 'todo');

            $list[] = [
                'key' => $key,
                'title' => $task['title'],
                'desc' => $task['desc'],
                'points' => (int)$task['points'],
                'icon' => $task['icon'],
                'action_url' => $task['action_url'],
                'enabled' => true,
                'completed' => $completed,
                'claimed' => $claimed,
                'status' => $status,
                'button_text' => match ($status) {
                    'claimed' => '已领取',
                    'ready' => '领取',
                    default => '去完成',
                },
            ];
        }

        return $list;
    }

    /**
     * 判断任务行为是否已完成。
     *
     * 完成依据：
     * - 关注店铺：user_favorite 中存在 merchant 类型且 status=1。
     * - 下单商品：order 中存在未取消/未退款的商品订单。
     * - 加入购物车：cart 中存在非立即购买的有效商品购物车记录。
     * - 浏览专题：user_browse_record 中存在 special 类型记录。
     */
    private function isTaskCompleted(int $userId, string $taskKey): bool
    {
        return match ($taskKey) {
            self::TASK_FOLLOW_MERCHANT => app()->make(UserFavoriteService::class)->count([
                'user_id' => $userId,
                'target_type' => UserFavoriteService::TARGET_TYPE_MERCHANT,
                'status' => 1,
            ]) > 0,
            self::TASK_ORDER_GOODS => app()->make(OrderService::class)->getModel()
                ->where('user_id', $userId)
                ->where('order_type', Order::ORDER_TYPE_GOODS)
                ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
                ->count() > 0,
            self::TASK_ADD_CART => app()->make(CartService::class)->getModel()
                ->where('user_id', $userId)
                ->where('order_type', Cart::ORDER_TYPE_GOODS)
                ->where('is_new', 0)
                ->where('status', 1)
                ->count() > 0,
            self::TASK_BROWSE_SPECIAL => app()->make(UserBrowseRecordService::class)->count([
                'user_id' => $userId,
                'target_type' => UserBrowseRecordService::TARGET_TYPE_SPECIAL,
            ]) > 0,
            default => false,
        };
    }

    /**
     * 构造7天签到展示数据。
     *
     * @param array $rewards 第1天到第7天的奖励配置。
     * @param int $currentCycleDay 当前7天周期内已签到到第几天。
     * @param bool $signedToday 今日是否已签到。
     * @return array 移动端签到进度节点。
     */
    private function buildSignDays(array $rewards, int $currentCycleDay, bool $signedToday): array
    {
        $weekNames = ['第1天', '第2天', '第3天', '第4天', '第5天', '第6天', '第7天'];
        $checkedDay = $signedToday ? $currentCycleDay : max(0, $currentCycleDay);
        $result = [];

        for ($day = 1; $day <= 7; $day++) {
            $reward = $this->getRewardByCycleDay($rewards, $day);
            $result[] = [
                'key' => 'day_' . $day,
                'day' => $day,
                'week' => $weekNames[$day - 1] ?? ('第' . $day . '天'),
                'reward' => (int)$reward['points'],
                'reward_type' => $reward['reward_type'],
                'reward_types' => $this->resolveRewardTypes((int)$reward['points'], (int)$reward['coupon_id']),
                'reward_points' => (int)$reward['points'],
                'coupon_id' => (int)$reward['coupon_id'],
                'coupon_name' => (string)$reward['coupon_name'],
                'reward_label' => (string)$reward['reward_label'],
                'checked' => $checkedDay > 0 && $day <= $checkedDay,
            ];
        }

        return $result;
    }

    /**
     * 计算当前连续签到天数。
     *
     * 仅今天或昨天有连续记录时保持连签，断签后页面进度从0开始。
     */
    private function resolveCurrentStreak(mixed $latestRecord, bool $signedToday): int
    {
        if (!$latestRecord) {
            return 0;
        }

        $signDate = (string)($latestRecord['sign_date'] ?? '');
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($signedToday || $signDate === $yesterday) {
            return max(0, (int)($latestRecord['streak_days'] ?? 0));
        }

        return 0;
    }

    /**
     * 计算当前7天周期内的展示天数。
     */
    private function resolveCurrentCycleDay(mixed $latestRecord, int $currentStreak, bool $signedToday): int
    {
        if ($currentStreak <= 0) {
            return 0;
        }

        if ($signedToday && $latestRecord) {
            return max(1, min(7, (int)($latestRecord['cycle_day'] ?? (($currentStreak - 1) % 7 + 1))));
        }

        $cycleDay = $currentStreak % 7;
        return $cycleDay === 0 ? 7 : $cycleDay;
    }

    /**
     * 获取用户当前积分余额。
     */
    private function getUserPoints(int $userId): int
    {
        $asset = app()->make(UserAssetService::class)->getInfo($userId);
        return (int)($asset['points'] ?? 0);
    }

    /**
     * 发放积分并写入资产流水。
     *
     * UserAssetService::changeAsset 会同步更新 user_asset.points 和 user_asset_log。
     */
    private function grantPoints(int $userId, int $points, string $remark, string $sourceType, string $sourceDesc): void
    {
        if ($points <= 0) {
            return;
        }

        app()->make(UserAssetService::class)->changeAsset(
            $userId,
            UserAssetService::TYPE_POINTS,
            (string)$points,
            'in',
            $remark,
            [
                'source_type' => $sourceType,
                'source_desc' => $sourceDesc,
            ]
        );
    }

    /**
     * 校验移动端登录用户。
     *
     * @throws AdminResponseException
     */
    private function assertUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }
    }
}
