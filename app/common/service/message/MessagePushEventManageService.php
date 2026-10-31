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
use app\common\model\message\MessagePushEvent;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 消息推送自定义事件管理服务。
 */
class MessagePushEventManageService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return MessagePushEvent::class;
    }

    /**
     * 更新自定义事件。
     */
    public function update(int|string|array|float $where, array $data = [])
    {
        return parent::update($where, $this->encodeJsonFields($data));
    }

    /**
     * 获取启用的自定义事件定义。
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEnabledDefinitions(): array
    {
        $rows = $this->getModel()
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
        $events = [];

        foreach ($rows as $row) {
            $definition = $this->formatDefinition($row);
            $events[(string)$definition['key']] = $definition;
        }

        return $events;
    }

    /**
     * 保存自定义消息事件。
     */
    public function saveEvent(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $eventKey = trim((string)($data['event_key'] ?? ''));
        $eventName = trim((string)($data['event_name'] ?? ''));
        $receiverType = trim((string)($data['receiver_type'] ?? MessagePushDefinitionService::RECEIVER_USER));

        if (!preg_match('/^[a-z][a-z0-9_]{1,59}$/', $eventKey)) {
            throw new AdminResponseException('事件标识需以小写字母开头，只能包含小写字母、数字、下划线，长度2-60位');
        }
        if ($eventName === '') {
            throw new AdminResponseException('请填写消息名称');
        }
        if (!app()->make(MessagePushDefinitionService::class)->hasReceiverType($receiverType)) {
            throw new AdminResponseException('通知类型不存在');
        }
        if ($this->isSystemEventKey($eventKey)) {
            throw new AdminResponseException('事件标识已被系统内置消息占用');
        }

        $query = $this->getModel()->where('event_key', $eventKey);
        if ($id > 0) {
            $query->where('id', '<>', $id);
        }
        if ($query->find()) {
            throw new AdminResponseException('事件标识已存在');
        }

        $saveData = [
            'event_key' => $eventKey,
            'event_name' => $eventName,
            'receiver_type' => $receiverType,
            'relation_type' => trim((string)($data['relation_type'] ?? 'custom')),
            'variables' => $this->normalizeVariables($data['variables'] ?? [], $receiverType),
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 1000),
            'remark' => trim((string)($data['remark'] ?? '')),
        ];

        if ($id > 0) {
            $event = $this->get($id);
            if (!$event) {
                throw new AdminResponseException('消息事件不存在');
            }
            $this->update($id, $saveData);
        } else {
            $event = $this->create($saveData);
            $id = (int)$event->id;
        }

        $fresh = $this->get($id);
        $definition = $this->formatDefinition($fresh ? $fresh->toArray() : $saveData);
        app()->make(MessagePushTemplateService::class)->ensureTemplatesForEvent((string)$definition['key']);

        return $definition;
    }

    /**
     * 格式化自定义事件定义。
     *
     * @param array<string, mixed> $event 事件数据。
     * @return array<string, mixed>
     */
    protected function formatDefinition(array $event): array
    {
        $receiverType = (string)($event['receiver_type'] ?? MessagePushDefinitionService::RECEIVER_USER);

        return [
            'id' => (int)($event['id'] ?? 0),
            'key' => (string)($event['event_key'] ?? ''),
            'label' => (string)($event['event_name'] ?? ''),
            'receiver_type' => $receiverType,
            'receiver_text' => app()->make(MessagePushDefinitionService::class)->getReceiverTypeLabel($receiverType),
            'relation_type' => (string)($event['relation_type'] ?? 'custom'),
            'sort' => (int)($event['sort'] ?? 1000),
            'variables' => $this->normalizeVariables($event['variables'] ?? [], $receiverType),
            'is_system' => 0,
            'status' => (int)($event['status'] ?? 1),
            'remark' => (string)($event['remark'] ?? ''),
        ];
    }

    /**
     * 规范化变量列表。
     */
    protected function normalizeVariables(mixed $value, string $receiverType): array
    {
        $rows = $this->normalizeArray($value);
        if (!$rows) {
            return $this->defaultVariables($receiverType);
        }

        $variables = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = trim((string)($row['key'] ?? ''));
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,59}$/', $key)) {
                continue;
            }

            $variables[$key] = [
                'key' => $key,
                'label' => trim((string)($row['label'] ?? $key)) ?: $key,
                'sample' => (string)($row['sample'] ?? ''),
            ];
        }

        return $variables ? array_values($variables) : $this->defaultVariables($receiverType);
    }

    /**
     * 默认变量。
     */
    protected function defaultVariables(string $receiverType): array
    {
        $groups = match ($receiverType) {
            MessagePushDefinitionService::RECEIVER_USER => ['user'],
            MessagePushDefinitionService::RECEIVER_MERCHANT => ['merchant'],
            default => [],
        };

        return app()->make(MessagePushDefinitionService::class)->getVariablesForGroups($groups);
    }

    /**
     * 判断是否为系统内置事件。
     */
    protected function isSystemEventKey(string $eventKey): bool
    {
        return app()->make(MessagePushDefinitionService::class)->hasSystemEvent($eventKey);
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
        if (array_key_exists('variables', $data) && !is_string($data['variables'])) {
            $json = json_encode($data['variables'] ?? [], JSON_UNESCAPED_UNICODE);
            $data['variables'] = $json === false ? '[]' : $json;
        }

        return $data;
    }
}
