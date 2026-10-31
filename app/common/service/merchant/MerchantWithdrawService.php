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
use app\common\model\merchant\MerchantWithdraw;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\finance\FinanceService;
use app\common\service\message\MessagePushDefinitionService;
use app\common\service\message\MessagePushEventService;

/**
 * 商户提现申请服务
 */
class MerchantWithdrawService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置对应的模型
     */
    protected function setModel(): string
    {
        return MerchantWithdraw::class;
    }

    /**
     * 获取提现申请列表
     */
    public function getList(array $where = [], int $page = 1, int $limit = 20)
    {
        return $this->getDataList($where, ['*'], $page, $limit, 'create_time desc');
    }

    /**
     * 获取后台资源列表。
     */
    public function getResourceList(
        array $params = [],
        array|string $fields = [],
        array $with = [],
        string|array|null $sort = null
    ) {
        return $this->getAdminList($params);
    }

    /**
     * 获取后台提现申请列表。
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
                'w.merchant_id',
                'w.amount',
                'w.withdraw_type',
                'w.bank_name',
                'w.bank_account',
                'w.account_name',
                'w.payment_qrcode',
                'w.status',
                'w.remark',
                'w.audit_time',
                'w.create_time',
                'w.update_time',
                'm.mer_name as merchant_name',
                'm.mer_avatar as merchant_avatar',
                'm.contact_phone as merchant_phone',
            ])
            ->order('w.create_time', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $financeService = app()->make(FinanceService::class);
        foreach ($list as &$item) {
            $item = $financeService->formatWithdrawRecord($item);
            $item['merchant'] = [
                'id' => (int)($item['merchant_id'] ?? 0),
                'mer_name' => (string)($item['merchant_name'] ?? ''),
                'mer_avatar' => (string)($item['merchant_avatar'] ?? ''),
                'contact_phone' => (string)($item['merchant_phone'] ?? ''),
            ];
            $item['bank_tail_number'] = substr((string)($item['bank_account'] ?? ''), -4);
        }
        unset($item);

        return $this->listData($list, $total, [
            'stats' => $this->getAdminStats($params),
        ], $page, $limit)->toArray();
    }

    /**
     * 获取提现申请详情
     */
    public function getInfo(int $id)
    {
        return $this->get(['id' => $id]);
    }

    /**
     * 处理提现申请
     */
    public function handle(int $id, int $status, string $remark = ''): bool
    {
        if (!in_array($status, [MerchantWithdraw::STATUS_APPROVED, MerchantWithdraw::STATUS_REJECTED], true)) {
            throw new AdminResponseException('审核状态错误');
        }

        return $this->transaction(function () use ($id, $status, $remark) {
            $withdraw = $this->getModel()
                ->where('id', $id)
                ->lock(true)
                ->find();
            if (!$withdraw instanceof MerchantWithdraw) {
                throw new AdminResponseException('提现申请不存在');
            }

            if ((int)$withdraw->status !== MerchantWithdraw::STATUS_PENDING) {
                throw new AdminResponseException('该提现申请已处理');
            }

            $auditTime = date('Y-m-d H:i:s');
            $withdraw->save([
                'status' => $status,
                'remark' => $remark,
                'audit_time' => $auditTime,
            ]);

            if ($status === MerchantWithdraw::STATUS_REJECTED) {
                app()->make(FinanceService::class)->createRecord([
                    'merchant_id' => (int)$withdraw->merchant_id,
                    'type' => FinanceService::TYPE_WITHDRAW,
                    'change_type' => 'in',
                    'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
                    'amount' => (float)$withdraw->amount,
                    'remark' => '提现审核拒绝退回',
                ]);
            }

            $handled = $withdraw->toArray();
            $handled['audit_time'] = $auditTime;
            $this->notifyAuditResult($handled, $status, $remark);

            return true;
        });
    }

    /**
     * 触发商户提现审核结果推送。
     */
    protected function notifyAuditResult(array $withdraw, int $status, string $remark = ''): void
    {
        $eventKey = $status === 1
            ? MessagePushDefinitionService::EVENT_WITHDRAW_APPROVED
            : MessagePushDefinitionService::EVENT_WITHDRAW_FAILED;
        $auditStatusText = $status === 1 ? '已通过' : '已拒绝';

        app()->make(MessagePushEventService::class)->dispatch($eventKey, array_merge($withdraw, [
            'withdraw_id' => (int)($withdraw['id'] ?? 0),
            'merchant_id' => (int)($withdraw['merchant_id'] ?? 0),
            'actual_amount' => (float)($withdraw['amount'] ?? 0),
            'audit_status' => $status,
            'audit_status_text' => $auditStatusText,
            'audit_remark' => $remark !== '' ? $remark : (string)($withdraw['remark'] ?? ''),
            'audit_time' => date('Y-m-d H:i:s'),
            'relation_type' => 'merchant_withdraw',
            'relation_id' => (int)($withdraw['id'] ?? 0),
        ]));
    }

    private function buildAdminQuery(array $params = [], bool $withStatus = true)
    {
        $keyword = trim((string)($params['keyword'] ?? $params['mer_name'] ?? ''));
        $merchantId = trim((string)($params['merchant_id'] ?? ''));
        $withdrawType = trim((string)($params['withdraw_type'] ?? ''));
        $status = $params['status'] ?? '';
        $startTime = trim((string)($params['start_time'] ?? ''));
        $endTime = trim((string)($params['end_time'] ?? ''));

        return $this->getModel()
            ->alias('w')
            ->leftJoin('merchant m', 'm.id = w.merchant_id')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $like = '%' . $keyword . '%';
                    $query->whereLike('m.mer_name', $like)
                        ->whereOr('m.contact_phone', 'like', $like)
                        ->whereOr('w.account_name', 'like', $like)
                        ->whereOr('w.bank_name', 'like', $like)
                        ->whereOr('w.bank_account', 'like', $like);

                    if (ctype_digit($keyword)) {
                        $query->whereOr('w.id', (int)$keyword)
                            ->whereOr('w.merchant_id', (int)$keyword);
                    }
                });
            })
            ->when($merchantId !== '', fn($query) => $query->where('w.merchant_id', (int)$merchantId))
            ->when($withdrawType !== '', fn($query) => $query->where('w.withdraw_type', $withdrawType))
            ->when($withStatus && $status !== '' && $status !== null, fn($query) => $query->where('w.status', (int)$status))
            ->when($startTime !== '', fn($query) => $query->where('w.create_time', '>=', $startTime . ' 00:00:00'))
            ->when($endTime !== '', fn($query) => $query->where('w.create_time', '<=', $endTime . ' 23:59:59'));
    }

    private function getAdminStats(array $params = []): array
    {
        $baseQuery = $this->buildAdminQuery($params, false);
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        return [
            'pending' => (int)(clone $baseQuery)->where('w.status', MerchantWithdraw::STATUS_PENDING)->count(),
            'approved_amount' => $this->formatAmount((clone $baseQuery)
                ->where('w.status', MerchantWithdraw::STATUS_APPROVED)
                ->sum('w.amount')),
            'today' => (int)(clone $baseQuery)
                ->where('w.create_time', '>=', $today . ' 00:00:00')
                ->where('w.create_time', '<=', $today . ' 23:59:59')
                ->count(),
            'month_amount' => $this->formatAmount((clone $baseQuery)
                ->where('w.status', MerchantWithdraw::STATUS_APPROVED)
                ->where('w.audit_time', '>=', $monthStart)
                ->where('w.audit_time', '<=', $monthEnd)
                ->sum('w.amount')),
        ];
    }

    private function formatAmount(float|int|string $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }
}
