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
use app\common\enum\ConfigKeyEnum;
use app\common\model\message\MessagePushTemplate;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\setting\ConfigService;
use Rabbit\service\wechat\MiniProgram;
use Rabbit\service\wechat\OfficialAccount;
use Throwable;

/**
 * 消息推送模板配置服务。
 */
class MessagePushTemplateService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const SYNC_STATUS_NONE = 0;
    public const SYNC_STATUS_SUCCESS = 1;
    public const SYNC_STATUS_FAILED = 2;

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return MessagePushTemplate::class;
    }

    /**
     * 更新模板配置。
     */
    public function update(int|string|array|float $where, array $data = [])
    {
        return parent::update($where, $this->encodeJsonFields($data));
    }

    /**
     * 获取事件列表及可配置渠道配置。
     */
    public function getList(): array
    {
        $this->ensureDefaultTemplates();
        $definitionService = $this->definitionService();
        $events = $definitionService->getEvents();
        $templates = $this->getModel()
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
        $templateMap = [];

        foreach ($templates as $template) {
            $templateMap[(string)$template['event_key']][(string)$template['channel']] = $this->formatTemplate($template);
        }

        $list = [];
        foreach ($events as $eventKey => $event) {
            $channelRows = [];
            $channels = $definitionService->getChannelsForEvent($event);
            foreach ($channels as $channel => $channelDefinition) {
                $row = $templateMap[$eventKey][$channel]
                    ?? $this->formatTemplate($definitionService->getDefaultTemplateData($eventKey, $channel));
                $row['channel_label'] = $channelDefinition['label'];
                $channelRows[] = $row;
            }

            $list[] = [
                'event_key' => $eventKey,
                'event_name' => $event['label'],
                'receiver_type' => $event['receiver_type'],
                'receiver_text' => $event['receiver_text'],
                'relation_type' => $event['relation_type'],
                'sort' => $event['sort'],
                'variables' => $event['variables'],
                'is_system' => (int)($event['is_system'] ?? 1),
                'status' => (int)($event['status'] ?? 1),
                'remark' => (string)($event['remark'] ?? ''),
                'channels' => $channelRows,
            ];
        }

        return ['list' => $list, 'total' => count($list)];
    }

    /**
     * 获取单个事件配置详情。
     */
    public function getDetail(string $eventKey): array
    {
        $this->ensureDefaultTemplates();
        $definitionService = $this->definitionService();
        $event = $definitionService->getEvent($eventKey);
        if (!$event) {
            throw new AdminResponseException('消息事件不存在');
        }

        $templates = $this->getModel()
            ->where('event_key', $eventKey)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
        $channels = $definitionService->getChannelsForEvent($event);
        $map = [];
        foreach ($templates as $template) {
            $map[(string)$template['channel']] = $this->formatTemplate($template);
        }

        $rows = [];
        foreach ($channels as $channel => $definition) {
            $row = $map[$channel] ?? $this->formatTemplate($definitionService->getDefaultTemplateData($eventKey, $channel));
            $row['channel_label'] = $definition['label'];
            $rows[] = $row;
        }

        $event['channels'] = $rows;

        return $event;
    }

    /**
     * 保存渠道配置。
     */
    public function saveConfig(array $data): array
    {
        $eventKey = trim((string)($data['event_key'] ?? ''));
        $channel = trim((string)($data['channel'] ?? ''));
        $this->validateEventAndChannel($eventKey, $channel);

        $template = $this->getByEventChannel($eventKey, $channel);
        if (!$template) {
            $template = $this->create($this->definitionService()->getDefaultTemplateData($eventKey, $channel));
        }

        $isEmailChannel = $channel === MessagePushDefinitionService::CHANNEL_EMAIL;
        $isWebhookChannel = $this->definitionService()->isWebhookChannel($channel);
        $contentTemplate = ($channel === MessagePushDefinitionService::CHANNEL_SMS
            || $isEmailChannel
            || $isWebhookChannel)
            ? trim((string)($data['content_template'] ?? ''))
            : '';

        if ($isEmailChannel) {
            $this->saveEmailConfig($data['sync_config'] ?? []);
        }

        $saveData = [
            'enabled' => (int)($data['enabled'] ?? 0) === 1 ? 1 : 0,
            'content_template' => $contentTemplate,
            'template_code' => $isWebhookChannel
                ? ''
                : trim((string)($data['template_code'] ?? '')),
            'external_template_id' => ($isWebhookChannel || $isEmailChannel)
                ? ''
                : trim((string)($data['external_template_id'] ?? '')),
            'field_map' => ($isWebhookChannel || $isEmailChannel)
                ? []
                : $this->normalizeArray($data['field_map'] ?? []),
            'jump_config' => $this->normalizeJumpConfig($channel, $eventKey, $data['jump_config'] ?? []),
            'sync_config' => $isEmailChannel ? [] : $this->normalizeSyncConfig($channel, $data['sync_config'] ?? []),
        ];

        $this->update((int)$template->id, $saveData);

        $fresh = $this->get((int)$template->id);

        return $this->formatTemplate($fresh ? $fresh->toArray() : array_merge($template->toArray(), $saveData));
    }

    /**
     * 修改渠道启用状态。
     */
    public function toggleStatus(int $id, int $status): array
    {
        $template = $this->get($id);
        if (!$template) {
            throw new AdminResponseException('模板配置不存在');
        }

        $templateData = $template->toArray();
        $this->validateEventAndChannel((string)$templateData['event_key'], (string)$templateData['channel']);
        $this->update($id, ['enabled' => $status === 1 ? 1 : 0]);
        $fresh = $this->get($id);

        return $this->formatTemplate($fresh ? $fresh->toArray() : $template->toArray());
    }

    /**
     * 获取启用模板列表。
     */
    public function getEnabledTemplates(string $eventKey): array
    {
        $this->ensureDefaultTemplates();
        $definitionService = $this->definitionService();
        $event = $definitionService->getEvent($eventKey);
        if (!$event) {
            return [];
        }

        $channels = array_keys($definitionService->getChannelsForEvent($event));
        if (!$channels) {
            return [];
        }

        return $this->getModel()
            ->where('event_key', $eventKey)
            ->where('enabled', 1)
            ->whereIn('channel', $channels)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取小程序端可主动订阅的模板消息。
     *
     * 微信小程序 requestSubscribeMessage 单次最多支持 3 个模板 ID，因此这里固定最多返回 3 条。
     *
     * @param string $type 事件标识或业务类型，如 refund_success/order/user_withdraw/article。
     * @return array<string, mixed>
     */
    public function getMiniappSubscribeMessages(string $type = ''): array
    {
        $type = trim($type);
        if ($type === '') {
            return $this->getAllMiniappSubscribeMessages();
        }

        return $this->buildMiniappSubscribePayload($type, true);
    }

    /**
     * 获取全部小程序端订阅模板配置。
     *
     * @return array<string, mixed>
     */
    protected function getAllMiniappSubscribeMessages(): array
    {
        $map = [];
        foreach ($this->resolveMiniappSubscribeTypes() as $type) {
            $map[$type] = $this->buildMiniappSubscribePayload($type, false);
        }

        return [
            'limit' => 3,
            'total' => count($map),
            'map' => $map,
            'list' => array_values($map),
        ];
    }

    /**
     * 组装指定类型的小程序订阅模板配置。
     *
     * @return array<string, mixed>
     */
    protected function buildMiniappSubscribePayload(string $type, bool $throwIfMissing): array
    {
        $events = $this->resolveMiniappSubscribeEvents($type);
        if (!$events) {
            if ($throwIfMissing) {
                throw new AdminResponseException('订阅消息类型不存在');
            }

            return [
                'type' => $type,
                'limit' => 3,
                'total' => 0,
                'template_ids' => [],
                'event_keys' => [],
                'list' => [],
            ];
        }

        $limit = 3;
        $list = [];
        $templateIds = [];
        $templateIndexMap = [];
        $matchedEventKeys = [];
        $queryEventKeys = array_keys($events);
        $templates = $queryEventKeys
            ? $this->getModel()
                ->whereIn('event_key', $queryEventKeys)
                ->where('channel', MessagePushDefinitionService::CHANNEL_MINIAPP)
                ->where('enabled', 1)
                ->order('sort asc, id asc')
                ->select()
                ->toArray()
            : [];

        foreach ($templates as $template) {
            $eventKey = (string)($template['event_key'] ?? '');
            $event = $events[$eventKey] ?? [];
            $templateId = trim((string)($template['external_template_id'] ?? ''));
            if ($templateId === '') {
                continue;
            }

            $matchedEventKeys[$eventKey] = true;
            if (isset($templateIndexMap[$templateId])) {
                $index = $templateIndexMap[$templateId];
                $list[$index]['event_keys'][] = $eventKey;
                continue;
            }

            if (count($templateIds) >= $limit) {
                break;
            }

            $templateIds[$templateId] = true;
            $templateIndexMap[$templateId] = count($list);
            $list[] = [
                'id' => $templateId,
                'template_id' => $templateId,
                'event_key' => $eventKey,
                'event_keys' => [$eventKey],
                'event_name' => (string)($event['label'] ?? $eventKey),
            ];
        }

        return [
            'type' => $type,
            'limit' => $limit,
            'total' => count($list),
            'template_ids' => array_values(array_keys($templateIds)),
            'event_keys' => array_values(array_keys($matchedEventKeys)),
            'list' => $list,
        ];
    }

    /**
     * 按渠道一键同步全部微信模板。
     */
    public function syncChannelTemplates(string $channel): array
    {
        $channel = trim($channel);
        if (!in_array($channel, [
            MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT,
            MessagePushDefinitionService::CHANNEL_MINIAPP,
        ], true)) {
            throw new AdminResponseException('当前渠道不支持同步');
        }

        $this->ensureDefaultTemplates();
        $channelDefinitions = $this->definitionService()->getChannels();
        $events = $this->definitionService()->getEvents();
        $eventKeys = [];
        foreach ($events as $eventKey => $event) {
            if ($this->definitionService()->isEventChannelAvailable($event, $channel)) {
                $eventKeys[] = $eventKey;
            }
        }

        $templates = $this->getModel()
            ->where('channel', $channel)
            ->whereIn('event_key', $eventKeys)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();

        $summary = [
            'channel' => $channel,
            'channel_label' => $channelDefinitions[$channel]['label'] ?? $channel,
            'total' => count($templates),
            'success' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($templates as $template) {
            $item = $this->syncTemplateRow((array)$template);
            $statusKey = $item['status'] === 'success' ? 'success' : 'failed';
            $summary[$statusKey]++;
            $summary['items'][] = $item;
        }

        return $summary;
    }

    /**
     * 格式化模板配置。
     */
    public function formatTemplate(array $template): array
    {
        $channel = (string)($template['channel'] ?? '');
        $channelDefinitions = $this->definitionService()->getChannels();

        return [
            'id' => (int)($template['id'] ?? 0),
            'event_key' => (string)($template['event_key'] ?? ''),
            'channel' => $channel,
            'channel_label' => $channelDefinitions[$channel]['label'] ?? $channel,
            'enabled' => (int)($template['enabled'] ?? 0),
            'content_template' => (string)($template['content_template'] ?? ''),
            'template_code' => (string)($template['template_code'] ?? ''),
            'external_template_id' => (string)($template['external_template_id'] ?? ''),
            'field_map' => $this->normalizeArray($template['field_map'] ?? []),
            'jump_config' => $this->normalizeJumpConfig($channel, (string)($template['event_key'] ?? ''), $template['jump_config'] ?? []),
            'sync_config' => $channel === MessagePushDefinitionService::CHANNEL_EMAIL
                ? $this->getEmailFormConfig($template['sync_config'] ?? [])
                : $this->normalizeSyncConfig($channel, $template['sync_config'] ?? []),
            'sync_status' => (int)($template['sync_status'] ?? 0),
            'sync_status_text' => $this->resolveSyncStatusText((int)($template['sync_status'] ?? 0)),
            'last_sync_time' => $template['last_sync_time'] ?? null,
            'last_sync_error' => (string)($template['last_sync_error'] ?? ''),
            'sort' => (int)($template['sort'] ?? 0),
            'create_time' => $template['create_time'] ?? '',
            'update_time' => $template['update_time'] ?? '',
        ];
    }

    /**
     * 获取事件渠道模板。
     */
    protected function getByEventChannel(string $eventKey, string $channel)
    {
        return $this->getModel()
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->find();
    }

    /**
     * 确保内置模板行存在。
     */
    protected function ensureDefaultTemplates(): void
    {
        $definitionService = $this->definitionService();
        $events = $definitionService->getEvents();

        foreach ($events as $eventKey => $event) {
            $this->ensureTemplatesForDefinition((string)$eventKey, $event);
        }
    }

    /**
     * 确保指定事件模板行存在。
     */
    public function ensureTemplatesForEvent(string $eventKey): void
    {
        $event = $this->definitionService()->getEvent($eventKey);
        if (!$event) {
            return;
        }

        $this->ensureTemplatesForDefinition($eventKey, $event);
    }

    /**
     * 确保指定事件定义的模板行存在。
     *
     * @param array<string, mixed> $event 事件定义。
     */
    protected function ensureTemplatesForDefinition(string $eventKey, array $event): void
    {
        $definitionService = $this->definitionService();
        $channels = $definitionService->getChannelsForEvent($event);
        foreach ($channels as $channel => $definition) {
            if ($this->getByEventChannel($eventKey, $channel)) {
                continue;
            }
            $this->create($definitionService->getDefaultTemplateData($eventKey, $channel));
        }
    }

    /**
     * 同步单条模板配置，失败时记录错误并继续批量任务。
     */
    protected function syncTemplateRow(array $template): array
    {
        $id = (int)($template['id'] ?? 0);
        $eventKey = (string)($template['event_key'] ?? '');
        $event = $this->definitionService()->getEvent($eventKey);
        $eventName = (string)($event['label'] ?? $eventKey);

        try {
            $response = (string)($template['channel'] ?? '') === MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT
                ? $this->syncOfficialAccountTemplate($template)
                : $this->syncMiniappTemplate($template);

            $externalTemplateId = (string)($response['template_id'] ?? $response['priTmplId'] ?? '');
            if ($externalTemplateId === '') {
                throw new AdminResponseException('微信未返回模板ID');
            }

            $this->update($id, [
                'external_template_id' => $externalTemplateId,
                'sync_status' => self::SYNC_STATUS_SUCCESS,
                'last_sync_time' => date('Y-m-d H:i:s'),
                'last_sync_error' => '',
            ]);

            return [
                'id' => $id,
                'event_key' => $eventKey,
                'event_name' => $eventName,
                'status' => 'success',
                'message' => '同步成功',
                'external_template_id' => $externalTemplateId,
            ];
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage() ?: '同步失败';
            if ($id > 0) {
                $this->update($id, [
                    'sync_status' => self::SYNC_STATUS_FAILED,
                    'last_sync_time' => date('Y-m-d H:i:s'),
                    'last_sync_error' => mb_substr($message, 0, 500),
                ]);
            }

            return [
                'id' => $id,
                'event_key' => $eventKey,
                'event_name' => $eventName,
                'status' => 'failed',
                'message' => $message,
                'external_template_id' => '',
            ];
        }
    }

    /**
     * 同步公众号模板。
     */
    protected function syncOfficialAccountTemplate(array $template): array
    {
        $shortId = trim((string)($template['template_code'] ?? ''));
        if ($shortId === '') {
            throw new AdminResponseException('请先填写公众号模板短ID');
        }

        return app()->make(OfficialAccount::class)
            ->addTemplate($shortId)
            ->getResponse();
    }

    /**
     * 同步小程序订阅模板。
     */
    protected function syncMiniappTemplate(array $template): array
    {
        $tid = trim((string)($template['template_code'] ?? ''));
        if ($tid === '') {
            throw new AdminResponseException('请先填写小程序模板编号');
        }

        $kidList = $this->resolveMiniappKidList($template);
        $event = $this->definitionService()->getEvent((string)($template['event_key'] ?? ''));
        $sceneDesc = trim((string)($event['label'] ?? '消息通知'));

        return app()->make(MiniProgram::class)->addSubscribeTemplate(
            $tid,
            $kidList,
            mb_substr($sceneDesc !== '' ? $sceneDesc : '消息通知', 0, 15)
        );
    }

    /**
     * 从字段映射中提取小程序订阅模板关键词 ID。
     */
    protected function resolveMiniappKidList(array $template): array
    {
        $fieldMap = $this->normalizeArray($template['field_map'] ?? []);
        $kidList = [];

        foreach ($fieldMap as $row) {
            if (!is_array($row)) {
                continue;
            }

            $field = trim((string)($row['field'] ?? ''));
            if ($field !== '' && preg_match('/(\d+)$/', $field, $matches)) {
                $kidList[] = (int)$matches[1];
            }
        }

        $kidList = array_values(array_unique(array_filter($kidList, static fn (int $kid): bool => $kid > 0)));
        if (!$kidList) {
            throw new AdminResponseException('请先配置小程序模板字段映射');
        }

        return $kidList;
    }

    /**
     * 校验事件和渠道。
     */
    protected function validateEventAndChannel(string $eventKey, string $channel): void
    {
        $definitionService = $this->definitionService();
        $event = $definitionService->getEvent($eventKey);
        if (!$event) {
            throw new AdminResponseException('消息事件不存在');
        }
        if (!$definitionService->hasChannel($channel)) {
            throw new AdminResponseException('消息渠道不存在');
        }
        if (!$definitionService->isEventChannelAvailable($event, $channel)) {
            throw new AdminResponseException('当前接收对象不支持该消息渠道');
        }
    }

    /**
     * 根据前端订阅类型解析消息事件。
     *
     * 支持直接传事件标识，也支持传业务 relation_type；常用业务别名会规范化后再匹配。
     *
     * @return array<string, array<string, mixed>>
     */
    protected function resolveMiniappSubscribeEvents(string $type): array
    {
        $events = $this->definitionService()->getEvents();
        if (isset($events[$type])) {
            return [$type => $events[$type]];
        }

        $relationType = $this->normalizeMiniappSubscribeType($type);
        $matched = [];
        foreach ($events as $eventKey => $event) {
            if ((string)($event['relation_type'] ?? '') !== $relationType) {
                continue;
            }

            if (!$this->definitionService()->isEventChannelAvailable(
                $event,
                MessagePushDefinitionService::CHANNEL_MINIAPP
            )) {
                continue;
            }

            $matched[(string)$eventKey] = $event;
        }

        return $matched;
    }

    /**
     * 获取全部支持小程序订阅的业务类型。
     *
     * @return array<int, string>
     */
    protected function resolveMiniappSubscribeTypes(): array
    {
        $types = [];
        foreach ($this->definitionService()->getEvents() as $event) {
            if (!$this->definitionService()->isEventChannelAvailable(
                $event,
                MessagePushDefinitionService::CHANNEL_MINIAPP
            )) {
                continue;
            }

            $type = trim((string)($event['relation_type'] ?? ''));
            if ($type !== '') {
                $types[$type] = true;
            }
        }

        return array_values(array_keys($types));
    }

    /**
     * 规范化前端按钮传入的订阅业务类型。
     */
    protected function normalizeMiniappSubscribeType(string $type): string
    {
        return match ($type) {
            'refund', 'order_refund' => 'order',
            'withdraw' => 'user_withdraw',
            'content' => 'article',
            'live' => 'live_room',
            default => $type,
        };
    }

    /**
     * 规范化数组字段。
     */
    protected function normalizeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * 编码查询构造器不会自动处理的 JSON 文本字段。
     */
    protected function encodeJsonFields(array $data): array
    {
        foreach (['field_map', 'jump_config', 'sync_config'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])) {
                $json = json_encode($data[$field] ?? [], JSON_UNESCAPED_UNICODE);
                $data[$field] = $json === false ? '[]' : $json;
            }
        }

        return $data;
    }

    /**
     * 规范化跳转配置。
     */
    protected function normalizeJumpConfig(string $channel, string $eventKey, mixed $value): array
    {
        $config = $this->normalizeArray($value);

        if ($channel === MessagePushDefinitionService::CHANNEL_OFFICIAL_ACCOUNT) {
            return [
                'url' => trim((string)($config['url'] ?? '')),
                'mini_appid' => trim((string)($config['mini_appid'] ?? '')),
                'mini_page_path' => trim((string)($config['mini_page_path'] ?? '')),
            ];
        }

        if ($channel === MessagePushDefinitionService::CHANNEL_MINIAPP) {
            return [
                'page' => $this->definitionService()->getMiniappPage($eventKey),
            ];
        }

        return [];
    }

    /**
     * 规范化同步配置。
     */
    protected function normalizeSyncConfig(string $channel, mixed $value): array
    {
        $config = $this->normalizeArray($value);
        if ($channel === MessagePushDefinitionService::CHANNEL_SMS) {
            return [
                'sign_name' => trim((string)($config['sign_name'] ?? '')),
            ];
        }

        if ($channel === MessagePushDefinitionService::CHANNEL_EMAIL) {
            return $this->getEmailConfig($config);
        }

        if ($this->definitionService()->isWebhookChannel($channel)) {
            return [
                'webhook_url' => trim((string)($config['webhook_url'] ?? '')),
                'secret' => trim((string)($config['secret'] ?? '')),
                'headers' => $this->normalizeHeaderRows($config['headers'] ?? []),
            ];
        }

        return [];
    }

    /**
     * 获取邮箱全局配置。
     *
     * 配置优先读 db_config；若全局配置尚未写入，则回退到模板旧的 sync_config。
     *
     * @param array<string, mixed> $fallback 旧模板配置。
     * @return array{api_url: string, api_key: string, subject_template: string, from_name: string}
     */
    public function getEmailConfig(array $fallback = []): array
    {
        $fallback = $this->normalizeArray($fallback);
        $config = [
            'api_url' => trim((string)db_config(ConfigKeyEnum::MESSAGE_PUSH_EMAIL_API_URL, '')),
            'api_key' => trim((string)db_config(ConfigKeyEnum::MESSAGE_PUSH_EMAIL_API_KEY, '')),
            'subject_template' => trim((string)db_config(ConfigKeyEnum::MESSAGE_PUSH_EMAIL_SUBJECT_TEMPLATE, '')),
            'from_name' => trim((string)db_config(ConfigKeyEnum::MESSAGE_PUSH_EMAIL_FROM_NAME, '')),
        ];

        return [
            'api_url' => $config['api_url'] !== '' ? $config['api_url'] : trim((string)($fallback['api_url'] ?? '')),
            'api_key' => $config['api_key'] !== '' ? $config['api_key'] : trim((string)($fallback['api_key'] ?? '')),
            'subject_template' => $config['subject_template'] !== '' ? $config['subject_template'] : trim((string)($fallback['subject_template'] ?? '')),
            'from_name' => $config['from_name'] !== '' ? $config['from_name'] : trim((string)($fallback['from_name'] ?? '')),
        ];
    }

    /**
     * 获取邮箱页面可编辑配置。
     *
     * @param array<string, mixed> $fallback 旧模板配置。
     * @return array{subject_template: string, from_name: string}
     */
    protected function getEmailFormConfig(array $fallback = []): array
    {
        $config = $this->getEmailConfig($fallback);

        return [
            'subject_template' => $config['subject_template'],
            'from_name' => $config['from_name'],
        ];
    }

    /**
     * 保存邮箱全局配置。
     *
     * @param array<string, mixed> $config 邮箱配置。
     */
    public function saveEmailConfig(array $config): void
    {
        $values = [
            ConfigKeyEnum::MESSAGE_PUSH_EMAIL_SUBJECT_TEMPLATE => trim((string)($config['subject_template'] ?? '')),
            ConfigKeyEnum::MESSAGE_PUSH_EMAIL_FROM_NAME => trim((string)($config['from_name'] ?? '')),
        ];

        if (array_key_exists('api_url', $config)) {
            $values[ConfigKeyEnum::MESSAGE_PUSH_EMAIL_API_URL] = trim((string)$config['api_url']);
        }
        if (array_key_exists('api_key', $config)) {
            $values[ConfigKeyEnum::MESSAGE_PUSH_EMAIL_API_KEY] = trim((string)$config['api_key']);
        }

        app()->make(ConfigService::class)->saveKeyValues($values);
    }

    /**
     * 规范化 Webhook 请求头。
     */
    protected function normalizeHeaderRows(mixed $value): array
    {
        $rows = $this->normalizeArray($value);
        $headers = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = trim((string)($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $headers[] = [
                'key' => $key,
                'value' => trim((string)($row['value'] ?? '')),
            ];
        }

        return $headers;
    }

    /**
     * 同步状态文案。
     */
    protected function resolveSyncStatusText(int $status): string
    {
        return match ($status) {
            self::SYNC_STATUS_SUCCESS => '同步成功',
            self::SYNC_STATUS_FAILED => '同步失败',
            default => '未同步',
        };
    }

    /**
     * 定义服务。
     */
    protected function definitionService(): MessagePushDefinitionService
    {
        return app()->make(MessagePushDefinitionService::class);
    }
}
