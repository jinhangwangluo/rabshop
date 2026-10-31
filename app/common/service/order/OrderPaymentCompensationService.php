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

namespace app\common\service\order;

use app\common\exception\AdminResponseException;
use app\common\job\order\OrderPaymentCompensationJob;
use app\common\model\order\OrderPaymentCompensation;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\finance\FinanceService;

/**
 * 使用固定退款单号重试被拒收的实收款，插件卸载后仍可继续处理。
 */
class OrderPaymentCompensationService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 指定当前服务管理的持久化模型。
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return OrderPaymentCompensation::class;
    }

    /**
     * 持有订单锁时登记已验证的实收凭证，不恢复已关闭订单。
     *
     * @param array $order 不可变的订单数据。
     * @param string $payType 已验证的支付渠道。
     * @param string $tradeType 支付渠道配置类型。
     * @param string $transactionId 已验证的支付渠道交易标识。
     * @param int $amountFen 已验证的实收金额，单位为分。
     * @return array 永久补偿记录。
     */
    public function register(array $order, string $payType, string $tradeType, string $transactionId, int $amountFen): array
    {
        if ($transactionId === '' || strlen($transactionId) > 128 || $amountFen <= 0) {
            throw new AdminResponseException('实收补偿凭证无效');
        }
        $record = $this->getModel()->where('pay_type', $payType)->where('transaction_id', $transactionId)->lock(true)->find();
        if ($record) {
            if ((int) $record['order_id'] !== (int) $order['id'] || (int) $record['amount_fen'] !== $amountFen) {
                throw new AdminResponseException('实收补偿凭证冲突');
            }
            return $record->toArray();
        }
        $data = [
            'order_id' => (int) $order['id'], 'order_sn' => (string) $order['order_sn'],
            'user_id' => (int) $order['user_id'], 'pay_type' => $payType, 'trade_type' => $tradeType,
            'transaction_id' => $transactionId, 'refund_sn' => get_sn('LC'), 'amount_fen' => $amountFen,
            'status' => 'pending', 'attempts' => 0, 'next_attempt_time' => date('Y-m-d H:i:s'), 'error_code' => '',
        ];
        $record = $this->create($data)->toArray();
        $this->writeFinance($record, 'in');
        return $record;
    }

    /**
     * 有界批量重试；支付渠道失败时保留待处理的永久记录。
     *
     * @return array 本批到期补偿的任务总数、入队及失败数量和对应编号。
     */
    public function retryDue(): array
    {
        $ids = $this->getModel()->where('status', 'pending')->where('next_attempt_time', '<=', date('Y-m-d H:i:s'))
            ->order('id asc')->limit(50)->column('id');
        $result = ['total' => count($ids), 'queued_count' => 0, 'invalid_count' => 0, 'queued_ids' => [], 'invalid_ids' => []];
        foreach ($ids as $id) {
            try {
                if (!OrderPaymentCompensationJob::dispatch((int) $id)) {
                    throw new \RuntimeException('Queue unavailable');
                }
                $result['queued_count']++;
                $result['queued_ids'][] = (int) $id;
            } catch (\Throwable) {
                $result['invalid_count']++;
                $result['invalid_ids'][] = (int) $id;
            }
        }
        return $result;
    }

    /**
     * 处理一项永久退款任务，通过行锁保证重复投递的幂等性。
     *
     * @param int $id 宿主永久实收退款补偿记录编号。
     * @return bool 本项补偿退款是否完成；未到重试时间或渠道未确认时为假。
     */
    public function retryOne(int $id): bool
    {
        return $this->transaction(function () use ($id): bool {
            $record = $this->getModel()->where('id', $id)->lock(true)->find();
            if (!$record || $record['status'] === 'completed') {
                return true;
            }
            if (strtotime((string) $record['next_attempt_time']) > time()) {
                return false;
            }
            $attempts = (int) $record['attempts'] + 1;
            try {
                $complete = app()->make(OrderPayService::class)->refundRejectedReceipt($record->toArray());
                $error = $complete ? '' : 'PROVIDER_REFUND_PENDING';
            } catch (\Throwable) {
                $complete = false;
                $error = 'PROVIDER_REFUND_RETRY';
            }
            $record->save([
                'status' => $complete ? 'completed' : 'pending', 'attempts' => $attempts,
                'next_attempt_time' => date('Y-m-d H:i:s', time() + min(3600, 30 * (2 ** min(7, $attempts)))),
                'error_code' => $error,
            ]);
            if ($complete) {
                $this->writeFinance($record->toArray(), 'out');
            }
            return $complete;
        });
    }

    /**
     * 确认被拒收实收款的已验证退款，不修改普通退款或订单状态。
     *
     * @param string $refundSn 固定的补偿退款单号。
     * @param string $tradeType 用于验证通知的渠道配置类型。
     * @param array $payload 已解密并通过验签的支付渠道报文。
     * @param array $config 已验证的支付渠道配置。
     * @return array|null 非补偿退款时返回空值。
     */
    public function acceptWechatRefundNotification(string $refundSn, string $tradeType, array $payload, array $config): ?array
    {
        return $this->transaction(function () use ($refundSn, $tradeType, $payload, $config): ?array {
            $record = $this->getModel()->where('refund_sn', $refundSn)->lock(true)->find();
            if (!$record) {
                return null;
            }
            $sameConfiguration = (string) $record['trade_type'] === $tradeType
                || ((string) $record['trade_type'] === 'h5' && $tradeType === 'oa_jsapi');
            $amount = is_array($payload['amount'] ?? null) ? $payload['amount'] : [];
            if ((string) $record['pay_type'] !== \app\common\enum\PayTypeEnum::PAY_TYPE_WECHAT || !$sameConfiguration
                || (string) ($payload['mchid'] ?? '') !== (string) ($config['mch_id'] ?? '')
                || (string) ($payload['out_trade_no'] ?? '') !== (string) $record['order_sn']
                || (string) ($payload['transaction_id'] ?? '') !== (string) $record['transaction_id']
                || (int) ($amount['refund'] ?? 0) !== (int) $record['amount_fen']
                || (int) ($amount['total'] ?? 0) !== (int) $record['amount_fen']) {
                throw new AdminResponseException('实收补偿退款凭证与渠道、订单或金额不一致');
            }
            $status = strtoupper((string) ($payload['refund_status'] ?? $payload['status'] ?? ''));
            if (!in_array($status, ['SUCCESS', 'PROCESSING', 'ABNORMAL', 'CLOSED'], true)) {
                throw new AdminResponseException('实收补偿退款状态不明确');
            }
            if ($record['status'] !== 'completed') {
                $record->save([
                    'status' => $status === 'SUCCESS' ? 'completed' : 'pending',
                    'error_code' => $status === 'SUCCESS' ? '' : 'PROVIDER_REFUND_' . $status,
                    'next_attempt_time' => date('Y-m-d H:i:s', time() + 60),
                ]);
                if ($status === 'SUCCESS') {
                    $this->writeFinance($record->toArray(), 'out');
                }
            }
            return ['refund_sn' => $refundSn, 'trade_type' => $tradeType, 'refund_status' => $status, 'compensation' => true];
        });
    }

    /**
     * 持有凭证锁时，每个收款或退款方向仅写一条资金流水。
     *
     * @param array $record 已加载的退款或永久实收补偿记录。
     * @param string $direction 资金方向，收款为 in、退款为 out。
     * @return void 无返回值；每个永久凭证的收款及退款方向最多登记一次流水。
     */
    protected function writeFinance(array $record, string $direction): void
    {
        $finance = app()->make(FinanceService::class);
        $remark = '迟到实收补偿：' . $record['refund_sn'];
        if ($finance->getModel()->where('order_sn', $record['order_sn'])->where('remark', $remark)->where('change_type', $direction)->count() > 0) {
            return;
        }
        $finance->createRecord([
            'pid' => 0, 'user_id' => (int) $record['user_id'], 'merchant_id' => 0,
            'type' => $direction === 'in' ? 'late_payment' : 'late_payment_refund', 'change_type' => $direction,
            'fund_status' => FinanceService::FUND_STATUS_AVAILABLE,
            'amount' => (float) bcdiv((string) $record['amount_fen'], '100', 2),
            'order_sn' => $record['order_sn'], 'remark' => $remark,
        ]);
    }
}
