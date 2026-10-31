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

namespace app\controller\merchant\live;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\live\LiveRoomService;
use app\common\service\websocket\WebSocketUrlService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户端直播间管理控制器
 */
class LiveRoom extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    #[Inject]
    protected LiveRoomService $service;

    protected function getStatusField(): string
    {
        return 'status';
    }

    protected function getValidateClassName(): ?string
    {
        return null;
    }

    protected function getIndexFields(): array
    {
        return [
            ['title', ''],
            ['live_anchor_id', ''],
            ['category_id', ''],
            ['status', ''],
            ['is_hot', ''],
            ['create_time', '', 'time'],
        ];
    }

    protected function getRequestFields(): array
    {
        return [
            ['live_anchor_id', 0],
            ['category_id', 0],
            ['title', ''],
            ['cover', ''],
            ['description', ''],
            ['notice', ''],
            ['welcome_msg', ''],
            ['tags', ''],
            ['banner', ''],
            ['poster', ''],
            ['intro', ''],
            ['is_record', 0],
            ['is_playback_auth', 0],
            ['allow_landscape', 0],
            ['stream_type', LiveRoomService::STREAM_TYPE_OBS],
            ['external_play_url', ''],
            ['keywords', ''],
            ['start_time', ''],
            ['end_time', ''],
            ['sort', 0],
            ['is_hot', 0],
            ['status', 0],
        ];
    }

    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 重写 index 方法，加入商户 ID 过滤。
     */
    public function index(): mixed
    {
        $params                = $this->request->getMore($this->getIndexFields());
        $params['merchant_id'] = $this->merchantId;
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 读取直播间详情时校验商户归属。
     *
     * @param mixed $id 直播间 ID
     */
    public function read($id): Response
    {
        if (!$id) {
            return $this->fail($this->message['edit']['empty']);
        }

        $this->checkRoomOwner((int)$id);
        return $this->success($this->service->resourceRead((int)$id));
    }

    /**
     * 切换展示状态前显式校验归属。
     *
     * @param mixed $id 直播间 ID
     */
    public function show($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['show']['empty']);
        }

        $this->checkRoomOwner((int)$id);

        $status = $this->request->get('status');
        if ($status === null) {
            return $this->fail($this->message['show']['status']);
        }

        $showData = [$this->getStatusField() => $status];
        $this->eventMethod('show', 'before', $showData, (int)$id);

        if ($this->service->resourceShowUpdate((int)$id, $showData)) {
            $this->eventMethod('show', 'after', $showData, (int)$id, true);
            return $this->success($this->message['show']['success']);
        }

        $this->eventMethod('show', 'after', $showData, (int)$id, false);
        return $this->fail($this->message['show']['fail']);
    }

    /**
     * 更新直播间前显式校验归属。
     *
     * @param mixed $id 直播间 ID
     */
    public function update($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['update']['empty']);
        }

        $this->checkRoomOwner((int)$id);

        $data = $this->request->postMore($this->getRequestFields());
        $this->validateCheck($data);
        $this->eventMethod('update', 'before', $data, (int)$id);

        if ($this->service->resourceUpdate((int)$id, $data)) {
            $this->eventMethod('update', 'after', $data, (int)$id, true);
            return $this->success($this->message['update']['success']);
        }

        $this->eventMethod('update', 'after', $data, (int)$id, false);
        return $this->fail($this->message['update']['fail']);
    }

    /**
     * 删除直播间前显式校验归属。
     *
     * @param mixed $id 直播间 ID
     */
    public function delete($id): mixed
    {
        if (!$id) {
            return $this->fail($this->message['delete']['empty']);
        }

        $this->checkRoomOwner((int)$id);
        if ($this->service->resourceDelete((int)$id)) {
            return $this->success($this->message['delete']['success']);
        }

        return $this->fail($this->message['delete']['fail']);
    }

    /**
     * 在保存前注入商户 ID（beforeSave 钩子）。
     */
    protected function beforeSave(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['merchant_id'] = $this->merchantId;
    }

    /**
     * 编辑前重置审核状态。
     */
    protected function beforeUpdate(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['merchant_id']  = $this->merchantId;
    }

    /**
     * 切换直播状态（开播/暂停/结束）。
     */
    public function switchStatus(int $id): Response
    {
        $this->checkRoomOwner($id);

        $status = (int)$this->request->post('status');
        $result = $this->service->switchStatus($id, $status);
        return $this->success($result['msg'], $result);
    }

    /**
     * 切换热门推荐。
     */
    public function toggleHot(int $id): Response
    {
        $this->checkRoomOwner($id);

        $result = $this->service->toggleHot($id);
        return $this->success($result['is_hot'] ? '已设为热门' : '已取消热门', $result);
    }

    /**
     * 获取直播间详情（含主播信息）。
     */
    public function detail(int $id): Response
    {
        $this->checkRoomOwner($id);

        $data = $this->service->getRoomDetail($id);
        return $this->success('获取成功', $data);
    }

    /**
     * 获取 WebSocket Token（用于商户端连接直播间 WebSocket）。
     */
    public function wsToken(int $id): Response
    {
        $this->checkRoomOwner($id);

        return $this->success('获取成功', [
            'token'   => $this->request->token,
            'ws_url'  => app()->make(WebSocketUrlService::class)->getClientUrl($this->request),
            'room_id' => $id,
        ]);
    }

    /**
     * 获取直播间统计概览。
     */
    public function stats(int $id): Response
    {
        $this->checkRoomOwner($id);

        $data = $this->service->getRoomStats($id);
        return $this->success('获取成功', $data);
    }

    /**
     * 获取直播间用户列表（分页）。
     */
    public function users(int $id): Response
    {
        $this->checkRoomOwner($id);

        $page  = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 20);
        $data  = $this->service->getRoomUsers($id, $page, $limit);
        return $this->success('获取成功', $data);
    }

    /**
     * 获取直播间评论列表（分页）。
     */
    public function comments(int $id): Response
    {
        $this->checkRoomOwner($id);

        $page  = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 20);
        $data  = $this->service->getRoomComments($id, $page, $limit);
        return $this->success('获取成功', $data);
    }

    /**
     * 删除直播间评论。
     */
    public function deleteComment(int $roomId, int $commentId): Response
    {
        $this->checkRoomOwner($roomId);

        $this->service->deleteRoomComment($roomId, $commentId);
        return $this->success('删除成功');
    }

    /**
     * 禁言直播间用户。
     */
    public function muteUser(int $roomId, int $userId): Response
    {
        $this->checkRoomOwner($roomId);

        $duration = (int)$this->request->post('duration', 3600);
        $reason   = (string)$this->request->post('reason', '');
        $adminId  = (int)$this->adminId;
        $data     = $this->service->muteRoomUser($roomId, $userId, $adminId, $duration, $reason);
        return $this->success('禁言成功', $data);
    }

    /**
     * 踢出直播间用户。
     */
    public function kickUser(int $roomId, int $userId): Response
    {
        $this->checkRoomOwner($roomId);

        $this->service->kickRoomUser($roomId, $userId);
        return $this->success('踢出成功');
    }

    /**
     * 校验直播间归属。
     */
    protected function checkRoomOwner(int $id): void
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '直播间');
    }
}
