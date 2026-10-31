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

namespace app\common\service\live;

use app\common\exception\AdminResponseException;
use app\common\model\live\LiveChatRecord;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 直播间聊天记录服务
 */
class LiveChatRecordService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return LiveChatRecord::class;
    }

    /**
     * 创建聊天记录
     * @param array $data
     * @return mixed
     */
    public function createRecord(array $data)
    {
        return $this->create($data);
    }

    /**
     * 获取直播间聊天记录总数。
     */
    public function countRoomRecords(int $roomId): int
    {
        return (int)$this->getModel()
            ->where('room_id', $roomId)
            ->count();
    }

    /**
     * 获取直播间聊天记录分页数据。
     *
     * @return array{list: array, total: int}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomRecordPage(int $roomId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $total = $this->countRoomRecords($roomId);
        $list = $this->getModel()
            ->where('room_id', $roomId)
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return compact('list', 'total');
    }

    /**
     * 删除直播间评论记录。
     *
     * @throws AdminResponseException
     */
    public function deleteRoomRecord(int $roomId, int $commentId): void
    {
        $record = $this->getModel()
            ->where('id', $commentId)
            ->where('room_id', $roomId)
            ->find();
        if (!$record) {
            throw new AdminResponseException('评论不存在或不属于该直播间');
        }

        $record->delete();
    }

    /**
     * 获取直播间聊天记录列表
     * @param int $roomId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomRecords(int $roomId): array
    {
        [$page, $limit] = $this->getPagesValue();

        return $this->getModel()
            ->where('room_id', $roomId)
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
    }
}
