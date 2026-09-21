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

use app\common\service\BaseService;
use app\common\model\Base;
use Throwable;

/**
 * 消息推送内置定义服务。
 *
 * 该服务集中维护消息推送模块的稳定业务定义，包括发送渠道、消息事件、接收人类型、
 * 模板默认内容、可用变量和小程序固定跳转页。这里不读写业务表，只为模板配置、
 * 发送服务和管理后台页面提供统一的元数据。
 */
class MessagePushDefinitionService extends BaseService
{
    /** 短信渠道。 */
    public const CHANNEL_SMS = 'sms';

    /** 邮箱推送渠道。 */
    public const CHANNEL_EMAIL = 'email';

    /** 微信公众号模板消息渠道。 */
    public const CHANNEL_OFFICIAL_ACCOUNT = 'official_account';

    /** 微信小程序订阅消息渠道。 */
    public const CHANNEL_MINIAPP = 'miniapp';

    /** 企业微信机器人渠道。 */
    public const CHANNEL_WECOM_BOT = 'wecom_bot';

    /** 钉钉机器人渠道。 */
    public const CHANNEL_DINGTALK_BOT = 'dingtalk_bot';

    /** 其他机器人 Webhook 渠道。 */
    public const CHANNEL_CUSTOM_BOT = 'custom_bot';

    /** 用户退款成功通知事件。 */
    public const EVENT_REFUND_SUCCESS = 'refund_success';

    /** 用户退款失败通知事件。 */
    public const EVENT_REFUND_FAILED = 'refund_failed';

    /** 用户提现审核通过通知事件。 */
    public const EVENT_WITHDRAW_APPROVED = 'withdraw_approved';

    /** 用户提现失败通知事件。 */
    public const EVENT_WITHDRAW_FAILED = 'withdraw_failed';

    /** 管理员退款申请通知事件。 */
    public const EVENT_REFUND_APPLY_ADMIN = 'refund_apply_admin';

    /** 商户内容审核结果通知事件。 */
    public const EVENT_CONTENT_AUDIT = 'content_audit';

    /** 商户专题审核结果通知事件。 */
    public const EVENT_SPECIAL_AUDIT = 'special_audit';

    /** 商户直播审核结果通知事件。 */
    public const EVENT_LIVE_AUDIT = 'live_audit';

    /** 接收人为平台用户。 */
    public const RECEIVER_USER = 'user';

    /** 接收人为平台管理员配置的手动接收人。 */
    public const RECEIVER_ADMIN = 'admin';

    /** 接收人为商户。 */
    public const RECEIVER_MERCHANT = 'merchant';

    /**
     * 设置模型。定义服务不依赖具体表。
     */
    protected function setModel(): string
    {
        return Base::class;
    }

    /**
     * 获取全部可配置发送渠道。
     *
     * @return array<string, array{value: string, label: string}> 渠道标识映射到管理端展示文案。
     */
    public function getChannels(): array
    {
        return [
            self::CHANNEL_SMS => ['value' => self::CHANNEL_SMS, 'label' => '短信'],
            self::CHANNEL_EMAIL => ['value' => self::CHANNEL_EMAIL, 'label' => '邮箱推送'],
            self::CHANNEL_OFFICIAL_ACCOUNT => ['value' => self::CHANNEL_OFFICIAL_ACCOUNT, 'label' => '公众号模板消息'],
            self::CHANNEL_MINIAPP => ['value' => self::CHANNEL_MINIAPP, 'label' => '小程序订阅消息'],
            self::CHANNEL_WECOM_BOT => ['value' => self::CHANNEL_WECOM_BOT, 'label' => '企微BOT'],
            self::CHANNEL_DINGTALK_BOT => ['value' => self::CHANNEL_DINGTALK_BOT, 'label' => '钉钉BOT'],
            self::CHANNEL_CUSTOM_BOT => ['value' => self::CHANNEL_CUSTOM_BOT, 'label' => '其他BOT'],
        ];
    }

    /**
     * 获取全部通知类型。
     *
     * @return array<string, array{value: string, label: string}> 通知类型映射到管理端文案。
     */
    public function getReceiverTypes(): array
    {
        return [
            self::RECEIVER_USER => ['value' => self::RECEIVER_USER, 'label' => '用户'],
            self::RECEIVER_MERCHANT => ['value' => self::RECEIVER_MERCHANT, 'label' => '商户'],
            self::RECEIVER_ADMIN => ['value' => self::RECEIVER_ADMIN, 'label' => '平台管理员'],
        ];
    }

    /**
     * 获取通知类型文案。
     */
    public function getReceiverTypeLabel(string $receiverType): string
    {
        $types = $this->getReceiverTypes();

        return $types[$receiverType]['label'] ?? $receiverType;
    }

    /**
     * 判断通知类型是否存在。
     */
    public function hasReceiverType(string $receiverType): bool
    {
        return isset($this->getReceiverTypes()[$receiverType]);
    }

    /**
     * 获取指定事件可配置的发送渠道。
     *
     * @param string|array<string, mixed> $event 事件标识或事件定义。
     * @return array<string, array{value: string, label: string}> 渠道标识映射到管理端展示文案。
     */
    public function getChannelsForEvent(string|array $event): array
    {
        $channels = [];
        foreach ($this->getChannels() as $channel => $definition) {
            if ($this->isEventChannelAvailable($event, $channel)) {
                $channels[$channel] = $definition;
            }
        }

        return $channels;
    }

    /**
     * 判断事件是否支持指定发送渠道。
     *
     * 短信、邮箱和 Webhook 可用于全部接收对象；微信公众号模板消息和小程序订阅消息只支持平台用户。
     *
     * @param string|array<string, mixed> $event 事件标识或事件定义。
     * @param string $channel 渠道标识。
     */
    public function isEventChannelAvailable(string|array $event, string $channel): bool
    {
        if (!$this->hasChannel($channel)) {
            return false;
        }

        $eventData = is_array($event) ? $event : $this->getEvent($event);
        if (!$eventData) {
            return false;
        }

        if (in_array($channel, [self::CHANNEL_SMS, self::CHANNEL_EMAIL], true) || $this->isWebhookChannel($channel)) {
            return true;
        }

        return (string)($eventData['receiver_type'] ?? '') === self::RECEIVER_USER
            && in_array($channel, [self::CHANNEL_OFFICIAL_ACCOUNT, self::CHANNEL_MINIAPP], true);
    }

    /**
     * 判断是否为 Webhook BOT 渠道。
     */
    public function isWebhookChannel(string $channel): bool
    {
        return in_array($channel, [
            self::CHANNEL_WECOM_BOT,
            self::CHANNEL_DINGTALK_BOT,
            self::CHANNEL_CUSTOM_BOT,
        ], true);
    }

    /**
     * 获取全部内置消息事件定义。
     *
     * 每个事件定义包含事件标识、展示名称、接收人类型、关联业务类型、排序值和可用变量。
     * 方法末尾会把渠道列表补进每个事件，供管理端列表一次性渲染可配置渠道入口。
     *
     * @return array<string, array<string, mixed>> 事件标识映射到事件元数据。
     */
    public function getEvents(): array
    {
        $events = $this->getSystemEvents();
        foreach ($this->getCustomEvents() as $eventKey => $event) {
            if (!isset($events[$eventKey])) {
                $events[$eventKey] = $event;
            }
        }

        uasort($events, static function (array $first, array $second): int {
            return ((int)($first['sort'] ?? 0)) <=> ((int)($second['sort'] ?? 0));
        });

        foreach ($events as &$event) {
            $event['is_system'] = (int)($event['is_system'] ?? 1);
            $event['status'] = (int)($event['status'] ?? 1);
            $event['remark'] = (string)($event['remark'] ?? '');
            $event['channels'] = array_values($this->getChannelsForEvent($event));
        }

        return $events;
    }

    /**
     * 获取系统内置消息事件定义。
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSystemEvents(): array
    {
        return [
            self::EVENT_REFUND_SUCCESS => [
                'key' => self::EVENT_REFUND_SUCCESS,
                'label' => '退款成功通知',
                'receiver_type' => self::RECEIVER_USER,
                'receiver_text' => '用户',
                'relation_type' => 'order',
                'sort' => 10,
                'variables' => $this->mergeVariables(['order', 'refund', 'user', 'merchant']),
            ],
            self::EVENT_REFUND_FAILED => [
                'key' => self::EVENT_REFUND_FAILED,
                'label' => '退款失败通知',
                'receiver_type' => self::RECEIVER_USER,
                'receiver_text' => '用户',
                'relation_type' => 'order',
                'sort' => 20,
                'variables' => $this->mergeVariables(['order', 'refund', 'user', 'merchant']),
            ],
            self::EVENT_WITHDRAW_APPROVED => [
                'key' => self::EVENT_WITHDRAW_APPROVED,
                'label' => '提现审核通过通知',
                'receiver_type' => self::RECEIVER_USER,
                'receiver_text' => '用户',
                'relation_type' => 'user_withdraw',
                'sort' => 30,
                'variables' => $this->mergeVariables(['withdraw', 'user']),
            ],
            self::EVENT_WITHDRAW_FAILED => [
                'key' => self::EVENT_WITHDRAW_FAILED,
                'label' => '提现失败通知',
                'receiver_type' => self::RECEIVER_USER,
                'receiver_text' => '用户',
                'relation_type' => 'user_withdraw',
                'sort' => 40,
                'variables' => $this->mergeVariables(['withdraw', 'user']),
            ],
            self::EVENT_REFUND_APPLY_ADMIN => [
                'key' => self::EVENT_REFUND_APPLY_ADMIN,
                'label' => '退款申请通知管理员',
                'receiver_type' => self::RECEIVER_ADMIN,
                'receiver_text' => '平台管理员',
                'relation_type' => 'order',
                'sort' => 50,
                'variables' => $this->mergeVariables(['order', 'refund', 'user', 'merchant']),
            ],
            self::EVENT_CONTENT_AUDIT => [
                'key' => self::EVENT_CONTENT_AUDIT,
                'label' => '内容审核通知',
                'receiver_type' => self::RECEIVER_MERCHANT,
                'receiver_text' => '商户',
                'relation_type' => 'article',
                'sort' => 60,
                'variables' => $this->mergeVariables(['audit', 'content', 'merchant']),
            ],
            self::EVENT_SPECIAL_AUDIT => [
                'key' => self::EVENT_SPECIAL_AUDIT,
                'label' => '专题审核通知',
                'receiver_type' => self::RECEIVER_MERCHANT,
                'receiver_text' => '商户',
                'relation_type' => 'special',
                'sort' => 70,
                'variables' => $this->mergeVariables(['audit', 'content', 'merchant']),
            ],
            self::EVENT_LIVE_AUDIT => [
                'key' => self::EVENT_LIVE_AUDIT,
                'label' => '直播审核通知',
                'receiver_type' => self::RECEIVER_MERCHANT,
                'receiver_text' => '商户',
                'relation_type' => 'live_room',
                'sort' => 80,
                'variables' => $this->mergeVariables(['audit', 'live', 'merchant']),
            ],
        ];
    }

    /**
     * 获取指定事件定义。
     *
     * @param string $eventKey 事件标识，如 refund_success。
     * @return array<string, mixed> 事件不存在时返回空数组。
     */
    public function getEvent(string $eventKey): array
    {
        $events = $this->getEvents();

        return $events[$eventKey] ?? [];
    }

    /**
     * 判断事件是否存在于内置定义中。
     *
     * @param string $eventKey 事件标识。
     */
    public function hasEvent(string $eventKey): bool
    {
        return isset($this->getEvents()[$eventKey]);
    }

    /**
     * 判断事件是否为系统内置事件。
     */
    public function hasSystemEvent(string $eventKey): bool
    {
        return isset($this->getSystemEvents()[$eventKey]);
    }

    /**
     * 判断渠道是否存在于内置定义中。
     *
     * @param string $channel 渠道标识。
     */
    public function hasChannel(string $channel): bool
    {
        return isset($this->getChannels()[$channel]);
    }

    /**
     * 获取小程序订阅消息固定跳转页。
     *
     * 小程序跳转页不允许在管理端手动修改；发送时 MessagePushService 会根据事件和业务
     * payload 自动拼接业务 ID。这里仅返回页面路径，避免模板配置里出现不一致跳转地址。
     *
     * @param string $eventKey 事件标识。
     */
    public function getMiniappPage(string $eventKey): string
    {
        return match ($eventKey) {
            self::EVENT_REFUND_SUCCESS,
            self::EVENT_REFUND_FAILED,
            self::EVENT_REFUND_APPLY_ADMIN => '/pages/orders/order/detail/index',
            self::EVENT_WITHDRAW_APPROVED,
            self::EVENT_WITHDRAW_FAILED => '/pages/users/withdraw/index',
            self::EVENT_CONTENT_AUDIT => '/pages/contents/content/detail/index',
            self::EVENT_SPECIAL_AUDIT => '/pages/contents/special/detail/index',
            self::EVENT_LIVE_AUDIT => '/pages/lives/room/index',
            default => '/pages/index/index',
        };
    }

    /**
     * 获取事件渠道的默认模板配置。
     *
     * 短信、邮箱和 Webhook BOT 渠道使用 content_template 作为发送内容模板；公众号和小程序
     * 实际发送内容由 field_map 渲染，因此默认 content_template 保持为空。
     *
     * @param string $eventKey 事件标识。
     * @param string $channel 渠道标识。
     * @return array<string, mixed> 可直接写入 message_push_template 的默认字段。
     */
    public function getDefaultTemplateData(string $eventKey, string $channel): array
    {
        $event = $this->getEvent($eventKey);
        $content = (in_array($channel, [self::CHANNEL_SMS, self::CHANNEL_EMAIL], true) || $this->isWebhookChannel($channel))
            ? $this->getDefaultContent($eventKey, $channel)
            : '';
        if ($content === '' && (in_array($channel, [self::CHANNEL_SMS, self::CHANNEL_EMAIL], true) || $this->isWebhookChannel($channel))) {
            $siteNameToken = $channel === self::CHANNEL_SMS ? '${site_name}' : '{{site_name}}';
            $content = $siteNameToken . (string)($event['label'] ?? '消息通知') . '，请及时处理。';
        }

        return [
            'event_key' => $eventKey,
            'channel' => $channel,
            'enabled' => 0,
            'content_template' => $content,
            'template_code' => '',
            'external_template_id' => '',
            'field_map' => [],
            'jump_config' => [],
            'sync_config' => [],
            'sync_status' => 0,
            'last_sync_error' => '',
            'sort' => (int)($event['sort'] ?? 0),
        ];
    }

    /**
     * 获取短信默认内容模板。
     *
     * 该内容仅作为短信/邮箱/Webhook 渠道默认文案使用，占位符使用对应渠道格式。
     * 公众号模板消息和小程序订阅消息的真实发送内容由模板字段映射决定，不依赖这里的文本模板。
     *
     * @param string $eventKey 事件标识。
     */
    protected function getDefaultContent(string $eventKey, string $channel = self::CHANNEL_SMS): string
    {
        $content = match ($eventKey) {
            self::EVENT_REFUND_SUCCESS => '您的订单${order_sn}退款已成功，退款金额${refund_amount}元。',
            self::EVENT_REFUND_FAILED => '您的订单${order_sn}退款处理失败，原因：${fail_reason}。',
            self::EVENT_WITHDRAW_APPROVED => '您的提现申请已审核通过，到账金额${actual_amount}元。',
            self::EVENT_WITHDRAW_FAILED => '您的提现申请未通过，原因：${audit_remark}。',
            self::EVENT_REFUND_APPLY_ADMIN => '订单${order_sn}发起退款申请，退款金额${refund_amount}元，请及时处理。',
            self::EVENT_CONTENT_AUDIT => '内容《${content_title}》审核结果：${audit_status_text}。',
            self::EVENT_SPECIAL_AUDIT => '专题《${content_title}》审核结果：${audit_status_text}。',
            self::EVENT_LIVE_AUDIT => '直播《${live_title}》审核结果：${audit_status_text}。',
            default => '',
        };

        if ($channel === self::CHANNEL_EMAIL) {
            return preg_replace('/\$\{\s*([a-zA-Z0-9_]+)\s*\}/', '{{$1}}', $content) ?? $content;
        }

        return $content;
    }

    /**
     * 合并事件可用变量分组。
     *
     * common 分组会始终保留；传入的业务分组按顺序追加，同名 key 后出现的定义覆盖前者，
     * 最终返回给前端用于字段映射选择和测试变量示例。
     *
     * @param array<int, string> $groups 业务变量分组名称。
     * @return array<int, array{key: string, label: string, sample: string}> 去重后的变量列表。
     */
    protected function mergeVariables(array $groups): array
    {
        $definitions = $this->getVariableGroups();
        $variables = $definitions['common'];

        foreach ($groups as $group) {
            foreach ($definitions[$group] ?? [] as $variable) {
                $variables[] = $variable;
            }
        }

        $unique = [];
        foreach ($variables as $variable) {
            $unique[$variable['key']] = $variable;
        }

        return array_values($unique);
    }

    /**
     * 按变量分组获取变量。
     *
     * @param array<int, string> $groups 业务变量分组名称。
     * @return array<int, array{key: string, label: string, sample: string}> 去重后的变量列表。
     */
    public function getVariablesForGroups(array $groups): array
    {
        return $this->mergeVariables($groups);
    }

    /**
     * 获取自定义消息事件定义。
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getCustomEvents(): array
    {
        try {
            return app()->make(MessagePushEventManageService::class)->getEnabledDefinitions();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * 获取全部变量分组。
     *
     * key 是模板中可使用的变量名，label 是管理端展示文案，sample 用于管理端生成测试
     * payload 示例。变量真实值由 MessagePushRenderService 根据业务 payload 统一渲染。
     *
     * @return array<string, array<int, array{key: string, label: string, sample: string}>>
     */
    protected function getVariableGroups(): array
    {
        return [
            'common' => [
                ['key' => 'site_name', 'label' => '站点名称', 'sample' => '乖乖兔多商户平台'],
                ['key' => 'now_time', 'label' => '当前时间', 'sample' => date('Y-m-d H:i:s')],
            ],
            'order' => [
                ['key' => 'order_id', 'label' => '订单ID', 'sample' => '1001'],
                ['key' => 'order_sn', 'label' => '订单号', 'sample' => 'ORD202607290001'],
                ['key' => 'product_name', 'label' => '商品名称', 'sample' => '示例商品名称'],
                ['key' => 'pay_amount', 'label' => '支付金额', 'sample' => '99.00'],
                ['key' => 'order_amount', 'label' => '订单金额', 'sample' => '99.00'],
            ],
            'refund' => [
                ['key' => 'refund_sn', 'label' => '退款单号', 'sample' => 'RF202607290001'],
                ['key' => 'refund_amount', 'label' => '退款金额', 'sample' => '99.00'],
                ['key' => 'refund_reason', 'label' => '退款原因', 'sample' => '用户申请退款'],
                ['key' => 'fail_reason', 'label' => '失败原因', 'sample' => '退款渠道返回失败'],
            ],
            'withdraw' => [
                ['key' => 'withdraw_id', 'label' => '提现ID', 'sample' => '1001'],
                ['key' => 'amount', 'label' => '提现金额', 'sample' => '100.00'],
                ['key' => 'actual_amount', 'label' => '到账金额', 'sample' => '99.90'],
                ['key' => 'fee', 'label' => '手续费', 'sample' => '0.10'],
                ['key' => 'bank_name', 'label' => '开户银行', 'sample' => '招商银行'],
                ['key' => 'audit_remark', 'label' => '审核备注', 'sample' => '资料不完整'],
                ['key' => 'audit_time', 'label' => '审核时间', 'sample' => date('Y-m-d H:i:s')],
            ],
            'audit' => [
                ['key' => 'audit_status', 'label' => '审核状态值', 'sample' => '1'],
                ['key' => 'audit_status_text', 'label' => '审核状态', 'sample' => '已通过'],
                ['key' => 'audit_remark', 'label' => '审核备注', 'sample' => '内容合规'],
                ['key' => 'audit_time', 'label' => '审核时间', 'sample' => date('Y-m-d H:i:s')],
            ],
            'content' => [
                ['key' => 'content_id', 'label' => '内容ID', 'sample' => '1001'],
                ['key' => 'content_title', 'label' => '内容标题', 'sample' => '示例内容'],
            ],
            'live' => [
                ['key' => 'live_id', 'label' => '直播ID', 'sample' => '1001'],
                ['key' => 'live_title', 'label' => '直播标题', 'sample' => '示例直播'],
                ['key' => 'anchor_name', 'label' => '主播名称', 'sample' => '张三'],
            ],
            'user' => [
                ['key' => 'user_id', 'label' => '用户ID', 'sample' => '1001'],
                ['key' => 'user_nickname', 'label' => '用户昵称', 'sample' => '用户昵称'],
                ['key' => 'user_phone', 'label' => '用户手机号', 'sample' => '13800000000'],
            ],
            'merchant' => [
                ['key' => 'merchant_id', 'label' => '商户ID', 'sample' => '1001'],
                ['key' => 'merchant_name', 'label' => '商户名称', 'sample' => '示例商户'],
                ['key' => 'merchant_phone', 'label' => '商户电话', 'sample' => '13800000000'],
            ],
        ];
    }
}
