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

use app\common\model\message\MessagePushSendLog;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 消息推送发送日志服务。
 */
class MessagePushLogService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const STATUS_PENDING = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAILED = 2;
    public const STATUS_SKIPPED = 3;

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return MessagePushSendLog::class;
    }

    /**
     * 更新发送日志。
     */
    public function update(int|string|array|float $where, array $data = [])
    {
        return parent::update($where, $this->encodeJsonFields($data));
    }

    /**
     * 分页日志列表。
     */
    public function getList(array $params = []): array
    {
        [$page, $limit, $defaultLimit] = $this->getPagesValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;
        $eventKey = trim((string)($params['event_key'] ?? ''));
        $channel = trim((string)($params['channel'] ?? ''));
        $status = $params['status'] ?? '';
        $receiverType = trim((string)($params['receiver_type'] ?? ''));

        $query = $this->getModel()
            ->when($eventKey !== '', fn($q) => $q->where('event_key', $eventKey))
            ->when($channel !== '', fn($q) => $q->where('channel', $channel))
            ->when($status !== '', fn($q) => $q->where('status', (int)$status))
            ->when($receiverType !== '', fn($q) => $q->where('receiver_type', $receiverType));
        $total = (int)(clone $query)->count();
        $list = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();

        return [
            'list' => array_map(fn($item) => $this->formatLog($item), $list),
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
        ];
    }

    /**
     * 创建日志。
     */
    public function createLog(array $data): array
    {
        $log = $this->create(array_merge([
            'status' => self::STATUS_PENDING,
            'send_time' => null,
        ], $data));

        return $log->toArray();
    }

    /**
     * 更新日志状态。
     */
    public function markResult(int $id, int $status, array $response = [], string $errorMessage = ''): void
    {
        $this->update($id, [
            'status' => $status,
            'response_payload' => $response,
            'error_message' => mb_substr($errorMessage, 0, 1000),
            'send_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 编码查询构造器不会自动处理的 JSON 文本字段。
     */
    protected function encodeJsonFields(array $data): array
    {
        foreach (['request_payload', 'response_payload'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])) {
                $json = json_encode($data[$field] ?? [], JSON_UNESCAPED_UNICODE);
                $data[$field] = $json === false ? '[]' : $json;
            }
        }

        return $data;
    }

    /**
     * 格式化日志。
     */
    protected function formatLog(array $log): array
    {
        return [
            'id' => (int)($log['id'] ?? 0),
            'event_key' => (string)($log['event_key'] ?? ''),
            'channel' => (string)($log['channel'] ?? ''),
            'template_id' => (int)($log['template_id'] ?? 0),
            'receiver_type' => (string)($log['receiver_type'] ?? ''),
            'receiver_id' => (int)($log['receiver_id'] ?? 0),
            'receiver_account' => (string)($log['receiver_account'] ?? ''),
            'relation_type' => (string)($log['relation_type'] ?? ''),
            'relation_id' => (int)($log['relation_id'] ?? 0),
            'rendered_content' => (string)($log['rendered_content'] ?? ''),
            'request_payload' => is_array($log['request_payload'] ?? null) ? $log['request_payload'] : [],
            'response_payload' => is_array($log['response_payload'] ?? null) ? $log['response_payload'] : [],
            'status' => (int)($log['status'] ?? 0),
            'status_text' => $this->resolveStatusText((int)($log['status'] ?? 0)),
            'error_message' => (string)($log['error_message'] ?? ''),
            'send_time' => $log['send_time'] ?? null,
            'create_time' => $log['create_time'] ?? '',
        ];
    }

    /**
     * 日志状态文案。
     */
    protected function resolveStatusText(int $status): string
    {
        return match ($status) {
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED => '失败',
            self::STATUS_SKIPPED => '跳过',
            default => '待发送',
        };
    }
}
