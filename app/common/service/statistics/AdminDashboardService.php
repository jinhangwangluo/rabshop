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

namespace app\common\service\statistics;

use app\common\model\order\Order;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialService;
use app\common\service\finance\FinanceService;
use app\common\service\merchant\MerchantService;
use app\common\service\order\OrderService;
use app\common\service\user\UserService;
use DateInterval;
use DateTime;

/**
 * 总平台首页统计聚合服务。
 */
class AdminDashboardService extends BaseService
{
    protected function setModel(): string
    {
        return Order::class;
    }

    /**
     * 获取总平台首页统计概览。
     * @param array $params 查询参数，支持 range/start_time/end_time
     * @return array
     */
    public function overview(array $params = []): array
    {
        $range = $this->resolveRange($params);
        $paidOrderQuery = $this->paidOrderQuery($range['start_time'], $range['end_time']);

        return [
            'range' => $range,
            'cards' => $this->buildCards($range, $paidOrderQuery),
            'trend' => $this->buildTrend($range),
            'distribution' => $this->buildDistribution($paidOrderQuery),
            'rankings' => $this->buildRankings(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 获取首页实时预警摘要。
     * @return array
     */
    public function pending(): array
    {
        return [
            'pending' => $this->buildPending(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 构建核心指标卡片。
     * @param array $range 时间范围
     * @param mixed $paidOrderQuery 已支付订单查询对象
     * @return array
     */
    protected function buildCards(array $range, $paidOrderQuery): array
    {
        $orderService = app()->make(OrderService::class);
        $merchantService = app()->make(MerchantService::class);
        $userService = app()->make(UserService::class);
        $articleService = app()->make(ArticleService::class);
        $specialService = app()->make(SpecialService::class);

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        $totalRevenue = (float)$orderService->getModel()
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED])
            ->sum('pay_amount');
        $todayRevenue = (float)$this->paidOrderQuery($todayStart, $todayEnd)->sum('pay_amount');
        $monthOrderCount = (int)$this->paidOrderQuery($monthStart, $monthEnd)->count();
        $rangeRevenue = (float)(clone $paidOrderQuery)->sum('pay_amount');
        $rangeOrderCount = (int)(clone $paidOrderQuery)->count();
        $previousRevenue = (float)$this->paidOrderQuery($range['previous_start_time'], $range['previous_end_time'])->sum('pay_amount');
        $previousOrderCount = (int)$this->paidOrderQuery($range['previous_start_time'], $range['previous_end_time'])->count();
        $commission = (float)app()->make(FinanceService::class)->getModel()
            ->where('type', 'commission')
            ->where('change_type', 'in')
            ->sum('amount');
        $merchantCount = (int)$merchantService->getModel()->whereNull('delete_time')->count();
        $userCount = (int)$userService->getModel()->whereNull('delete_time')->count();
        $contentCount = (int)$articleService->getModel()->whereNull('delete_time')->count()
            + (int)$specialService->getModel()->whereNull('delete_time')->count();

        return [
            $this->card('total_revenue', '平台总成交额', $totalRevenue, 'currency', '累计已支付订单实付金额', $this->calcPercent($rangeRevenue, $previousRevenue), 'el-icon-wallet', 'primary'),
            $this->card('today_revenue', '今日交易总额', $todayRevenue, 'currency', '今日已支付订单实付金额', null, 'el-icon-data-line', 'primary'),
            $this->card('month_orders', '本月订单总数', $monthOrderCount, 'number', '本月已支付订单数量', $this->calcPercent($rangeOrderCount, $previousOrderCount), 'el-icon-shopping-bag-1', 'success'),
            $this->card('platform_commission', '平台总佣金', $commission, 'currency', '财务流水中平台抽佣收入', null, 'el-icon-coin', 'warning'),
            $this->card('merchant_count', '总商户数量', $merchantCount, 'number', '平台入驻商户总量', null, 'el-icon-office-building', 'info'),
            $this->card('user_count', '总用户数量', $userCount, 'number', '平台注册用户总量', null, 'el-icon-user', 'info'),
            $this->card('content_count', '内容专题数量', $contentCount, 'number', '图文内容与专题合计', null, 'el-icon-document', 'info'),
        ];
    }

    /**
     * 构建交易趋势。
     * @param array $range 时间范围
     * @return array
     */
    protected function buildTrend(array $range): array
    {
        $rows = $this->paidOrderQuery($range['start_time'], $range['end_time'])
            ->field("DATE_FORMAT(create_time, '%Y-%m-%d') as stat_date, SUM(pay_amount) as amount, COUNT(*) as order_count")
            ->group('stat_date')
            ->select()
            ->toArray();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['stat_date']] = [
                'amount' => round((float)($row['amount'] ?? 0), 2),
                'order_count' => (int)($row['order_count'] ?? 0),
            ];
        }

        $xAxis = $this->dateAxis($range['start_date'], $range['end_date']);
        $amountData = [];
        $countData = [];
        foreach ($xAxis as $date) {
            $amountData[] = $map[$date]['amount'] ?? 0;
            $countData[] = $map[$date]['order_count'] ?? 0;
        }

        return [
            'xAxis' => $xAxis,
            'series' => [
                ['name' => '成交额', 'type' => 'line', 'data' => $amountData],
                ['name' => '订单数', 'type' => 'bar', 'data' => $countData],
            ],
        ];
    }

    /**
     * 构建业务分布。
     * @param mixed $paidOrderQuery 已支付订单查询对象
     * @return array
     */
    protected function buildDistribution($paidOrderQuery): array
    {
        $rows = (clone $paidOrderQuery)
            ->field('order_type, COUNT(*) as total, SUM(pay_amount) as amount')
            ->group('order_type')
            ->select()
            ->toArray();
        $labels = [
            Order::ORDER_TYPE_CONTENT => '内容付费',
            Order::ORDER_TYPE_GOODS => '商品订单',
            Order::ORDER_TYPE_SPECIAL => '专题订单',
        ];
        $total = max(1, array_sum(array_map(static fn($item) => (int)($item['total'] ?? 0), $rows)));
        $result = [];
        foreach ($rows as $row) {
            $value = (int)($row['total'] ?? 0);
            $type = (int)($row['order_type'] ?? 0);
            $result[] = [
                'name' => $labels[$type] ?? '其他订单',
                'value' => $value,
                'amount' => round((float)($row['amount'] ?? 0), 2),
                'percent' => round($value / $total * 100, 1),
            ];
        }
        return $result;
    }

    /**
     * 构建排行列表。
     * @return array
     */
    protected function buildRankings(): array
    {
        $merchantList = app()->make(MerchantService::class)->getModel()
            ->whereNull('delete_time')
            ->field('id, mer_name as name, total_sales as value, total_orders as extra')
            ->order('total_sales', 'desc')
            ->limit(5)
            ->select()
            ->toArray();
        $contentList = app()->make(ArticleService::class)->getModel()
            ->whereNull('delete_time')
            ->field('id, title as name, view_count as value, like_count as extra')
            ->order('view_count', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        return [
            ['key' => 'merchant', 'title' => '商户销售排行', 'unit' => '元', 'list' => $merchantList],
            ['key' => 'content', 'title' => '内容热度排行', 'unit' => '浏览', 'list' => $contentList],
        ];
    }

    /**
     * 构建待处理摘要。
     * @return array
     */
    protected function buildPending(): array
    {
        $merchantPending = (int)app()->make(MerchantService::class)->getModel()
            ->whereNull('delete_time')
            ->where('audit_status', 0)
            ->count();
        $articlePendingTabs = app()->make(ArticleService::class)->getPendingAuditTabCounts();
        $articlePending = (int)($articlePendingTabs['merchant'] ?? 0);
        $refundPending = (int)app()->make(OrderService::class)->getModel()
            ->where(function ($query) {
                $query->where('refund_status', 1)
                    ->whereOr('status', Order::STATUS_REFUNDING);
            })
            ->count();

        return [
            ['key' => 'merchant_audit', 'label' => '商户待审核', 'value' => $merchantPending, 'type' => 'warning'],
            ['key' => 'content_audit', 'label' => '内容待审核', 'value' => $articlePending, 'type' => 'warning'],
            ['key' => 'refund_pending', 'label' => '退款待处理', 'value' => $refundPending, 'type' => 'danger'],
        ];
    }

    /**
     * 已支付订单查询。
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return mixed
     */
    protected function paidOrderQuery(string $startTime = '', string $endTime = '')
    {
        $query = app()->make(OrderService::class)->getModel()
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED]);
        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime('create_time', $startTime, $endTime);
        }
        return $query;
    }

    /**
     * 生成卡片结构。
     */
    protected function card(string $key, string $title, float|int $value, string $format, string $description, ?float $trend, string $icon, string $type): array
    {
        return compact('key', 'title', 'value', 'format', 'description', 'trend', 'icon', 'type');
    }

    /**
     * 计算环比百分比。
     */
    protected function calcPercent(float|int $current, float|int $previous): ?float
    {
        if ((float)$previous <= 0) {
            return null;
        }
        return round(((float)$current - (float)$previous) / (float)$previous * 100, 1);
    }

    /**
     * 解析统计时间范围。
     * @param array $params 查询参数
     * @return array
     */
    protected function resolveRange(array $params): array
    {
        $range = (string)($params['range'] ?? '30day');
        $end = new DateTime(date('Y-m-d'));
        $start = clone $end;
        $days = match ($range) {
            '7day' => 7,
            'month' => (int)$end->format('j'),
            'year' => (int)$end->format('z') + 1,
            default => 30,
        };
        $start->modify('-' . max(0, $days - 1) . ' day');

        $customStart = trim((string)($params['start_time'] ?? ''));
        $customEnd = trim((string)($params['end_time'] ?? ''));
        if ($customStart !== '' && $customEnd !== '') {
            $start = new DateTime(substr($customStart, 0, 10));
            $end = new DateTime(substr($customEnd, 0, 10));
            $days = max(1, $end->diff($start)->days + 1);
            $range = 'custom';
        }

        $previousEnd = (clone $start)->modify('-1 day');
        $previousStart = (clone $previousEnd)->modify('-' . max(0, $days - 1) . ' day');

        return [
            'range' => $range,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'start_time' => $start->format('Y-m-d 00:00:00'),
            'end_time' => $end->format('Y-m-d 23:59:59'),
            'previous_start_time' => $previousStart->format('Y-m-d 00:00:00'),
            'previous_end_time' => $previousEnd->format('Y-m-d 23:59:59'),
        ];
    }

    /**
     * 生成日期轴。
     */
    protected function dateAxis(string $startDate, string $endDate): array
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $result = [];
        while ($start <= $end) {
            $result[] = $start->format('Y-m-d');
            $start->add(new DateInterval('P1D'));
        }
        return $result;
    }
}
