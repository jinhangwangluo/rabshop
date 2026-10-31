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

namespace app\common\service\finance;

use app\common\exception\AdminResponseException;
use app\common\model\finance\FinanceRecord;
use app\common\model\merchant\MerchantWithdraw;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 财务管理服务
 */
class FinanceService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const FUND_STATUS_FROZEN = 'frozen';
    public const FUND_STATUS_AVAILABLE = 'available';
    public const FUND_STATUS_REVERSED = 'reversed';
    public const TYPE_ORDER_SUMMARY = 'order_summary';
    public const TYPE_REFUND_SUMMARY = 'refund_summary';
    public const TYPE_COUPON_SUBSIDY = 'coupon_subsidy';
    public const TYPE_LIVE_GIFT_SUMMARY = 'live_gift_summary';
    public const TYPE_LIVE_GIFT_PLATFORM = 'live_gift_platform';
    public const TYPE_LIVE_GIFT_MERCHANT = 'live_gift_merchant';
    public const TYPE_LIVE_GIFT_ANCHOR = 'live_gift_anchor';
    public const TYPE_WITHDRAW = 'withdraw';

    /**
     * 设置对应的模型
     */
    protected function setModel(): string
    {
        return FinanceRecord::class;
    }

    /**
     * 获取财务记录详情
     * @param int $id
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getInfo(int $id): ?array
    {
        $record = $this->get($id);
        return $record ? $record->toArray() : null;
    }

    /**
     * 获取商户财务记录列表
     * @param int $merchantId
     * @param array $params
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMerchantFinanceList(int $merchantId, array $params = []): array
    {
        $where = [
            'merchant_id' => $merchantId,
            'type' => $params['type'] ?? '',
            'change_type' => $params['change_type'] ?? '',
            'fund_status' => $params['fund_status'] ?? '',
        ];
        [$page, $limit] = $this->getPagesValue();

        $totalQuery = $this->getModel()->searchWhere($where);
        $this->applyCreateTimeRange($totalQuery, $params);
        $total = $totalQuery->count();

        $listQuery = $this->getModel()->searchWhere($where);
        $this->applyCreateTimeRange($listQuery, $params);
        $list = $listQuery
            ->order('create_time desc')
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->page($page, $limit);
            })
            ->when(!$page && $limit, function ($query) use ($limit) {
                $query->limit($limit);
            })
            ->select()
            ->toArray();

        $list = array_map(fn($record) => $this->formatFinanceRecord($record), $list);

        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 统计财务数据
     * @param array $where
     * @return array
     */
    public function statistics(array $where = []): array
    {
        $model = $this->getModel();
        // 聚合流水只是主单/退款单的父节点，真实收入支出已经体现在子流水里，统计时必须排除避免重复计算。
        $excludedTypes = [self::TYPE_ORDER_SUMMARY, self::TYPE_REFUND_SUMMARY, self::TYPE_LIVE_GIFT_SUMMARY];

        // 总收入
        $income = $model->where($where)->whereNotIn('type', $excludedTypes)->where('change_type', 'in')->sum('amount');

        // 总支出
        $expense = $model->where($where)->whereNotIn('type', $excludedTypes)->where('change_type', 'out')->sum('amount');

        // 净利润
        $profit = $this->moneySub($income, $expense);

        return [
            'income' => $this->normalizeMoney($income),
            'expense' => $this->normalizeMoney($expense),
            'profit' => $profit,
        ];
    }

    /**
     * 获取商户财务统计
     * @param int $merchantId
     * @return array
     */
    public function getMerchantFinanceStatistics(int $merchantId): array
    {
        $model = $this->getModel();

        // 累计收入
        $totalIncome = $model->where('merchant_id', $merchantId)->where('change_type', 'in')->sum('amount');
        // 累计支出
        $totalExpense = $model->where('merchant_id', $merchantId)->where('change_type', 'out')->sum('amount');
        // 可提现余额只统计已释放资金
        $availableIncome = $model->where('merchant_id', $merchantId)
            ->where('fund_status', self::FUND_STATUS_AVAILABLE)
            ->where('change_type', 'in')
            ->sum('amount');
        $availableExpense = $model->where('merchant_id', $merchantId)
            ->where('fund_status', self::FUND_STATUS_AVAILABLE)
            ->where('change_type', 'out')
            ->sum('amount');
        $balance = $this->moneySub($availableIncome, $availableExpense);
        // 冻结金额用于后台/商户端展示，不参与提现校验
        $frozenIncome = $model->where('merchant_id', $merchantId)
            ->where('fund_status', self::FUND_STATUS_FROZEN)
            ->where('change_type', 'in')
            ->sum('amount');
        $frozenExpense = $model->where('merchant_id', $merchantId)
            ->where('fund_status', self::FUND_STATUS_FROZEN)
            ->where('change_type', 'out')
            ->sum('amount');
        $frozenAmount = $this->moneyMax(0, $this->moneySub($frozenIncome, $frozenExpense));
        // 今日收入
        $todayIncome = $model->where('merchant_id', $merchantId)->where('change_type', 'in')->whereDay('create_time')->sum('amount');
        // 今日支出
        $todayExpense = $model->where('merchant_id', $merchantId)->where('change_type', 'out')->whereDay('create_time')->sum('amount');

        return [
            'total_income' => $this->normalizeMoney($totalIncome),
            'total_expense' => $this->normalizeMoney($totalExpense),
            'balance' => $balance,
            'available_income' => $this->normalizeMoney($availableIncome),
            'available_expense' => $this->normalizeMoney($availableExpense),
            'frozen_amount' => $frozenAmount,
            'today_income' => $this->normalizeMoney($todayIncome),
            'today_expense' => $this->normalizeMoney($todayExpense),
        ];
    }

    /**
     * 按类型统计财务数据
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function statisticsByType(array $where = []): array
    {
        // 按类型统计同样只看明细流水；order_summary/refund_summary 用于串联 pid，不参与经营数据汇总。
        $records = $this->getModel()
            ->where($where)
            ->whereNotIn('type', [self::TYPE_ORDER_SUMMARY, self::TYPE_REFUND_SUMMARY, self::TYPE_LIVE_GIFT_SUMMARY])
            ->field('type, change_type, sum(amount) as amount')
            ->group('type, change_type')
            ->select();

        $data = [];
        foreach ($records as $record) {
            $type = $record['type'];
            $changeType = $record['change_type'];
            if (!isset($data[$type])) {
                $data[$type] = [
                    'income' => 0,
                    'expense' => 0,
                ];
            }
            if ($changeType == 'in') {
                $data[$type]['income'] = $record['amount'];
            } else {
                $data[$type]['expense'] = $record['amount'];
            }
        }

        return $data;
    }

    /**
     * 获取商户提现记录列表
     * @param int $merchantId
     * @param array $params
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMerchantWithdrawList(int $merchantId, array $params = []): array
    {
        $where = [
            'merchant_id' => $merchantId,
            'status' => $params['status'] ?? '',
            'withdraw_type' => $params['withdraw_type'] ?? '',
        ];
        [$page, $limit] = $this->getPagesValue();

        $model = app()->make(MerchantWithdraw::class);
        $totalQuery = $model->searchWhere($where);
        $this->applyCreateTimeRange($totalQuery, $params);
        $total = $totalQuery->count();

        $listQuery = $model->searchWhere($where);
        $this->applyCreateTimeRange($listQuery, $params);
        $list = $listQuery
            ->order('create_time desc')
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->page($page, $limit);
            })
            ->when(!$page && $limit, function ($query) use ($limit) {
                $query->limit($limit);
            })
            ->select()
            ->toArray();

        $list = array_map(fn($record) => $this->formatWithdrawRecord($record), $list);

        return $this->listData($list, $total, [], $page, $limit)->toArray();
    }

    /**
     * 应用财务流水创建时间范围筛选。
     */
    private function applyCreateTimeRange(mixed $query, array $params): void
    {
        $startTime = trim((string)($params['start_time'] ?? ''));
        if ($startTime !== '') {
            $query->where('create_time', '>=', $this->normalizeDateBoundary($startTime, ' 00:00:00'));
        }

        $endTime = trim((string)($params['end_time'] ?? ''));
        if ($endTime !== '') {
            $query->where('create_time', '<=', $this->normalizeDateBoundary($endTime, ' 23:59:59'));
        }
    }

    /**
     * 日期格式参数补齐日边界，完整时间保持原样。
     */
    private function normalizeDateBoundary(string $time, string $suffix): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $time) ? $time . $suffix : $time;
    }

    /**
     * 商户申请提现
     * @param int $merchantId
     * @param array $data
     * @return array
     * @throws AdminResponseException
     */
    public function applyWithdraw(int $merchantId, array $data): array
    {
        $withdrawData = $this->buildWithdrawData($merchantId, $data);

        return $this->transaction(function () use ($withdrawData) {
            $balance = $this->getMerchantAvailableBalanceForUpdate((int)$withdrawData['merchant_id']);
            if ($this->moneyCompare($withdrawData['amount'], $balance) > 0) {
                throw new AdminResponseException('提现金额不能超过可提现余额');
            }

            $withdraw = app()->make(MerchantWithdraw::class)->create($withdrawData);

            $this->createRecord([
                'merchant_id' => (int)$withdrawData['merchant_id'],
                'type' => self::TYPE_WITHDRAW,
                'change_type' => 'out',
                'fund_status' => self::FUND_STATUS_AVAILABLE,
                'amount' => $withdrawData['amount'],
                'remark' => '提现申请冻结',
            ]);

            $result = $this->formatWithdrawRecord($withdraw->toArray());
            app()->make(BackendMessageEventService::class)->merchantWithdrawApplied($result);

            return $result;
        });
    }

    /**
     * 创建财务记录
     * @param array $data
     * @return array
     */
    public function createRecord(array $data): array
    {
        if (!isset($data['pid'])) {
            // pid=0 表示顶层流水；合单支付/退款的明细流水会传入父流水 ID，形成可追溯的主从关系。
            $data['pid'] = 0;
        }

        if (empty($data['fund_status'])) {
            $data['fund_status'] = self::FUND_STATUS_AVAILABLE;
        }

        $record = $this->create($data);
        return $record->toArray();
    }

    /**
     * 金额标准化。
     */
    protected function normalizeMoney(float|string $amount): float
    {
        return (float)bcadd((string)$amount, '0', 2);
    }

    /**
     * 高精度金额相减。
     */
    protected function moneySub(float|string $left, float|string $right): float
    {
        return (float)bcsub((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额比较。
     */
    protected function moneyCompare(float|string $left, float|string $right): int
    {
        return bccomp((string)$left, (string)$right, 2);
    }

    /**
     * 高精度金额最大值。
     */
    protected function moneyMax(float|string $left, float|string $right): float
    {
        return $this->moneyCompare($left, $right) >= 0
            ? $this->normalizeMoney($left)
            : $this->normalizeMoney($right);
    }

    /**
     * 组装提现申请数据并按收款方式校验。
     *
     * @throws AdminResponseException
     */
    private function buildWithdrawData(int $merchantId, array $data): array
    {
        $amount = $this->normalizeMoney($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new AdminResponseException('提现金额必须大于0');
        }

        $withdrawType = trim((string)($data['withdraw_type'] ?? MerchantWithdraw::TYPE_BANK));
        if (!in_array($withdrawType, [
            MerchantWithdraw::TYPE_BANK,
            MerchantWithdraw::TYPE_WECHAT,
            MerchantWithdraw::TYPE_ALIPAY,
        ], true)) {
            throw new AdminResponseException('提现方式错误');
        }

        $bankName = trim((string)($data['bank_name'] ?? ''));
        $bankAccount = (string)preg_replace('/\s+/', '', trim((string)($data['bank_account'] ?? '')));
        $accountName = trim((string)($data['account_name'] ?? ''));
        $paymentQrcode = trim((string)($data['payment_qrcode'] ?? ''));

        if ($accountName === '') {
            throw new AdminResponseException('账户姓名不能为空');
        }

        if ($withdrawType === MerchantWithdraw::TYPE_BANK) {
            if ($bankName === '') {
                throw new AdminResponseException('银行名称不能为空');
            }
            if ($bankAccount === '') {
                throw new AdminResponseException('银行卡号不能为空');
            }
            $paymentQrcode = '';
        } else {
            if ($paymentQrcode === '') {
                throw new AdminResponseException('请上传收款码');
            }
            $bankName = '';
            $bankAccount = '';
        }

        return [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'withdraw_type' => $withdrawType,
            'bank_name' => $bankName,
            'bank_account' => $bankAccount,
            'account_name' => $accountName,
            'payment_qrcode' => $paymentQrcode,
            'status' => MerchantWithdraw::STATUS_PENDING,
        ];
    }

    /**
     * 锁定当前商户可提现流水并计算余额，降低并发重复提现风险。
     */
    private function getMerchantAvailableBalanceForUpdate(int $merchantId): float
    {
        $records = $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('fund_status', self::FUND_STATUS_AVAILABLE)
            ->field('change_type, amount')
            ->lock(true)
            ->select()
            ->toArray();

        $income = '0.00';
        $expense = '0.00';
        foreach ($records as $record) {
            if (($record['change_type'] ?? '') === 'in') {
                $income = bcadd($income, (string)($record['amount'] ?? 0), 2);
            } elseif (($record['change_type'] ?? '') === 'out') {
                $expense = bcadd($expense, (string)($record['amount'] ?? 0), 2);
            }
        }

        return $this->moneySub($income, $expense);
    }

    /**
     * 格式化财务流水展示字段。
     */
    private function formatFinanceRecord(array $record): array
    {
        $record['amount'] = $this->normalizeMoney($record['amount'] ?? 0);
        $record['type_text'] = $this->getFinanceTypeText((string)($record['type'] ?? ''));
        $record['change_type_text'] = $this->getChangeTypeText((string)($record['change_type'] ?? ''));
        $record['fund_status_text'] = $this->getFundStatusText((string)($record['fund_status'] ?? self::FUND_STATUS_AVAILABLE));

        return $record;
    }

    /**
     * 格式化提现记录展示字段。
     */
    public function formatWithdrawRecord(array $record): array
    {
        $withdrawType = (string)($record['withdraw_type'] ?? MerchantWithdraw::TYPE_BANK);
        $record['withdraw_type'] = $withdrawType ?: MerchantWithdraw::TYPE_BANK;
        $record['amount'] = $this->normalizeMoney($record['amount'] ?? 0);
        $record['withdraw_type_text'] = MerchantWithdraw::getWithdrawTypeText($record['withdraw_type']);
        $record['status_text'] = MerchantWithdraw::getStatusText((int)($record['status'] ?? MerchantWithdraw::STATUS_PENDING));
        $record['account_summary'] = $this->buildWithdrawAccountSummary($record);

        return $record;
    }

    /**
     * 提现收款信息摘要。
     */
    private function buildWithdrawAccountSummary(array $record): string
    {
        $withdrawType = (string)($record['withdraw_type'] ?? MerchantWithdraw::TYPE_BANK);
        $accountName = trim((string)($record['account_name'] ?? ''));

        if ($withdrawType === MerchantWithdraw::TYPE_BANK) {
            $bankName = trim((string)($record['bank_name'] ?? ''));
            $bankAccount = trim((string)($record['bank_account'] ?? ''));
            return trim($bankName . ' ' . $accountName . ' ' . $bankAccount);
        }

        return trim(MerchantWithdraw::getWithdrawTypeText($withdrawType) . ' ' . $accountName);
    }

    private function getFinanceTypeText(string $type): string
    {
        return [
            'order' => '订单收入',
            self::TYPE_ORDER_SUMMARY => '订单聚合流水',
            self::TYPE_WITHDRAW => '提现',
            'recharge' => '充值',
            'refund' => '退款',
            self::TYPE_REFUND_SUMMARY => '退款聚合流水',
            'commission' => '平台抽佣',
            self::TYPE_COUPON_SUBSIDY => '平台券补贴',
            self::TYPE_LIVE_GIFT_SUMMARY => '直播礼物聚合流水',
            self::TYPE_LIVE_GIFT_PLATFORM => '直播礼物平台抽成',
            self::TYPE_LIVE_GIFT_MERCHANT => '直播礼物商户抽成',
            self::TYPE_LIVE_GIFT_ANCHOR => '直播礼物主播所得',
        ][$type] ?? '其他';
    }

    private function getChangeTypeText(string $changeType): string
    {
        return [
            'in' => '收入',
            'out' => '支出',
        ][$changeType] ?? '其他';
    }

    private function getFundStatusText(string $fundStatus): string
    {
        return [
            self::FUND_STATUS_FROZEN => '冻结中',
            self::FUND_STATUS_AVAILABLE => '可提现',
            self::FUND_STATUS_REVERSED => '已冲正',
        ][$fundStatus] ?? '可提现';
    }

    /**
     * 释放到期的商户冻结资金。
     *
     * @param int $limit 单次释放上限。
     * @return array<string, int>
     */
    public function releaseDueFrozenMerchantFunds(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $now = date('Y-m-d H:i:s');
        $recordIds = $this->getModel()
            ->where('merchant_id', '>', 0)
            ->where('fund_status', self::FUND_STATUS_FROZEN)
            ->whereNotNull('release_time')
            ->where('release_time', '<=', $now)
            ->order('id asc')
            ->limit($limit)
            ->column('id');

        $result = [
            'scanned' => count($recordIds),
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($recordIds as $recordId) {
            try {
                $released = $this->transaction(function () use ($recordId, $now): bool {
                    $record = $this->getModel()
                        ->where('id', (int)$recordId)
                        ->lock(true)
                        ->find();
                    if (!$record || (string)($record['fund_status'] ?? '') !== self::FUND_STATUS_FROZEN) {
                        return false;
                    }

                    $releaseTime = (string)($record['release_time'] ?? '');
                    if ($releaseTime === '' || $releaseTime > $now) {
                        return false;
                    }

                    $record->save([
                        'fund_status' => self::FUND_STATUS_AVAILABLE,
                    ]);

                    return true;
                });

                if ($released) {
                    $result['success']++;
                } else {
                    $result['skipped']++;
                }
            } catch (\Throwable $throwable) {
                $result['failed']++;
                trace('release merchant frozen finance failed: ' . json_encode([
                    'finance_record_id' => (int)$recordId,
                    'message' => $throwable->getMessage(),
                ], JSON_UNESCAPED_UNICODE), 'error');
            }
        }

        return $result;
    }
}
