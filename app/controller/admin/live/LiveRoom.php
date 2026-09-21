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

namespace app\controller\admin\live;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\live\LiveRoomService;
use app\common\service\setting\AdminService;
use app\common\service\websocket\WebSocketUrlService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use Rabbit\service\websocket\TokenHelper;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 直播间管理控制器
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

    protected function getValidateClassName(): string|null
    {
        return null;
    }

    protected function getIndexFields(): array
    {
        return [
            ['tab', 'all'],
            ['title', ''],
            ['merchant_id', ''],
            ['live_anchor_id', ''],
            ['category_id', ''],
            ['audit_status', ''],
            ['status', ''],
            ['is_hot', ''],
            ['create_time', '', 'time'],
        ];
    }

    /**
     * 列表（预加载商户、主播、分类，供平台/商户 tab 与所属商户列展示）
     */
    public function index(): mixed
    {
        $params = $this->request->getMore($this->getIndexFields());

        return $this->success($this->service->getResourceList(
            $params,
            ['*'],
            ['merchant', 'anchor', 'category'],
            ['sort' => 'desc', 'id' => 'desc']
        ));
    }

    /**
     * 各 Tab 数量统计
     */
    public function tabStats(): mixed
    {
        return $this->success('ok', $this->service->getAdminTabStats());
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
     * 平台端新增直播间固定为平台归属，并直接审核通过。
     */
    protected function beforeSave(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['merchant_id']  = 0;
        $data['audit_status'] = LiveRoomService::AUDIT_PASS;
        $data['audit_remark'] = '';
    }

    /**
     * 审核直播间
     */
    public function audit(int $id): mixed
    {
        $auditStatus = (int)$this->request->post('audit_status');
        $remark      = $this->request->post('audit_remark', '');
        $this->service->audit($id, $auditStatus, $remark);
        return $this->success('审核操作成功');
    }

    /**
     * 切换直播状态（开播/暂停/结束）
     * @param $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function switchStatus($id): Response
    {
        $status = (int)$this->request->post('status');
        $result = $this->service->switchStatus($id, $status);
        return $this->success($result['msg'], $result);
    }

    /**
     * 切换热门推荐
     * @param $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function toggleHot($id): Response
    {
        $result = $this->service->toggleHot($id);
        return $this->success($result['is_hot'] ? '已设为热门' : '已取消热门', $result);
    }

    /**
     * 获取直播间详情（含主播信息）
     * @param $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function detail($id): Response
    {
        $data = $this->service->getRoomDetail($id);
        return $this->success('获取成功', $data);
    }

    /**
     * 获取 WebSocket Token（用于平台端连接直播间 WebSocket）
     * @param $id
     * @return mixed
     */
    public function wsToken($id): Response
    {
        return $this->success('获取成功', [
            'token'   => TokenHelper::generatePlatformTokenWithScopes(
                (int)$this->adminId,
                app()->make(AdminService::class)->getWebSocketScopes((array)$this->adminInfo)
            ),
            'ws_url'  => app()->make(WebSocketUrlService::class)->getClientUrl($this->request),
            'room_id' => $id,
        ]);
    }

    /**
     * 获取直播间统计概览
     * @param $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function stats($id): Response
    {
        $data = $this->service->getRoomStats((int)$id);
        return $this->success('获取成功', $data);
    }

    /**
     * 获取直播间用户列表（分页）
     * @param $id
     * @return Response
     */
    public function users($id): Response
    {
        $page  = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 20);
        $data  = $this->service->getRoomUsers((int)$id, $page, $limit);
        return $this->success('获取成功', $data);
    }

    /**
     * 获取直播间评论列表（分页）
     * @param $id
     * @return Response
     */
    public function comments($id): Response
    {
        $page  = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 20);
        $data  = $this->service->getRoomComments((int)$id, $page, $limit);
        return $this->success('获取成功', $data);
    }

    /**
     * 管理员按绑定用户身份发送直播间评论。
     * @param $id
     * @return Response
     */
    public function sendComment($id): Response
    {
        $content = (string)$this->request->post('content', '');
        $data = $this->service->sendAdminComment((int)$id, (int)$this->adminId, $content);
        return $this->success('发送成功', $data);
    }

    /**
     * 删除直播间评论
     * @param $roomId
     * @param $commentId
     * @return Response
     */
    public function deleteComment($roomId, $commentId): Response
    {
        $this->service->deleteRoomComment((int)$roomId, (int)$commentId);
        return $this->success('删除成功');
    }

    /**
     * 禁言直播间用户（HTTP 方式，与 WebSocket 方式互补）
     * @param $roomId
     * @param $userId
     * @return Response
     */
    public function muteUser($roomId, $userId): Response
    {
        $duration = (int)$this->request->post('duration', 3600);
        $reason   = (string)$this->request->post('reason', '');
        $adminId  = (int)$this->adminId;
        $data = $this->service->muteRoomUser((int)$roomId, (int)$userId, $adminId, $duration, $reason);
        return $this->success('禁言成功', $data);
    }

    /**
     * 踢出直播间用户（HTTP 方式，与 WebSocket 方式互补）
     * @param $roomId
     * @param $userId
     * @return Response
     */
    public function kickUser($roomId, $userId): Response
    {
        $this->service->kickRoomUser((int)$roomId, (int)$userId);
        return $this->success('踢出成功');
    }
}
