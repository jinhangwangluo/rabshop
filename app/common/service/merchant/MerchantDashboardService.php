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

namespace app\common\service\merchant;

use app\common\exception\AdminResponseException;
use app\common\model\merchant\Merchant;
use app\common\model\order\Order;
use app\common\model\store\StoreCouponUser;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\SpecialService;
use app\common\service\order\OrderService;
use app\common\service\store\StoreCouponUserService;
use app\common\service\user\UserBrowseRecordService;
use app\common\service\user\UserFavoriteService;
use DateInterval;
use DateTime;

/**
 * 商户首页统计聚合服务。
 */
class MerchantDashboardService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置对应模型。
     */
    protected function setModel(): string
    {
        return Merchant::class;
    }

    /**
     * 获取商户首页聚合数据。
     *
     * @param int $merchantId 商户ID
     * @param array $params 查询参数，支持 range/start_time/end_time
     * @return array
     * @throws AdminResponseException
     */
    public function home(int $merchantId, array $params = []): array
    {
        $dateRange = $this->resolveDateRange($params);

        return [
            'merchant' => $this->buildMerchant($merchantId),
            'cards' => $this->buildCards($merchantId),
            'quick_actions' => $this->buildQuickActions(),
            'order_trend' => $this->buildOrderTrend($merchantId, $dateRange),
            'user_overview' => $this->buildUserOverview($merchantId, $dateRange),
            'content_coupon' => $this->buildContentCoupon($merchantId),
        ];
    }

    /**
     * 商户基础信息。
     *
     * @throws AdminResponseException
     */
    protected function buildMerchant(int $merchantId): array
    {
        $merchant = $this->get($merchantId, [
            'id',
            'mer_name',
            'description',
            'business_status',
            'business_hours',
            'mer_class',
        ]);

        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $data = $merchant->toArray();

        return [
            'id' => (int)($data['id'] ?? 0),
            'name' => trim((string)($data['mer_name'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'business_status' => (int)($data['business_status'] ?? 1),
            'business_status_text' => (int)($data['business_status'] ?? 1) === 1 ? '营业中' : '休息中',
            'business_hours_text' => $this->formatBusinessHoursText($data['business_hours'] ?? []),
            'mer_class_text' => (int)($data['mer_class'] ?? 2) === 1 ? '自营' : '非自营',
        ];
    }

    /**
     * 核心经营指标卡片。
     */
    protected function buildCards(int $merchantId): array
    {
        $today = $this->dayRange(0);
        $yesterday = $this->dayRange(1);

        $totalVisitors = $this->sumBrowseCount($merchantId);
        $todayVisitors = $this->sumBrowseCount($merchantId, $today['start'], $today['end']);
        $yesterdayVisitors = $this->sumBrowseCount($merchantId, $yesterday['start'], $yesterday['end']);

        $totalOrders = (int)$this->paidOrderQuery($merchantId)->count();
        $todayOrders = (int)$this->paidOrderQuery($merchantId, $today['start'], $today['end'])->count();
        $yesterdayOrders = (int)$this->paidOrderQuery($merchantId, $yesterday['start'], $yesterday['end'])->count();

        $totalBuyers = (int)$this->paidOrderQuery($merchantId)->distinct(true)->count('user_id');
        $todayBuyers = (int)$this->paidOrderQuery($merchantId, $today['start'], $today['end'])->distinct(true)->count('user_id');
        $yesterdayBuyers = (int)$this->paidOrderQuery($merchantId, $yesterday['start'], $yesterday['end'])->distinct(true)->count('user_id');

        $totalAmount = $this->normalizeMoney($this->paidOrderQuery($merchantId)->sum('pay_amount'));
        $todayAmount = $this->normalizeMoney($this->paidOrderQuery($merchantId, $today['start'], $today['end'])->sum('pay_amount'));
        $yesterdayAmount = $this->normalizeMoney($this->paidOrderQuery($merchantId, $yesterday['start'], $yesterday['end'])->sum('pay_amount'));

        $totalRefund = $this->sumRefundAmount($this->refundOrderQuery($merchantId));
        $todayRefund = $this->sumRefundAmount($this->refundOrderQuery($merchantId, $today['start'], $today['end']));
        $yesterdayRefund = $this->sumRefundAmount($this->refundOrderQuery($merchantId, $yesterday['start'], $yesterday['end']));

        $totalAverage = $totalOrders > 0 ? $this->normalizeMoney($totalAmount / $totalOrders) : 0.00;
        $todayAverage = $todayOrders > 0 ? $this->normalizeMoney($todayAmount / $todayOrders) : 0.00;
        $yesterdayAverage = $yesterdayOrders > 0 ? $this->normalizeMoney($yesterdayAmount / $yesterdayOrders) : 0.00;

        return [
            $this->card('visitors', '访客数', $totalVisitors, '', $this->compareText($todayVisitors, $yesterdayVisitors)),
            $this->card('buyers', '下单买家数', $totalBuyers, '', $this->compareText($todayBuyers, $yesterdayBuyers)),
            $this->card('orders', '订单数量', $totalOrders, '', $this->compareText($todayOrders, $yesterdayOrders)),
            $this->card('amount', '成交金额', $totalAmount, '¥', $this->compareText($todayAmount, $yesterdayAmount)),
            $this->card('refund', '退款金额', $totalRefund, '¥', $this->compareText($todayRefund, $yesterdayRefund)),
            $this->card('avg', '客单价', $totalAverage, '¥', $this->compareText($todayAverage, $yesterdayAverage)),
        ];
    }

    /**
     * 快捷入口。
     */
    protected function buildQuickActions(): array
    {
        return [
            ['title' => '发布内容', 'icon' => 'el-icon-edit-outline', 'type' => 'primary', 'route' => 'ContentArticleCreate'],
            ['title' => '专题管理', 'icon' => 'el-icon-notebook-2', 'type' => 'primary', 'route' => 'SpecialManage'],
            ['title' => '订单管理', 'icon' => 'el-icon-s-order', 'type' => 'info', 'route' => 'MerchantOrderList'],
            ['title' => '资金提现', 'icon' => 'el-icon-s-finance', 'type' => 'warning', 'route' => 'MerchantFinanceWithdraw'],
            ['title' => '优惠券', 'icon' => 'el-icon-ticket', 'type' => 'success', 'route' => 'MerchantCouponList'],
            ['title' => '直播管理', 'icon' => 'el-icon-video-camera', 'type' => 'danger', 'route' => 'MerchantLiveRoomList'],
            ['title' => '店铺设置', 'icon' => 'el-icon-setting', 'type' => 'info', 'route' => 'SettingMerchant'],
        ];
    }

    /**
     * 成交趋势。
     */
    protected function buildOrderTrend(int $merchantId, array $range): array
    {
        $rows = $this->paidOrderQuery($merchantId, $range['start_time'], $range['end_time'])
            ->field("DATE_FORMAT(create_time, '%Y-%m-%d') as stat_date, SUM(pay_amount) as amount, COUNT(*) as order_count")
            ->group('stat_date')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $date = (string)($row['stat_date'] ?? '');
            if ($date === '') {
                continue;
            }

            $map[$date] = [
                'amount' => $this->normalizeMoney($row['amount'] ?? 0),
                'count' => (int)($row['order_count'] ?? 0),
            ];
        }

        $amounts = [];
        $counts = [];
        foreach ($range['dates'] as $date) {
            $amounts[] = $map[$date]['amount'] ?? 0.00;
            $counts[] = $map[$date]['count'] ?? 0;
        }

        return [
            'dates' => $range['dates'],
            'amounts' => $amounts,
            'counts' => $counts,
        ];
    }

    /**
     * 用户数据概览。
     */
    protected function buildUserOverview(int $merchantId, array $range): array
    {
        $favoriteService = app()->make(UserFavoriteService::class);
        $browseService = app()->make(UserBrowseRecordService::class);

        $newUsers = (int)$favoriteService->getModel()
            ->where('merchant_id', $merchantId)
            ->where('target_type', UserFavoriteService::TARGET_TYPE_MERCHANT)
            ->where('target_id', $merchantId)
            ->where('status', 1)
            ->whereBetweenTime('create_time', $range['start_time'], $range['end_time'])
            ->distinct(true)
            ->count('user_id');

        $activeUsers = (int)$browseService->getModel()
            ->where('merchant_id', $merchantId)
            ->where('user_id', '>', 0)
            ->whereBetweenTime('last_browse_time', $range['start_time'], $range['end_time'])
            ->distinct(true)
            ->count('user_id');

        $repeatRows = $this->paidOrderQuery($merchantId, $range['start_time'], $range['end_time'])
            ->field('user_id, COUNT(*) as order_count')
            ->group('user_id')
            ->having('COUNT(*) > 1')
            ->select()
            ->toArray();
        $repeatUsers = count($repeatRows);

        $buyers = (int)$this->paidOrderQuery($merchantId, $range['start_time'], $range['end_time'])
            ->distinct(true)
            ->count('user_id');

        return [
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
            'repeat_users' => $repeatUsers,
            'conversion_rate' => $this->percent($buyers, $activeUsers),
            'trend' => $this->buildUserTrend($merchantId, $range),
        ];
    }

    /**
     * 专题与优惠券概览。
     */
    protected function buildContentCoupon(int $merchantId): array
    {
        $specialService = app()->make(SpecialService::class);
        $couponUserService = app()->make(StoreCouponUserService::class);

        $specialCount = (int)$specialService->getModel()
            ->where('merchant_id', $merchantId)
            ->count();
        $specialBuyCount = (int)$specialService->getModel()
            ->where('merchant_id', $merchantId)
            ->sum('buy_count');
        $specialViewCount = (int)$specialService->getModel()
            ->where('merchant_id', $merchantId)
            ->sum('view_count');
        $couponReceived = (int)$couponUserService->getModel()
            ->where('merchant_id', $merchantId)
            ->count();
        $couponUsed = (int)$couponUserService->getModel()
            ->where('merchant_id', $merchantId)
            ->where('status', StoreCouponUser::STATUS_USED)
            ->count();
        $couponWriteoffAmount = $this->normalizeMoney($couponUserService->getModel()
            ->where('merchant_id', $merchantId)
            ->where('status', StoreCouponUser::STATUS_USED)
            ->sum('discount_amount'));

        return [
            'special_count' => $specialCount,
            'special_buy_count' => $specialBuyCount,
            'special_conversion_rate' => $this->percent($specialBuyCount, $specialViewCount),
            'coupon_received' => $couponReceived,
            'coupon_used' => $couponUsed,
            'coupon_writeoff_rate' => $this->percent($couponUsed, $couponReceived),
            'coupon_writeoff_amount' => $couponWriteoffAmount,
        ];
    }

    /**
     * 用户活跃趋势。
     */
    protected function buildUserTrend(int $merchantId, array $range): array
    {
        $rows = app()->make(UserBrowseRecordService::class)->getModel()
            ->where('merchant_id', $merchantId)
            ->where('user_id', '>', 0)
            ->whereBetweenTime('last_browse_time', $range['start_time'], $range['end_time'])
            ->field("DATE_FORMAT(last_browse_time, '%Y-%m-%d') as stat_date, COUNT(DISTINCT user_id) as user_count")
            ->group('stat_date')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $date = (string)($row['stat_date'] ?? '');
            if ($date !== '') {
                $map[$date] = (int)($row['user_count'] ?? 0);
            }
        }

        $trend = [];
        foreach ($range['dates'] as $date) {
            $trend[] = [
                'date' => $date,
                'value' => $map[$date] ?? 0,
            ];
        }

        return $trend;
    }

    /**
     * 已支付业务订单查询。
     */
    protected function paidOrderQuery(int $merchantId, string $startTime = '', string $endTime = '')
    {
        $query = app()->make(OrderService::class)->getModel()
            ->where('merchant_id', $merchantId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->whereIn('status', $this->paidBusinessStatuses());

        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime('create_time', $startTime, $endTime);
        }

        return $query;
    }

    /**
     * 已支付业务订单状态集合。
     */
    protected function paidBusinessStatuses(): array
    {
        return [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED];
    }

    /**
     * 退款中/已退款订单查询。
     */
    protected function refundOrderQuery(int $merchantId, string $startTime = '', string $endTime = '')
    {
        $query = app()->make(OrderService::class)->getModel()
            ->where('merchant_id', $merchantId)
            ->where('order_role', '<>', Order::ROLE_MAIN)
            ->where(function ($subQuery): void {
                $subQuery->whereIn('status', [Order::STATUS_REFUNDING, Order::STATUS_REFUNDED])
                    ->whereOr('refund_status', 'in', [1, 2]);
            });

        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime('create_time', $startTime, $endTime);
        }

        return $query;
    }

    /**
     * 汇总退款金额，优先取 refund_amount，否则回落 pay_amount。
     */
    protected function sumRefundAmount($query): float
    {
        $row = $query
            ->fieldRaw('COALESCE(SUM(CASE WHEN refund_amount > 0 THEN refund_amount ELSE pay_amount END), 0) AS total')
            ->find();

        return $this->normalizeMoney($row['total'] ?? 0);
    }

    /**
     * 汇总浏览次数。
     */
    protected function sumBrowseCount(int $merchantId, string $startTime = '', string $endTime = ''): int
    {
        $query = app()->make(UserBrowseRecordService::class)->getModel()
            ->where('merchant_id', $merchantId);

        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime('last_browse_time', $startTime, $endTime);
        }

        return (int)$query->sum('browse_count');
    }

    /**
     * 卡片结构。
     */
    protected function card(string $key, string $title, float|int $value, string $unit, array $compare): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'value' => $value,
            'unit' => $unit,
            'compare_text' => $compare['text'],
            'trend' => $compare['trend'],
        ];
    }

    /**
     * 较昨日文案。
     */
    protected function compareText(float|int $current, float|int $previous): array
    {
        if ((float)$previous <= 0) {
            return ['text' => '较昨日 --', 'trend' => 'flat'];
        }

        $percent = round(((float)$current - (float)$previous) / (float)$previous * 100, 1);
        if ($percent > 0) {
            return ['text' => '较昨日 ↑ ' . abs($percent) . '%', 'trend' => 'up'];
        }

        if ($percent < 0) {
            return ['text' => '较昨日 ↓ ' . abs($percent) . '%', 'trend' => 'down'];
        }

        return ['text' => '较昨日 0%', 'trend' => 'flat'];
    }

    /**
     * 归一化金额。
     */
    protected function normalizeMoney(float|int|string|null $amount): float
    {
        return round((float)$amount, 2);
    }

    /**
     * 百分比。
     */
    protected function percent(float|int $numerator, float|int $denominator): float
    {
        if ((float)$denominator <= 0) {
            return 0.0;
        }

        return round((float)$numerator / (float)$denominator * 100, 1);
    }

    /**
     * 解析趋势日期范围，支持预设 7/30 天和自定义日期。
     */
    protected function resolveDateRange(array $params): array
    {
        $start = $this->parseDate((string)($params['start_time'] ?? ''));
        $end = $this->parseDate((string)($params['end_time'] ?? ''));

        if ($start && $end) {
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }

            $days = $start->diff($end)->days + 1;
            if ($days > 366) {
                $start = (clone $end)->modify('-365 day');
            }

            return [
                'dates' => $this->dateAxis($start, $end),
                'start_time' => $start->format('Y-m-d 00:00:00'),
                'end_time' => $end->format('Y-m-d 23:59:59'),
            ];
        }

        return $this->buildPresetDateRange((int)($params['range'] ?? 7));
    }

    /**
     * 预设日期范围。
     */
    protected function buildPresetDateRange(int $range): array
    {
        $range = $range === 30 ? 30 : 7;
        $end = new DateTime(date('Y-m-d'));
        $start = clone $end;
        $start->modify('-' . max(0, $range - 1) . ' day');

        return [
            'dates' => $this->dateAxis($start, $end),
            'start_time' => $start->format('Y-m-d 00:00:00'),
            'end_time' => $end->format('Y-m-d 23:59:59'),
        ];
    }

    /**
     * 解析日期字符串。
     */
    protected function parseDate(string $value): ?DateTime
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    /**
     * 单日时间边界。
     */
    protected function dayRange(int $daysAgo): array
    {
        $date = new DateTime(date('Y-m-d'));
        if ($daysAgo > 0) {
            $date->modify('-' . $daysAgo . ' day');
        }

        return [
            'start' => $date->format('Y-m-d 00:00:00'),
            'end' => $date->format('Y-m-d 23:59:59'),
        ];
    }

    /**
     * 生成日期轴。
     *
     * @return string[]
     */
    protected function dateAxis(DateTime $start, DateTime $end): array
    {
        $cursor = clone $start;
        $result = [];
        while ($cursor <= $end) {
            $result[] = $cursor->format('Y-m-d');
            $cursor->add(new DateInterval('P1D'));
        }

        return $result;
    }

    /**
     * 营业时间展示文本。
     */
    protected function formatBusinessHoursText(mixed $businessHours): string
    {
        if (is_string($businessHours)) {
            $decoded = json_decode($businessHours, true);
            $businessHours = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($businessHours) || $businessHours === []) {
            return '09:00-18:00';
        }

        if (!empty($businessHours['is_all_day'])) {
            return '全天营业';
        }

        $start = trim((string)($businessHours['start'] ?? ''));
        $end = trim((string)($businessHours['end'] ?? ''));

        if ($start !== '' && $end !== '') {
            return $start . '-' . $end;
        }

        return '09:00-18:00';
    }
}
