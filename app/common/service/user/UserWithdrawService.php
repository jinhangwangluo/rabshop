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

namespace app\common\service\user;

use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\user\User;
use app\common\model\user\UserWithdraw;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\message\MessagePushDefinitionService;
use app\common\service\message\MessagePushEventService;

/**
 * 用户提现服务
 */
class UserWithdrawService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const SOURCE_COMMISSION = 'commission';

    protected function setModel(): string
    {
        return UserWithdraw::class;
    }

    /**
     * 获取佣金提现配置。
     *
     * @return array<string, mixed>
     */
    public function getCommissionWithdrawConfig(): array
    {
        $notesRaw = db_config(ConfigKeyEnum::COMMISSION_WITHDRAW_NOTES, '');
        $notes = $notesRaw
            ? array_values(array_filter(
                array_map('trim', explode("\n", (string) $notesRaw)),
                fn($line) => $line !== ''
            ))
            : [
                '单笔最低提现 10.00 元，最高提现 50,000.00 元。',
                '手续费按提现金额的 0.1% 收取，最低 0.1 元。',
            ];

        return [
            'enabled' => (bool) db_config(ConfigKeyEnum::COMMISSION_WITHDRAW_ENABLED, true),
            'fee_rate' => (float) db_config(ConfigKeyEnum::COMMISSION_WITHDRAW_FEE_RATE, 0.001),
            'min_fee' => (float) db_config(ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_FEE, 0.1),
            'min_amount' => (float) db_config(ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_AMOUNT, 10),
            'max_amount' => (float) db_config(ConfigKeyEnum::COMMISSION_WITHDRAW_MAX_AMOUNT, 50000),
            'arrival_time' => (string) db_config(ConfigKeyEnum::COMMISSION_WITHDRAW_ARRIVAL_TIME, '预计 2 小时内到账'),
            'notes' => $notes,
        ];
    }

    /**
     * 提交用户提现申请.
     *
     * @param int   $userId 用户ID
     * @param array $data   包含 amount 的请求数据，提现来源固定为佣金
     *
     * @return array 提现记录
     */
    public function submitWithdraw(int $userId, array $data): array
    {
        $source = self::SOURCE_COMMISSION;
        $amount = (float) ($data['amount'] ?? 0);
        $config = $this->getCommissionWithdrawConfig();
        $feeRate = (float) $config['fee_rate'];
        $minFee = (float) $config['min_fee'];
        $minAmount = (float) $config['min_amount'];
        $maxAmount = (float) $config['max_amount'];

        if (empty($config['enabled'])) {
            throw new AdminResponseException('佣金提现暂未开放');
        }

        if ($amount < $minAmount) {
            throw new AdminResponseException("单笔最低提现 {$minAmount} 元");
        }
        if ($amount > $maxAmount) {
            throw new AdminResponseException("单笔最高提现 {$maxAmount} 元");
        }

        // 校验用户资产
        /** @var UserAssetService $assetService */
        $assetService = app()->make(UserAssetService::class);
        $asset = $assetService->getInfo($userId);
        $available = (float) ($asset->commission ?? 0);

        if ($amount > $available) {
            throw new AdminResponseException('提现金额不能超过可提现金额');
        }

        // 计算手续费与到账金额
        $fee = max(round($amount * $feeRate, 2), $minFee);
        $actualAmount = round($amount - $fee, 2);
        if ($actualAmount <= 0) {
            throw new AdminResponseException('到账金额需大于 0 元');
        }

        // 校验银行卡
        $user = app()->make(UserService::class)->get($userId);
        if (!$user instanceof User) {
            throw new AdminResponseException('用户不存在');
        }
        $bankName   = trim((string) ($user->bank_name ?? ''));
        $bankCardNo = preg_replace('/\s+/', '', (string) ($user->bank_card_no ?? ''));
        $holderName = trim((string) ($user->bank_holder_name ?? ''));

        if ($bankName === '' || $bankCardNo === '') {
            throw new AdminResponseException('请先添加提现银行卡');
        }

        return $this->transaction(function () use ($userId, $source, $amount, $fee, $actualAmount, $bankName, $bankCardNo, $holderName, $assetService) {
            // 扣减用户资产
            $assetService->changeAsset(
                $userId,
                'commission',
                $amount,
                'out',
                '提现申请冻结'
            );

            // 创建提现记录
            $withdraw = $this->create([
                'user_id'          => $userId,
                'source'            => $source,
                'amount'            => $amount,
                'fee'               => $fee,
                'actual_amount'     => $actualAmount,
                'bank_name'         => $bankName,
                'bank_card_no'      => $bankCardNo,
                'bank_holder_name'  => $holderName,
                'status'            => 0,
            ]);

            $data = $withdraw->toArray();
            app()->make(BackendMessageEventService::class)->userWithdrawApplied($data);

            return $data;
        });
    }

    /**
     * 获取后台用户提现申请列表。
     *
     * @param array $params 搜索参数。
     * @return array 列表、分页与统计信息。
     */
    public function getAdminList(array $params = []): array
    {
        [$page, $limit] = $this->getPageValue();
        $page = max(1, $page);
        $limit = max(1, $limit);

        $query = $this->buildAdminQuery($params, true);
        $total = (clone $query)->count();
        $list = $query
            ->field([
                'w.id',
                'w.user_id as uid',
                'w.source',
                'w.amount',
                'w.fee',
                'w.actual_amount',
                'w.bank_name',
                'w.bank_card_no',
                'w.bank_holder_name',
                'w.status',
                'w.remark',
                'w.audit_time',
                'w.created_at as create_time',
                'w.updated_at as update_time',
                'u.nickname',
                'u.avatar',
                'u.phone',
            ])
            ->order('w.created_at', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['source_text'] = $this->getSourceText((string)($item['source'] ?? ''));
            $item['status_text'] = $this->getWithdrawStatusText((int)($item['status'] ?? 0));
            $item['bank_tail_number'] = substr((string)($item['bank_card_no'] ?? ''), -4);
        }
        unset($item);

        return $this->listData($list, $total, [
            'stats' => $this->getAdminStats($params),
        ], $page, $limit)->toArray();
    }

    /**
     * 审核用户提现申请。
     *
     * @param int $id 提现记录 ID。
     * @param int $status 审核状态：1=通过，2=拒绝。
     * @param string $remark 审核备注。
     * @return bool
     */
    public function audit(int $id, int $status, string $remark = ''): bool
    {
        if (!in_array($status, [1, 2], true)) {
            throw new AdminResponseException('审核状态错误');
        }

        return $this->transaction(function () use ($id, $status, $remark) {
            $withdraw = $this->get($id);
            if (!$withdraw instanceof UserWithdraw) {
                throw new AdminResponseException('提现申请不存在');
            }

            if ((int)$withdraw->status !== 0) {
                throw new AdminResponseException('该提现申请已处理');
            }

            $this->update($id, [
                'status' => $status,
                'remark' => $remark,
                'audit_time' => date('Y-m-d H:i:s'),
            ]);

            if ($status === 2) {
                app()->make(UserAssetService::class)->changeAsset(
                    (int)$withdraw->user_id,
                    UserAssetService::TYPE_COMMISSION,
                    (float)$withdraw->amount,
                    'in',
                    '提现审核拒绝退回',
                    [
                        'source_type' => 'withdraw_refund',
                        'source_desc' => '提现申请(ID:' . $id . ')审核拒绝，佣金退回',
                    ]
                );
            }

            $handledWithdraw = $this->get($id);
            if ($handledWithdraw instanceof UserWithdraw) {
                $this->notifyAuditResult($handledWithdraw->toArray(), $status, $remark);
            }

            return true;
        });
    }

    /**
     * 触发用户提现审核结果推送。
     */
    public function notifyAuditResult(array $withdraw, int $status, string $remark = ''): void
    {
        $eventKey = $status === 1
            ? MessagePushDefinitionService::EVENT_WITHDRAW_APPROVED
            : MessagePushDefinitionService::EVENT_WITHDRAW_FAILED;
        $auditStatusText = $status === 1 ? '已通过' : '已拒绝';

        app()->make(MessagePushEventService::class)->dispatch($eventKey, array_merge($withdraw, [
            'withdraw_id' => (int)($withdraw['id'] ?? 0),
            'user_id' => (int)($withdraw['user_id'] ?? 0),
            'audit_status' => $status,
            'audit_status_text' => $auditStatusText,
            'audit_remark' => $remark !== '' ? $remark : (string)($withdraw['remark'] ?? ''),
            'audit_time' => date('Y-m-d H:i:s'),
            'relation_type' => 'user_withdraw',
            'relation_id' => (int)($withdraw['id'] ?? 0),
        ]));
    }

    private function buildAdminQuery(array $params = [], bool $withStatus = true)
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $source = trim((string)($params['source'] ?? ''));
        $status = $params['status'] ?? '';
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));

        return $this->getModel()
            ->alias('w')
            ->leftJoin('user u', 'u.uid = w.user_id')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $like = '%' . $keyword . '%';
                    $query->whereLike('u.nickname', $like)
                        ->whereOr('u.phone', 'like', $like)
                        ->whereOr('w.bank_name', 'like', $like)
                        ->whereOr('w.bank_card_no', 'like', $like);

                    if (ctype_digit($keyword)) {
                        $query->whereOr('w.id', (int)$keyword)
                            ->whereOr('w.user_id', (int)$keyword);
                    }
                });
            })
            ->when($source !== '', fn($query) => $query->where('w.source', $source))
            ->when($withStatus && $status !== '' && $status !== null, fn($query) => $query->where('w.status', (int)$status))
            ->when($startTime !== '', fn($query) => $query->where('w.created_at', '>=', $startTime . ' 00:00:00'))
            ->when($endTime !== '', fn($query) => $query->where('w.created_at', '<=', $endTime . ' 23:59:59'));
    }

    private function getAdminStats(array $params = []): array
    {
        $baseQuery = $this->buildAdminQuery($params, false);
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        return [
            'pending' => (clone $baseQuery)->where('w.status', 0)->count(),
            'passed_amount' => $this->formatAmount((clone $baseQuery)->where('w.status', 1)->sum('w.actual_amount')),
            'today' => (clone $baseQuery)
                ->where('w.created_at', '>=', $today . ' 00:00:00')
                ->where('w.created_at', '<=', $today . ' 23:59:59')
                ->count(),
            'month_amount' => $this->formatAmount((clone $baseQuery)
                ->where('w.status', 1)
                ->where('w.audit_time', '>=', $monthStart)
                ->where('w.audit_time', '<=', $monthEnd)
                ->sum('w.actual_amount')),
        ];
    }

    private function getSourceText(string $source): string
    {
        return [
            self::SOURCE_COMMISSION => '佣金',
            'balance' => '余额',
        ][$source] ?? $source;
    }

    private function getWithdrawStatusText(int $status): string
    {
        return [
            0 => '待审核',
            1 => '已通过',
            2 => '已拒绝',
        ][$status] ?? '未知';
    }

    private function formatAmount(float|int|string $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }
}
