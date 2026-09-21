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

namespace app\common\service\message;

use app\common\enum\AuditStatusEnum;
use think\facade\Log;

/**
 * 后台消息事件服务。
 *
 * 业务 Service 只调用本服务触发事件，不直接写消息表。
 */
class BackendMessageEventService
{
    /**
     * 触发后台消息事件。
     *
     * @param array $payload 消息载荷。
     */
    public function dispatch(array $payload): void
    {
        try {
            event('backend.message', $payload);
        } catch (\Throwable $e) {
            Log::error('[BackendMessageEventService] 事件触发失败：' . $e->getMessage() . '|' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 订单下单通知。
     *
     * @param array $order 订单数据。
     */
    public function orderCreated(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_ORDER_CREATE, $order, '用户已提交订单');

        $children = is_array($order['children'] ?? null) ? $order['children'] : [];
        foreach ($children as $childOrder) {
            if (!is_array($childOrder) || (int)($childOrder['merchant_id'] ?? 0) <= 0) {
                continue;
            }

            $this->dispatchOrderMessage(
                BackendMessageService::SUB_TYPE_ORDER_CREATE,
                array_merge([
                    'main_order_sn' => (string)($order['order_sn'] ?? ''),
                    'user_id' => (int)($order['user_id'] ?? 0),
                ], $childOrder),
                '用户已提交订单',
                false
            );
        }
    }

    /**
     * 订单支付通知。
     *
     * @param array $order 订单数据。
     */
    public function orderPaid(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_ORDER_PAID, $order, '订单已支付成功');
    }

    /**
     * 订单取消通知。
     *
     * @param array $order 订单数据。
     */
    public function orderCancelled(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_ORDER_CANCEL, $order, '用户已取消订单');
    }

    /**
     * 确认收货通知。
     *
     * @param array $order 订单数据。
     */
    public function orderConfirmed(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_ORDER_CONFIRM, $order, '用户已确认收货');
    }

    /**
     * 发起退款通知。
     *
     * @param array $order 订单数据。
     */
    public function refundApplied(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_REFUND_APPLY, $order, '订单已发起退款申请');
        app()->make(MessagePushEventService::class)->dispatch(
            MessagePushDefinitionService::EVENT_REFUND_APPLY_ADMIN,
            array_merge($order, [
                'relation_type' => 'order',
                'relation_id' => (int)($order['id'] ?? $order['order_id'] ?? 0),
            ])
        );
    }

    /**
     * 退款关闭通知。
     *
     * @param array $order 订单数据。
     */
    public function refundCancelled(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_REFUND_CANCEL, $order, '订单退款申请已关闭');
    }

    /**
     * 退款审核通过通知。
     *
     * @param array $order 订单数据。
     */
    public function refundApproved(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_REFUND_APPROVE, $order, '订单退款审核已通过');
    }

    /**
     * 退款审核拒绝通知。
     *
     * @param array $order 订单数据。
     */
    public function refundRejected(array $order): void
    {
        $this->dispatchOrderMessage(BackendMessageService::SUB_TYPE_REFUND_REJECT, $order, '订单退款审核已拒绝');
    }

    /**
     * 用户余额充值通知。
     *
     * @param array $order 充值订单。
     */
    public function balanceRecharged(array $order): void
    {
        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_PLATFORM,
            'receiver_id' => 0,
            'message_group' => BackendMessageService::GROUP_ORDER,
            'message_sub_type' => BackendMessageService::SUB_TYPE_BALANCE_RECHARGE,
            'relation_type' => 'user_recharge_order',
            'relation_id' => (int)($order['id'] ?? 0),
            'title' => '余额充值通知',
            'content' => '用户ID ' . (int)($order['user_id'] ?? 0) . ' 已充值 ' . (float)($order['amount'] ?? 0) . ' 元',
            'extra' => $order,
        ]);
    }

    /**
     * VIP 购买通知。
     *
     * @param array $order 会员订单。
     */
    public function vipBought(array $order): void
    {
        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_PLATFORM,
            'receiver_id' => 0,
            'message_group' => BackendMessageService::GROUP_ORDER,
            'message_sub_type' => BackendMessageService::SUB_TYPE_VIP_BUY,
            'relation_type' => 'user_recharge_order',
            'relation_id' => (int)($order['id'] ?? 0),
            'title' => 'VIP购买通知',
            'content' => '用户ID ' . (int)($order['user_id'] ?? 0) . ' 已购买VIP',
            'extra' => $order,
        ]);
    }

    /**
     * 用户提现通知。
     *
     * @param array $withdraw 提现记录。
     */
    public function userWithdrawApplied(array $withdraw): void
    {
        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_PLATFORM,
            'receiver_id' => 0,
            'message_group' => BackendMessageService::GROUP_FINANCE,
            'message_sub_type' => BackendMessageService::SUB_TYPE_USER_WITHDRAW,
            'relation_type' => 'user_withdraw',
            'relation_id' => (int)($withdraw['id'] ?? 0),
            'title' => '用户提现通知',
            'content' => '用户ID ' . (int)($withdraw['user_id'] ?? 0) . ' 提交了提现申请',
            'extra' => $withdraw,
        ]);
    }

    /**
     * 商户提现通知。
     *
     * @param array $withdraw 提现记录。
     */
    public function merchantWithdrawApplied(array $withdraw): void
    {
        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_PLATFORM,
            'receiver_id' => 0,
            'message_group' => BackendMessageService::GROUP_FINANCE,
            'message_sub_type' => BackendMessageService::SUB_TYPE_MERCHANT_WITHDRAW,
            'relation_type' => 'merchant_withdraw',
            'relation_id' => (int)($withdraw['id'] ?? 0),
            'title' => '商户提现通知',
            'content' => '商户ID ' . (int)($withdraw['merchant_id'] ?? 0) . ' 提交了提现申请',
            'extra' => $withdraw,
        ]);
    }

    /**
     * 用户注册通知。
     *
     * @param array $user 用户数据。
     */
    public function userRegistered(array $user): void
    {
        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_PLATFORM,
            'receiver_id' => 0,
            'message_group' => BackendMessageService::GROUP_USER,
            'message_sub_type' => BackendMessageService::SUB_TYPE_USER_REGISTER,
            'relation_type' => 'user',
            'relation_id' => (int)($user['id'] ?? 0),
            'title' => '新用户注册通知',
            'content' => '用户 ' . ((string)($user['nickname'] ?? '') ?: ('ID ' . (int)($user['id'] ?? 0))) . ' 已完成注册',
            'extra' => $user,
        ]);
    }

    /**
     * 平台评论通知。
     *
     * @param array $comment 评论数据。
     */
    public function platformCommentCreated(array $comment): void
    {
        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_PLATFORM,
            'receiver_id' => 0,
            'message_group' => BackendMessageService::GROUP_USER,
            'message_sub_type' => BackendMessageService::SUB_TYPE_PLATFORM_COMMENT,
            'relation_type' => 'content_comment',
            'relation_id' => (int)($comment['id'] ?? 0),
            'title' => '用户评论通知',
            'content' => '用户ID ' . (int)($comment['user_id'] ?? 0) . ' 提交了新的评论',
            'extra' => $comment,
        ]);
    }

    /**
     * 提交审核通知平台。
     *
     * @param string $subType 审核子类型。
     * @param string $relationType 关联类型。
     * @param int $relationId 关联 ID。
     * @param int $merchantId 商户 ID。
     * @param string $title 业务标题。
     */
    public function auditSubmitted(
        string $subType,
        string $relationType,
        int $relationId,
        int $merchantId,
        string $title
    ): void {
        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_PLATFORM,
            'receiver_id' => 0,
            'message_group' => BackendMessageService::GROUP_AUDIT,
            'message_sub_type' => $subType,
            'relation_type' => $relationType,
            'relation_id' => $relationId,
            'title' => '新的审核待办',
            'content' => '商户ID ' . $merchantId . ' 提交了「' . $title . '」等待审核',
            'extra' => [
                'merchant_id' => $merchantId,
                'title' => $title,
                'audit_scene' => 'submit',
            ],
        ]);
    }

    /**
     * 审核结果通知商户。
     *
     * @param string $subType 审核子类型。
     * @param string $relationType 关联类型。
     * @param int $relationId 关联 ID。
     * @param int $merchantId 商户 ID。
     * @param int $auditStatus 审核状态。
     * @param string $remark 审核备注。
     * @param string $title 业务标题。
     */
    public function auditResult(
        string $subType,
        string $relationType,
        int $relationId,
        int $merchantId,
        int $auditStatus,
        string $remark,
        string $title
    ): void {
        if ($merchantId <= 0) {
            return;
        }

        $statusText = $this->resolveAuditStatusText($auditStatus);
        $content = '「' . $title . '」审核结果：' . $statusText;
        if ($remark !== '') {
            $content .= '，备注：' . $remark;
        }

        $this->dispatch([
            'receiver_type' => BackendMessageService::RECEIVER_MERCHANT,
            'receiver_id' => $merchantId,
            'message_group' => BackendMessageService::GROUP_AUDIT,
            'message_sub_type' => $subType,
            'relation_type' => $relationType,
            'relation_id' => $relationId,
            'title' => '审核结果通知',
            'content' => $content,
            'extra' => [
                'merchant_id' => $merchantId,
                'audit_status' => $auditStatus,
                'audit_status_text' => $statusText,
                'audit_remark' => $remark,
                'title' => $title,
                'audit_scene' => 'result',
            ],
        ]);

        $pushEventKey = $this->resolveAuditPushEvent($subType);
        if ($pushEventKey !== '') {
            app()->make(MessagePushEventService::class)->dispatch($pushEventKey, [
                'merchant_id' => $merchantId,
                'audit_status' => $auditStatus,
                'audit_status_text' => $statusText,
                'audit_remark' => $remark,
                'audit_time' => date('Y-m-d H:i:s'),
                'content_id' => $relationId,
                'content_title' => $title,
                'live_id' => $relationId,
                'live_title' => $title,
                'relation_type' => $relationType,
                'relation_id' => $relationId,
            ]);
        }
    }

    /**
     * 商户审核结果通知。
     *
     * @param array $merchant 商户或申请数据。
     * @param int $auditStatus 审核状态。
     * @param string $remark 审核备注。
     */
    public function merchantAuditResult(array $merchant, int $auditStatus, string $remark = ''): void
    {
        $merchantId = (int)($merchant['id'] ?? $merchant['merchant_id'] ?? 0);
        $statusText = $this->resolveAuditStatusText($auditStatus);
        $receiverType = $merchantId > 0 ? BackendMessageService::RECEIVER_MERCHANT : BackendMessageService::RECEIVER_PLATFORM;

        $this->dispatch([
            'receiver_type' => $receiverType,
            'receiver_id' => $merchantId,
            'message_group' => BackendMessageService::GROUP_AUDIT,
            'message_sub_type' => BackendMessageService::SUB_TYPE_MERCHANT_AUDIT,
            'relation_type' => 'merchant',
            'relation_id' => $merchantId ?: (int)($merchant['apply_id'] ?? 0),
            'title' => '商户审核通知',
            'content' => '商户审核结果：' . $statusText . ($remark !== '' ? '，备注：' . $remark : ''),
            'extra' => array_merge($merchant, [
                'audit_status' => $auditStatus,
                'audit_status_text' => $statusText,
                'audit_remark' => $remark,
            ]),
        ]);
    }

    /**
     * 发送订单消息。
     */
    protected function dispatchOrderMessage(
        string $subType,
        array $order,
        string $defaultContent,
        bool $notifyPlatform = true
    ): void {
        if ($notifyPlatform) {
            $this->dispatchOrderMessageToReceiver(
                BackendMessageService::RECEIVER_PLATFORM,
                0,
                $subType,
                $order,
                $defaultContent
            );
        }

        $merchantId = (int)($order['merchant_id'] ?? 0);
        if ($merchantId <= 0) {
            return;
        }

        $this->dispatchOrderMessageToReceiver(
            BackendMessageService::RECEIVER_MERCHANT,
            $merchantId,
            $subType,
            $order,
            $defaultContent
        );
    }

    /**
     * 发送指定接收端订单消息。
     */
    protected function dispatchOrderMessageToReceiver(
        string $receiverType,
        int $receiverId,
        string $subType,
        array $order,
        string $defaultContent
    ): void
    {
        $orderSn = (string)($order['order_sn'] ?? '');

        $this->dispatch([
            'receiver_type' => $receiverType,
            'receiver_id' => $receiverId,
            'message_group' => BackendMessageService::GROUP_ORDER,
            'message_sub_type' => $subType,
            'relation_type' => 'order',
            'relation_id' => (int)($order['id'] ?? $order['order_id'] ?? 0),
            'title' => '订单通知',
            'content' => $defaultContent . ($orderSn !== '' ? '，订单号：' . $orderSn : ''),
            'extra' => $order,
        ]);
    }

    /**
     * 获取审核状态文案。
     */
    protected function resolveAuditStatusText(int $auditStatus): string
    {
        if (class_exists(AuditStatusEnum::class) && method_exists(AuditStatusEnum::class, 'list')) {
            $list = AuditStatusEnum::list();
            if (isset($list[$auditStatus])) {
                return (string)$list[$auditStatus];
            }
        }

        return match ($auditStatus) {
            1 => '已通过',
            2 => '已驳回',
            default => '待审核',
        };
    }

    /**
     * 解析审核类外部推送事件。
     */
    protected function resolveAuditPushEvent(string $subType): string
    {
        return match ($subType) {
            BackendMessageService::SUB_TYPE_CONTENT_AUDIT => MessagePushDefinitionService::EVENT_CONTENT_AUDIT,
            BackendMessageService::SUB_TYPE_SPECIAL_AUDIT => MessagePushDefinitionService::EVENT_SPECIAL_AUDIT,
            BackendMessageService::SUB_TYPE_LIVE_ROOM_AUDIT,
            BackendMessageService::SUB_TYPE_LIVE_ANCHOR_AUDIT => MessagePushDefinitionService::EVENT_LIVE_AUDIT,
            default => '',
        };
    }
}
