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

use app\common\exception\AdminResponseException;
use app\common\model\message\BackendMessage;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use Rabbit\service\websocket\PushNotification;

/**
 * 后台消息服务。
 */
class BackendMessageService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const RECEIVER_PLATFORM = 'platform';
    public const RECEIVER_MERCHANT = 'merchant';

    public const GROUP_ORDER = 'order';
    public const GROUP_FINANCE = 'finance';
    public const GROUP_USER = 'user';
    public const GROUP_AUDIT = 'audit';

    public const SUB_TYPE_VIP_BUY = 'vip_buy';
    public const SUB_TYPE_BALANCE_RECHARGE = 'balance_recharge';
    public const SUB_TYPE_ORDER_CREATE = 'order_create';
    public const SUB_TYPE_ORDER_PAID = 'order_paid';
    public const SUB_TYPE_ORDER_CONFIRM = 'order_confirm';
    public const SUB_TYPE_ORDER_CANCEL = 'order_cancel';
    public const SUB_TYPE_REFUND_APPLY = 'refund_apply';
    public const SUB_TYPE_REFUND_CANCEL = 'refund_cancel';
    public const SUB_TYPE_REFUND_APPROVE = 'refund_approve';
    public const SUB_TYPE_REFUND_REJECT = 'refund_reject';
    public const SUB_TYPE_USER_WITHDRAW = 'user_withdraw';
    public const SUB_TYPE_MERCHANT_WITHDRAW = 'merchant_withdraw';
    public const SUB_TYPE_USER_REGISTER = 'user_register';
    public const SUB_TYPE_PLATFORM_COMMENT = 'platform_comment';
    public const SUB_TYPE_CONTENT_AUDIT = 'content_audit';
    public const SUB_TYPE_SPECIAL_AUDIT = 'special_audit';
    public const SUB_TYPE_LIVE_ROOM_AUDIT = 'live_room_audit';
    public const SUB_TYPE_LIVE_ANCHOR_AUDIT = 'live_anchor_audit';
    public const SUB_TYPE_MERCHANT_AUDIT = 'merchant_audit';

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return BackendMessage::class;
    }

    /**
     * 创建消息并推送后台 WebSocket。
     *
     * @param array $payload 事件载荷，包含接收端、消息类型、关联业务和文案等字段。
     * @return array 创建后的消息数据。
     */
    public function createFromPayload(array $payload): array
    {
        $data = $this->normalizePayload($payload);
        $message = $this->create($data);
        $messageData = $this->formatMessage($message->toArray());
        $unreadCount = $this->getUnreadCount((string)$data['receiver_type'], (int)$data['receiver_id']);

        PushNotification::notifyBackendMessage(
            (string)$data['receiver_type'],
            (int)$data['receiver_id'],
            $messageData,
            $unreadCount
        );

        return $messageData;
    }

    /**
     * 获取消息分类及统计。
     *
     * @param string $receiverType 接收端类型。
     * @param int $receiverId 接收端 ID。
     * @return array 分类列表。
     */
    public function getTypes(string $receiverType, int $receiverId): array
    {
        $groups = $this->getGroupDefinitions();
        $totalUnread = $this->getUnreadCount($receiverType, $receiverId);
        $total = $this->countByReceiver($receiverType, $receiverId);
        $types = [[
            'value' => '',
            'label' => '全部消息',
            'icon' => 'el-icon-message',
            'unread_count' => $totalUnread,
            'total' => $total,
        ]];

        foreach ($groups as $value => $definition) {
            $types[] = [
                'value' => $value,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'unread_count' => $this->getUnreadCount($receiverType, $receiverId, $value),
                'total' => $this->countByReceiver($receiverType, $receiverId, $value),
            ];
        }

        return $types;
    }

    /**
     * 获取消息分页列表。
     *
     * @param array $params 查询参数：page、limit、message_group、read_status。
     * @param string $receiverType 接收端类型。
     * @param int $receiverId 接收端 ID。
     * @return array 分页结果。
     */
    public function getList(array $params, string $receiverType, int $receiverId): array
    {
        $page = max(1, (int)($params['page'] ?? request()->param('page/d', 1)));
        $limit = max(1, min(100, (int)($params['limit'] ?? request()->param('limit/d', 10))));
        $messageGroup = trim((string)($params['message_group'] ?? ''));
        $readStatus = $params['read_status'] ?? '';

        $query = $this->buildReceiverQuery($receiverType, $receiverId)
            ->when($messageGroup !== '', fn($q) => $q->where('message_group', $messageGroup))
            ->when($readStatus !== '', fn($q) => $q->where('read_status', (int)$readStatus));

        $total = (int)(clone $query)->count();
        $list = $query->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => array_map(fn($item) => $this->formatMessage($item), $list),
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
        ];
    }

    /**
     * 获取未读消息数。
     *
     * @param string $receiverType 接收端类型。
     * @param int $receiverId 接收端 ID。
     * @param string $messageGroup 消息分组，空字符串表示全部。
     * @return int 未读数。
     */
    public function getUnreadCount(string $receiverType, int $receiverId, string $messageGroup = ''): int
    {
        return (int)$this->buildReceiverQuery($receiverType, $receiverId)
            ->where('read_status', 0)
            ->when($messageGroup !== '', fn($q) => $q->where('message_group', $messageGroup))
            ->count();
    }

    /**
     * 标记指定消息为已读。
     *
     * @param array $ids 消息 ID 列表。
     * @param string $receiverType 接收端类型。
     * @param int $receiverId 接收端 ID。
     * @return int 更新数量。
     */
    public function markRead(array $ids, string $receiverType, int $receiverId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw new AdminResponseException('请选择要标记的消息');
        }

        return (int)$this->buildReceiverQuery($receiverType, $receiverId)
            ->whereIn('id', $ids)
            ->where('read_status', 0)
            ->update([
                'read_status' => 1,
                'read_time' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 标记全部消息为已读。
     *
     * @param string $receiverType 接收端类型。
     * @param int $receiverId 接收端 ID。
     * @param string $messageGroup 消息分组。
     * @return int 更新数量。
     */
    public function markAllRead(string $receiverType, int $receiverId, string $messageGroup = ''): int
    {
        return (int)$this->buildReceiverQuery($receiverType, $receiverId)
            ->where('read_status', 0)
            ->when($messageGroup !== '', fn($q) => $q->where('message_group', $messageGroup))
            ->update([
                'read_status' => 1,
                'read_time' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 软清理消息。
     *
     * @param string $receiverType 接收端类型。
     * @param int $receiverId 接收端 ID。
     * @param string $messageGroup 消息分组。
     * @return int 清理数量。
     */
    public function clear(string $receiverType, int $receiverId, string $messageGroup = ''): int
    {
        return (int)$this->buildReceiverQuery($receiverType, $receiverId)
            ->when($messageGroup !== '', fn($q) => $q->where('message_group', $messageGroup))
            ->update([
                'clear_time' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 构建接收端查询。
     */
    protected function buildReceiverQuery(string $receiverType, int $receiverId)
    {
        return $this->getModel()
            ->where('receiver_type', $receiverType)
            ->where('receiver_id', $receiverId)
            ->whereNull('clear_time');
    }

    /**
     * 统计接收端消息数。
     */
    protected function countByReceiver(string $receiverType, int $receiverId, string $messageGroup = ''): int
    {
        return (int)$this->buildReceiverQuery($receiverType, $receiverId)
            ->when($messageGroup !== '', fn($q) => $q->where('message_group', $messageGroup))
            ->count();
    }

    /**
     * 规范化事件载荷。
     */
    protected function normalizePayload(array $payload): array
    {
        $receiverType = (string)($payload['receiver_type'] ?? self::RECEIVER_PLATFORM);
        if (!in_array($receiverType, [self::RECEIVER_PLATFORM, self::RECEIVER_MERCHANT], true)) {
            throw new AdminResponseException('后台消息接收端类型不正确');
        }

        $messageGroup = $this->normalizeTextField($payload['message_group'] ?? '');
        $messageSubType = $this->normalizeTextField($payload['message_sub_type'] ?? '');
        if ($messageGroup === '' || $messageSubType === '') {
            throw new AdminResponseException('后台消息类型不能为空');
        }

        $template = $this->resolveTemplate($messageSubType);
        $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];

        return [
            'receiver_type' => $receiverType,
            'receiver_id' => (int)($payload['receiver_id'] ?? 0),
            'message_group' => $messageGroup,
            'message_sub_type' => $messageSubType,
            'relation_type' => $this->normalizeTextField($payload['relation_type'] ?? ''),
            'relation_id' => (int)($payload['relation_id'] ?? 0),
            'title' => $this->normalizeTextField($payload['title'] ?? '') ?: $template['title'],
            'content' => $this->normalizeTextField($payload['content'] ?? '') ?: $template['content'],
            'extra' => $extra,
            'read_status' => 0,
        ];
    }

    /**
     * 将消息文本字段规范化为字符串，避免数组载荷写入字符串字段时报错。
     */
    protected function normalizeTextField(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return trim((string)json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return trim((string)$value);
    }

    /**
     * 格式化消息记录。
     */
    protected function formatMessage(array $message): array
    {
        $messageGroup = (string)($message['message_group'] ?? '');
        $messageSubType = (string)($message['message_sub_type'] ?? '');
        $groupDefinitions = $this->getGroupDefinitions();
        $template = $this->resolveTemplate($messageSubType);

        return [
            'id' => (int)($message['id'] ?? 0),
            'receiver_type' => (string)($message['receiver_type'] ?? ''),
            'receiver_id' => (int)($message['receiver_id'] ?? 0),
            'message_group' => $messageGroup,
            'message_group_text' => $groupDefinitions[$messageGroup]['label'] ?? '消息',
            'message_sub_type' => $messageSubType,
            'message_sub_type_text' => $template['label'],
            'relation_type' => (string)($message['relation_type'] ?? ''),
            'relation_id' => (int)($message['relation_id'] ?? 0),
            'title' => (string)($message['title'] ?? ''),
            'content' => (string)($message['content'] ?? ''),
            'extra' => is_array($message['extra'] ?? null) ? $message['extra'] : [],
            'read_status' => (int)($message['read_status'] ?? 0),
            'read_status_text' => (int)($message['read_status'] ?? 0) === 1 ? '已读' : '未读',
            'read_time' => $message['read_time'] ?? null,
            'create_time' => $message['create_time'] ?? '',
        ];
    }

    /**
     * 消息分组定义。
     */
    protected function getGroupDefinitions(): array
    {
        return [
            self::GROUP_ORDER => ['label' => '订单通知', 'icon' => 'el-icon-s-order'],
            self::GROUP_FINANCE => ['label' => '财务通知', 'icon' => 'el-icon-bank-card'],
            self::GROUP_USER => ['label' => '用户消息', 'icon' => 'el-icon-user'],
            self::GROUP_AUDIT => ['label' => '审核消息', 'icon' => 'el-icon-s-check'],
        ];
    }

    /**
     * 子类型默认模板。
     */
    protected function resolveTemplate(string $subType): array
    {
        $templates = [
            self::SUB_TYPE_VIP_BUY => ['label' => 'VIP购买', 'title' => 'VIP购买通知', 'content' => '用户已完成VIP购买'],
            self::SUB_TYPE_BALANCE_RECHARGE => ['label' => '余额充值', 'title' => '余额充值通知', 'content' => '用户已完成余额充值'],
            self::SUB_TYPE_ORDER_CREATE => ['label' => '订单下单', 'title' => '新订单通知', 'content' => '用户已提交订单'],
            self::SUB_TYPE_ORDER_PAID => ['label' => '订单支付', 'title' => '订单支付通知', 'content' => '订单已支付成功'],
            self::SUB_TYPE_ORDER_CONFIRM => ['label' => '确认收货', 'title' => '确认收货通知', 'content' => '用户已确认收货'],
            self::SUB_TYPE_ORDER_CANCEL => ['label' => '订单取消', 'title' => '订单取消通知', 'content' => '用户已取消订单'],
            self::SUB_TYPE_REFUND_APPLY => ['label' => '发起退款', 'title' => '退款申请通知', 'content' => '订单已发起退款申请'],
            self::SUB_TYPE_REFUND_CANCEL => ['label' => '取消退款', 'title' => '退款关闭通知', 'content' => '订单退款申请已关闭'],
            self::SUB_TYPE_REFUND_APPROVE => ['label' => '退款通过', 'title' => '退款审核通过通知', 'content' => '订单退款审核已通过'],
            self::SUB_TYPE_REFUND_REJECT => ['label' => '退款拒绝', 'title' => '退款审核拒绝通知', 'content' => '订单退款审核已拒绝'],
            self::SUB_TYPE_USER_WITHDRAW => ['label' => '用户提现', 'title' => '用户提现通知', 'content' => '用户已提交提现申请'],
            self::SUB_TYPE_MERCHANT_WITHDRAW => ['label' => '商户提现', 'title' => '商户提现通知', 'content' => '商户已提交提现申请'],
            self::SUB_TYPE_USER_REGISTER => ['label' => '用户注册', 'title' => '新用户注册通知', 'content' => '有新用户完成注册'],
            self::SUB_TYPE_PLATFORM_COMMENT => ['label' => '平台评论', 'title' => '用户评论通知', 'content' => '用户提交了新的评论'],
            self::SUB_TYPE_CONTENT_AUDIT => ['label' => '内容审核', 'title' => '内容审核通知', 'content' => '内容审核状态已变更'],
            self::SUB_TYPE_SPECIAL_AUDIT => ['label' => '专题审核', 'title' => '专题审核通知', 'content' => '专题审核状态已变更'],
            self::SUB_TYPE_LIVE_ROOM_AUDIT => ['label' => '直播间审核', 'title' => '直播间审核通知', 'content' => '直播间审核状态已变更'],
            self::SUB_TYPE_LIVE_ANCHOR_AUDIT => ['label' => '主播审核', 'title' => '主播审核通知', 'content' => '主播审核状态已变更'],
            self::SUB_TYPE_MERCHANT_AUDIT => ['label' => '商户审核', 'title' => '商户审核通知', 'content' => '商户审核状态已变更'],
        ];

        return $templates[$subType] ?? ['label' => '后台消息', 'title' => '后台消息', 'content' => '您有一条新的后台消息'];
    }
}
