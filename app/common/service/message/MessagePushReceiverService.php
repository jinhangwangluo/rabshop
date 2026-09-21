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
use app\common\model\message\MessagePushReceiver;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 消息推送手动接收人服务。
 */
class MessagePushReceiverService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return MessagePushReceiver::class;
    }

    /**
     * 获取接收人列表。
     */
    public function getList(array $params = []): array
    {
        $eventKey = trim((string)($params['event_key'] ?? ''));
        $status = $params['status'] ?? '';
        $query = $this->getModel()
            ->when($eventKey !== '', fn($q) => $q->where('event_key', $eventKey))
            ->when($status !== '', fn($q) => $q->where('status', (int)$status));
        $total = (int)(clone $query)->count();
        $list = $query->order('sort asc, id asc')->select()->toArray();

        return [
            'list' => array_map(fn($item) => $this->formatReceiver($item), $list),
            'total' => $total,
        ];
    }

    /**
     * 获取启用接收人。
     */
    public function getEnabledByEvent(string $eventKey): array
    {
        return $this->getModel()
            ->where('event_key', $eventKey)
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 保存接收人。
     */
    public function saveReceiver(array $data): array
    {
        $eventKey = trim((string)($data['event_key'] ?? MessagePushDefinitionService::EVENT_REFUND_APPLY_ADMIN));
        $receiverName = trim((string)($data['receiver_name'] ?? ''));
        if ($receiverName === '') {
            throw new AdminResponseException('请填写接收人名称');
        }

        $saveData = [
            'event_key' => $eventKey,
            'receiver_name' => $receiverName,
            'phone' => trim((string)($data['phone'] ?? '')),
            'official_openid' => trim((string)($data['official_openid'] ?? '')),
            'mini_openid' => trim((string)($data['mini_openid'] ?? '')),
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0),
            'remark' => trim((string)($data['remark'] ?? '')),
        ];

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $receiver = $this->get($id);
            if (!$receiver) {
                throw new AdminResponseException('接收人不存在');
            }
            $this->update($id, $saveData);
        } else {
            $receiver = $this->create($saveData);
            $id = (int)$receiver->id;
        }

        $fresh = $this->get($id);

        return $this->formatReceiver($fresh ? $fresh->toArray() : $saveData);
    }

    /**
     * 删除接收人。
     */
    public function deleteReceiver(int $id): bool
    {
        if ($id <= 0) {
            throw new AdminResponseException('参数错误');
        }

        return (bool)$this->getModel()->destroy($id);
    }

    /**
     * 格式化接收人。
     */
    protected function formatReceiver(array $receiver): array
    {
        return [
            'id' => (int)($receiver['id'] ?? 0),
            'event_key' => (string)($receiver['event_key'] ?? ''),
            'receiver_name' => (string)($receiver['receiver_name'] ?? ''),
            'phone' => (string)($receiver['phone'] ?? ''),
            'official_openid' => (string)($receiver['official_openid'] ?? ''),
            'mini_openid' => (string)($receiver['mini_openid'] ?? ''),
            'status' => (int)($receiver['status'] ?? 0),
            'sort' => (int)($receiver['sort'] ?? 0),
            'remark' => (string)($receiver['remark'] ?? ''),
            'create_time' => $receiver['create_time'] ?? '',
            'update_time' => $receiver['update_time'] ?? '',
        ];
    }
}
