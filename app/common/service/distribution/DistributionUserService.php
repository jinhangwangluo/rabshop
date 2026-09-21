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

namespace app\common\service\distribution;

use app\common\exception\AdminResponseException;
use app\common\model\order\Order;
use app\common\model\user\User;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\order\OrderService;
use app\common\service\user\UserDistributionService;
use app\common\service\user\UserService;

/**
 * 后台分销员列表与订单统计服务。
 */
class DistributionUserService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置资源服务对应模型。
     */
    protected function setModel(): string
    {
        return User::class;
    }

    /**
     * 获取后台分销员列表。
     *
     * @param array $params 查询参数。
     * @return array 分销员候选用户分页、分销配置与统计数据。
     */
    public function getList(array $params = []): array
    {
        $distributionService = app()->make(UserDistributionService::class);
        $config = $distributionService->getConfig();
        [$page, $limit] = $this->resolvePage($params, 15);

        $total = $this->buildUserListQuery($params, $config)->count();
        $list = $this->buildUserListQuery($params, $config)
            ->field([
                'uid',
                'nickname',
                'avatar',
                'phone',
                'status',
                'is_distribution',
                'distribution_permission',
                'spread_uid',
                'create_time',
                'last_time',
            ])
            ->order('uid desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $userIds = array_values(array_unique(array_filter(array_map('intval', array_column($list, 'uid')))));
        $firstStats = $this->getCommissionStatsByLevel($userIds, 1);
        $secondStats = $this->getCommissionStatsByLevel($userIds, 2);

        foreach ($list as $index => $user) {
            $userId = (int)($user['uid'] ?? 0);
            $first = $firstStats[$userId] ?? ['order_count' => 0, 'commission_amount' => 0.0];
            $second = $secondStats[$userId] ?? ['order_count' => 0, 'commission_amount' => 0.0];
            $permissionState = $distributionService->getEffectivePermission($user);

            $list[$index] = array_merge($user, $permissionState, [
                'first_order_count' => (int)$first['order_count'],
                'first_commission_amount' => $this->normalizeMoney((float)$first['commission_amount']),
                'second_order_count' => (int)$second['order_count'],
                'second_commission_amount' => $this->normalizeMoney((float)$second['commission_amount']),
                'total_order_count' => (int)$first['order_count'] + (int)$second['order_count'],
                'total_commission_amount' => $this->moneyAdd(
                    (float)$first['commission_amount'],
                    (float)$second['commission_amount']
                ),
            ]);
        }

        return [
            'list' => $list,
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
            'distribution_config' => $config,
            'stats' => $this->getOverviewStats($config),
        ];
    }

    /**
     * 指定分销模式下增加或取消分销资格。
     *
     * @param int $userId 用户 UID。
     * @param int $permission 分销权限，0=取消资格，1=增加资格。
     * @return array 更新后的用户有效权限。
     * @throws AdminResponseException
     */
    public function setPermission(int $userId, int $permission): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('请指定用户');
        }

        if (!in_array($permission, [UserDistributionService::PERMISSION_FORBID, UserDistributionService::PERMISSION_ALLOW], true)) {
            throw new AdminResponseException('分销资格参数错误');
        }

        $distributionService = app()->make(UserDistributionService::class);
        $config = $distributionService->getConfig();
        if (($config['mode'] ?? '') === UserDistributionService::MODE_ALL) {
            throw new AdminResponseException('人人分销模式下无需手动调整分销资格');
        }

        app()->make(UserService::class)->setDistribution($userId, $permission);

        $user = $this->getModel()
            ->where('uid', $userId)
            ->field(['uid', 'nickname', 'phone', 'status', 'is_distribution', 'distribution_permission'])
            ->find();
        if (!$user instanceof User) {
            throw new AdminResponseException('用户不存在');
        }

        return array_merge($user->toArray(), $distributionService->getEffectivePermission($user));
    }

    /**
     * 获取某个分销员产生佣金的订单明细。
     *
     * @param int $userId 分销员 UID。
     * @param array $params 查询参数。
     * @return array 订单分页列表。
     * @throws AdminResponseException
     */
    public function getCommissionOrders(int $userId, array $params = []): array
    {
        if ($userId <= 0 || !$this->getModel()->where('uid', $userId)->count()) {
            throw new AdminResponseException('用户不存在');
        }

        $level = (int)($params['level'] ?? 1);
        $level = $level === 2 ? 2 : 1;
        [$page, $limit] = $this->resolvePage($params, 10);

        $total = $this->buildCommissionOrderQuery($userId, $level, $params)->count();
        $orders = $this->buildCommissionOrderQuery($userId, $level, $params)
            ->field([
                'id',
                'order_sn',
                'user_id',
                'user_phone',
                'pay_amount',
                'status',
                'commission_status',
                'first_commission_amount',
                'second_commission_amount',
                'create_time',
                'pay_time',
            ])
            ->order('create_time desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $buyerIds = array_values(array_unique(array_filter(array_map('intval', array_column($orders, 'user_id')))));
        $buyerMap = $this->getUserMap($buyerIds);

        foreach ($orders as $index => $order) {
            $buyerId = (int)($order['user_id'] ?? 0);
            $commissionAmount = $level === 2
                ? (float)($order['second_commission_amount'] ?? 0)
                : (float)($order['first_commission_amount'] ?? 0);

            $orders[$index] = [
                'id' => (int)($order['id'] ?? 0),
                'order_sn' => (string)($order['order_sn'] ?? ''),
                'buyer_id' => $buyerId,
                'buyer_nickname' => (string)($buyerMap[$buyerId]['nickname'] ?? ''),
                'buyer_phone' => (string)($order['user_phone'] ?? ($buyerMap[$buyerId]['phone'] ?? '')),
                'pay_amount' => $this->normalizeMoney((float)($order['pay_amount'] ?? 0)),
                'commission_amount' => $this->normalizeMoney($commissionAmount),
                'commission_level' => $level,
                'status' => (int)($order['status'] ?? 0),
                'status_text' => $this->resolveOrderStatusText((int)($order['status'] ?? 0)),
                'commission_status' => (int)($order['commission_status'] ?? 0),
                'commission_status_text' => $this->resolveCommissionStatusText((int)($order['commission_status'] ?? 0)),
                'create_time' => (string)($order['create_time'] ?? ''),
                'pay_time' => (string)($order['pay_time'] ?? ''),
            ];
        }

        return [
            'list' => $orders,
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
        ];
    }

    /**
     * 构造用户列表查询。
     *
     * @param array $params 查询参数。
     * @param array $config 分销配置。
     * @return mixed
     */
    protected function buildUserListQuery(array $params, array $config)
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $nickname = trim((string)($params['nickname'] ?? ''));
        $phone = trim((string)($params['phone'] ?? ''));
        $uid = (int)($params['uid'] ?? 0);
        $status = $params['status'] ?? '';
        $qualification = trim((string)($params['qualification'] ?? ''));

        $query = $this->getModel();
        if ($keyword !== '') {
            $query = $query->where(function ($subQuery) use ($keyword): void {
                $subQuery->where('nickname', 'like', '%' . $keyword . '%')
                    ->whereOr('phone', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $subQuery->whereOr('uid', (int)$keyword);
                }
            });
        }

        if ($nickname !== '') {
            $query = $query->where('nickname', 'like', '%' . $nickname . '%');
        }

        if ($phone !== '') {
            $query = $query->where('phone', 'like', '%' . $phone . '%');
        }

        if ($uid > 0) {
            $query = $query->where('uid', $uid);
        }

        if ($status !== '') {
            $query = $query->where('status', (int)$status);
        }

        if ($qualification !== '') {
            $this->applyQualificationFilter($query, $qualification, $config);
        }

        return $query;
    }

    /**
     * 应用有效分销资格筛选。
     *
     * @param mixed $query 查询对象。
     * @param string $qualification qualified/unqualified。
     * @param array $config 分销配置。
     */
    protected function applyQualificationFilter($query, string $qualification, array $config): void
    {
        $enabled = (bool)($config['enabled'] ?? false);
        $mode = (string)($config['mode'] ?? UserDistributionService::MODE_SPECIFIED);

        if ($qualification === 'qualified') {
            if (!$enabled) {
                $query->where('uid', 0);
                return;
            }

            $query->where('status', 1);
            if ($mode === UserDistributionService::MODE_ALL) {
                $query->where('distribution_permission', '<>', UserDistributionService::PERMISSION_FORBID);
                return;
            }

            $query->where('distribution_permission', UserDistributionService::PERMISSION_ALLOW);
            return;
        }

        if ($qualification !== 'unqualified' || !$enabled) {
            return;
        }

        if ($mode === UserDistributionService::MODE_ALL) {
            $query->where(function ($subQuery): void {
                $subQuery->where('status', '<>', 1)
                    ->whereOr('distribution_permission', UserDistributionService::PERMISSION_FORBID);
            });
            return;
        }

        $query->where(function ($subQuery): void {
            $subQuery->where('status', '<>', 1)
                ->whereOr('distribution_permission', '<>', UserDistributionService::PERMISSION_ALLOW)
                ->whereNull('distribution_permission', 'OR');
        });
    }

    /**
     * 按一级或二级返佣口径聚合订单数量和金额。
     *
     * @param array $userIds 用户 UID 列表。
     * @param int $level 佣金层级，1/2。
     * @return array<int, array{order_count:int, commission_amount:float}>
     */
    protected function getCommissionStatsByLevel(array $userIds, int $level): array
    {
        if ($userIds === []) {
            return [];
        }

        $referrerField = $level === 2 ? 'second_referrer_id' : 'referrer_id';
        $commissionField = $level === 2 ? 'second_commission_amount' : 'first_commission_amount';
        $rows = app()->make(OrderService::class)->getModel()
            ->whereIn($referrerField, $userIds)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('status', $this->paidBusinessStatuses())
            ->where($commissionField, '>', 0)
            ->fieldRaw($referrerField . ' AS user_id, COUNT(*) AS order_count, COALESCE(SUM(' . $commissionField . '), 0) AS commission_amount')
            ->group($referrerField)
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $map[$userId] = [
                'order_count' => (int)($row['order_count'] ?? 0),
                'commission_amount' => $this->normalizeMoney((float)($row['commission_amount'] ?? 0)),
            ];
        }

        return $map;
    }

    /**
     * 构造分销订单明细查询。
     *
     * @param int $userId 分销员 UID。
     * @param int $level 佣金层级。
     * @param array $params 查询参数。
     * @return mixed
     */
    protected function buildCommissionOrderQuery(int $userId, int $level, array $params)
    {
        $referrerField = $level === 2 ? 'second_referrer_id' : 'referrer_id';
        $commissionField = $level === 2 ? 'second_commission_amount' : 'first_commission_amount';
        $orderSn = trim((string)($params['order_sn'] ?? ''));
        $buyerKeyword = trim((string)($params['buyer_keyword'] ?? ''));
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));

        $query = app()->make(OrderService::class)->getModel()
            ->where($referrerField, $userId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('status', $this->paidBusinessStatuses())
            ->where($commissionField, '>', 0);

        if ($orderSn !== '') {
            $query->where('order_sn', 'like', '%' . $orderSn . '%');
        }

        if ($buyerKeyword !== '') {
            $query->where(function ($subQuery) use ($buyerKeyword): void {
                $subQuery->where('user_phone', 'like', '%' . $buyerKeyword . '%');

                if (ctype_digit($buyerKeyword)) {
                    $subQuery->whereOr('user_id', (int)$buyerKeyword);
                }

                $matchedUserIds = $this->getModel()
                    ->where('nickname', 'like', '%' . $buyerKeyword . '%')
                    ->whereOr('phone', 'like', '%' . $buyerKeyword . '%')
                    ->column('uid');
                if ($matchedUserIds !== []) {
                    $subQuery->whereOr('user_id', 'in', array_map('intval', $matchedUserIds));
                }
            });
        }

        if ($startTime !== '') {
            $query->where('create_time', '>=', $startTime . ' 00:00:00');
        }

        if ($endTime !== '') {
            $query->where('create_time', '<=', $endTime . ' 23:59:59');
        }

        return $query;
    }

    /**
     * 获取分销员列表概览数据。
     *
     * @param array $config 分销配置。
     * @return array
     */
    protected function getOverviewStats(array $config): array
    {
        $qualifiedCount = $this->countQualifiedUsers($config);
        $first = $this->getTotalCommissionStats(1);
        $second = $this->getTotalCommissionStats(2);

        return [
            'qualified_count' => $qualifiedCount,
            'first_order_count' => (int)$first['order_count'],
            'first_commission_amount' => $this->normalizeMoney((float)$first['commission_amount']),
            'second_order_count' => (int)$second['order_count'],
            'second_commission_amount' => $this->normalizeMoney((float)$second['commission_amount']),
            'total_order_count' => (int)$first['order_count'] + (int)$second['order_count'],
            'total_commission_amount' => $this->moneyAdd(
                (float)$first['commission_amount'],
                (float)$second['commission_amount']
            ),
        ];
    }

    /**
     * 统计当前配置下有效分销员数量。
     *
     * @param array $config 分销配置。
     * @return int
     */
    protected function countQualifiedUsers(array $config): int
    {
        if (!(bool)($config['enabled'] ?? false)) {
            return 0;
        }

        $query = $this->getModel()->where('status', 1);
        if (($config['mode'] ?? '') === UserDistributionService::MODE_ALL) {
            $query->where('distribution_permission', '<>', UserDistributionService::PERMISSION_FORBID);
        } else {
            $query->where('distribution_permission', UserDistributionService::PERMISSION_ALLOW);
        }

        return (int)$query->count();
    }

    /**
     * 获取全局佣金订单统计。
     *
     * @param int $level 佣金层级。
     * @return array{order_count:int, commission_amount:float}
     */
    protected function getTotalCommissionStats(int $level): array
    {
        $referrerField = $level === 2 ? 'second_referrer_id' : 'referrer_id';
        $commissionField = $level === 2 ? 'second_commission_amount' : 'first_commission_amount';
        $row = app()->make(OrderService::class)->getModel()
            ->where($referrerField, '>', 0)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('status', $this->paidBusinessStatuses())
            ->where($commissionField, '>', 0)
            ->fieldRaw('COUNT(*) AS order_count, COALESCE(SUM(' . $commissionField . '), 0) AS commission_amount')
            ->find();

        return [
            'order_count' => (int)($row['order_count'] ?? 0),
            'commission_amount' => $this->normalizeMoney((float)($row['commission_amount'] ?? 0)),
        ];
    }

    /**
     * 批量获取用户信息映射。
     *
     * @param array $userIds 用户 UID 列表。
     * @return array<int, array>
     */
    protected function getUserMap(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->getModel()
            ->whereIn('uid', $userIds)
            ->field(['uid', 'nickname', 'phone', 'avatar'])
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['uid'] ?? 0)] = $row;
        }

        return $map;
    }

    /**
     * 解析分页参数。
     *
     * @param array $params 查询参数。
     * @param int $defaultLimit 默认每页条数。
     * @return array{0:int,1:int}
     */
    protected function resolvePage(array $params, int $defaultLimit): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = (int)($params['limit'] ?? $defaultLimit);
        $limit = $limit > 0 ? min(100, $limit) : $defaultLimit;

        return [$page, $limit];
    }

    /**
     * 已支付且仍属于有效业务订单的状态集合。
     *
     * @return array<int>
     */
    protected function paidBusinessStatuses(): array
    {
        return [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED];
    }

    /**
     * 订单状态文案。
     *
     * @param int $status 订单状态。
     * @return string
     */
    protected function resolveOrderStatusText(int $status): string
    {
        return match ($status) {
            Order::STATUS_PAID => '已支付',
            Order::STATUS_COMPLETED => '已完成',
            Order::STATUS_WAIT_REVIEW => '待评价',
            default => '未知',
        };
    }

    /**
     * 佣金状态文案。
     *
     * @param int $status 佣金状态。
     * @return string
     */
    protected function resolveCommissionStatusText(int $status): string
    {
        return match ($status) {
            Order::COMMISSION_STATUS_FROZEN => '冻结中',
            Order::COMMISSION_STATUS_GRANTED => '已发放',
            default => '待处理',
        };
    }

    /**
     * 金额标准化为两位小数。
     *
     * @param float|string $amount 原始金额。
     * @return float
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 两位小数金额相加。
     *
     * @param float|string $left 左侧金额。
     * @param float|string $right 右侧金额。
     * @return float
     */
    protected function moneyAdd(float|string $left, float|string $right): float
    {
        return (float)bcadd((string)$left, (string)$right, 2);
    }
}
