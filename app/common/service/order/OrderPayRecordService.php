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

use app\common\model\order\OrderPayRecord;
use app\common\service\BaseService;

/**
 * 订单支付记录服务
 */
class OrderPayRecordService extends BaseService
{
    public const STATUS_PENDING = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAILED = 2;
    public const STATUS_COMPENSATING = 3;

    /**
     * 指定当前服务管理的持久化模型。
     *
     * @return string 当前服务对应的模型完整类名。
     */
    protected function setModel(): string
    {
        return OrderPayRecord::class;
    }

    /**
     * 保存或更新支付发起记录
     *
     * @param array $data 本次订单身份、支付渠道及发起请求与响应字段。
     * @return array 已创建或更新的支付发起记录。
     */
    public function saveInitiatedRecord(array $data): array
    {
        $orderSn = (string) ($data['order_sn'] ?? '');
        $record = $orderSn !== '' ? $this->get(['order_sn' => $orderSn]) : null;
        $payload = array_merge([
            'order_id' => 0,
            'order_sn' => '',
            'user_id' => 0,
            'pay_type' => '',
            'trade_type' => '',
            'amount' => 0,
            'status' => self::STATUS_PENDING,
            'openid' => '',
            'prepay_id' => '',
            'transaction_id' => '',
            'request_payload' => [],
            'response_payload' => [],
            'notify_payload' => [],
            'error_message' => '',
            'pay_time' => null,
            'notify_time' => null,
        ], $data);

        if ($record) {
            $this->update((int) $record['id'], $payload);
            $record = $this->get((int) $record['id']);
        } else {
            $record = $this->create($payload);
        }

        return $record ? $record->toArray() : [];
    }

    /**
     * 标记支付成功
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @param array $data 已验证的支付渠道结果及支付时间字段。
     * @return void 无返回值；按订单号更新已验证的支付成功凭证。
     */
    public function markPaid(string $orderSn, array $data = []): void
    {
        $record = $this->get(['order_sn' => $orderSn]);
        if (!$record) {
            return;
        }

        $this->update((int) $record['id'], array_merge([
            'status' => self::STATUS_SUCCESS,
            'notify_time' => date('Y-m-d H:i:s'),
            'pay_time' => $data['pay_time'] ?? date('Y-m-d H:i:s'),
            'transaction_id' => (string) ($data['transaction_id'] ?? ($record['transaction_id'] ?? '')),
            'notify_payload' => $data['notify_payload'] ?? ($record['notify_payload'] ?? []),
            'response_payload' => $data['response_payload'] ?? ($record['response_payload'] ?? []),
            'error_message' => '',
        ], $data));
    }

    /**
     * 标记支付失败
     *
     * @param string $orderSn 宿主订单的业务单号。
     * @param string $message 当前业务处理或失败原因文案。
     * @param array $data 已验证的支付渠道结果及支付时间字段。
     * @return void 无返回值；按订单号登记支付失败状态和原因。
     */
    public function markFailed(string $orderSn, string $message, array $data = []): void
    {
        $record = $this->get(['order_sn' => $orderSn]);
        if (!$record) {
            return;
        }

        $this->update((int) $record['id'], array_merge([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'response_payload' => $data['response_payload'] ?? ($record['response_payload'] ?? []),
        ], $data));
    }
}
